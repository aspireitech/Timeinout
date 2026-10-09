<?php
// Subscriptions and payments.
//   Stripe: card subscriptions through Stripe Checkout + Customer Portal; Stripe tells us
//           about payments through a signed webhook (POST /webhooks/stripe).
//   Wave:   monthly invoices created through Wave's GraphQL API and emailed by Wave;
//           the hourly cron checks whether they were paid and raises the next one.
// Keys and IDs are entered in the provider console (/super → Payments) and stored
// encrypted in the settings table.

const GRACE_DAYS = 7; // days after a trial ends before the portal locks

function plans(): array
{
    $plans = cfg('plans', []);
    $rename = ['School' => 'Business', 'Campus' => 'Enterprise']; // older configs used school names
    foreach ($plans as $k => &$p) {
        $p['name'] = $rename[$p['name'] ?? ''] ?? ($p['name'] ?? ucfirst($k));
        $p['limit'] = str_replace(' students', ' people', (string) ($p['limit'] ?? ''));
        $p['amount'] = (float) ($p['amount'] ?? preg_replace('/[^0-9.]/', '', explode('/', (string) ($p['price'] ?? '0'))[0]));
    }
    return $plans;
}

function secret(string $key): string
{
    return (string) (decrypt_pii(setting($key, '')) ?? '');
}

function stripe_ready(): bool
{
    return secret('stripe_secret') !== '';
}

function wave_ready(): bool
{
    return secret('wave_token') !== '' && setting('wave_business_id', '') !== '';
}

// ---------- Subscription state ----------
/** 'active' | 'trial' | 'grace' | 'expired' | 'suspended' */
function billing_state(?array $t = null): string
{
    $t = $t ?? tenant();
    if ($t['status'] === 'suspended') {
        return 'suspended';
    }
    if ($t['status'] === 'cancelled') {
        return 'expired';
    }
    if ($t['status'] === 'active') {
        return 'active';
    }
    $ends = strtotime((string) $t['trial_ends_at']) ?: 0;
    if (time() <= $ends) {
        return 'trial';
    }
    return time() <= $ends + GRACE_DAYS * 86400 ? 'grace' : 'expired';
}

function trial_days_left(?array $t = null): int
{
    $t = $t ?? tenant();
    return (int) ceil(((strtotime((string) $t['trial_ends_at']) ?: 0) - time()) / 86400);
}

/** Lock an expired portal: admins are sent to Billing; the kiosk shows a notice. */
function billing_gate(string $path): void
{
    if (billing_state() !== 'expired') {
        return;
    }
    foreach (['/login', '/logout', '/admin/billing', '/admin/audit', '/admin/users', '/admin/password'] as $open) {
        if ($path === $open || str_starts_with($path, $open . '/') || str_starts_with($path, $open . '?')) {
            return;
        }
    }
    if (str_starts_with($path, '/admin')) {
        flash('error', 'Your subscription has ended. Choose a plan to continue.');
        redirect(url('/admin/billing'));
    }
    http_response_code(402);
    view('partials/error', ['code' => 402, 'message' => 'This portal\'s subscription has ended. An administrator can renew it in Admin → Billing.']);
}

function record_payment(array $p): void
{
    q('INSERT INTO payments (tenant_id, provider, external_id, amount_cents, currency, status, description, invoice_url, period_start, period_end, paid_at, created_at)
       VALUES (?,?,?,?,?,?,?,?,?,?,?,?)
       ON DUPLICATE KEY UPDATE status = VALUES(status), amount_cents = VALUES(amount_cents), invoice_url = COALESCE(VALUES(invoice_url), invoice_url),
         paid_at = COALESCE(VALUES(paid_at), paid_at), period_start = COALESCE(VALUES(period_start), period_start), period_end = COALESCE(VALUES(period_end), period_end)',
        [$p['tenant_id'] ?? null, $p['provider'], $p['external_id'] ?? null, (int) ($p['amount_cents'] ?? 0), strtoupper($p['currency'] ?? 'USD'),
         $p['status'], $p['description'] ?? null, $p['invoice_url'] ?? null, $p['period_start'] ?? null, $p['period_end'] ?? null,
         $p['paid_at'] ?? null, gmdate('Y-m-d H:i:s')]);
}

function money(int $cents, string $currency = 'USD'): string
{
    return ($currency === 'USD' ? '$' : $currency . ' ') . number_format($cents / 100, 2);
}

// ---------- Stripe ----------
function stripe_request(string $method, string $path, array $params = []): array
{
    $base = rtrim((string) setting('stripe_api_base', 'https://api.stripe.com'), '/');
    $ch = curl_init($base . $path . ($method === 'GET' && $params ? '?' . http_build_query($params) : ''));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 25, CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . secret('stripe_secret'), 'Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_POSTFIELDS => $method === 'GET' ? null : http_build_query($params),
    ]);
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($body === false) {
        throw new RuntimeException('Could not reach Stripe: ' . $err);
    }
    $data = json_decode((string) $body, true) ?: [];
    if ($code >= 400) {
        throw new RuntimeException('Stripe: ' . ($data['error']['message'] ?? "HTTP $code"));
    }
    return $data;
}

function owner_email(array $t): ?string
{
    return val("SELECT email FROM users WHERE tenant_id = ? AND role = 'owner' ORDER BY id LIMIT 1", [$t['id']]);
}

/** Stripe Checkout page for a plan. Returns the URL to send the admin to. */
function stripe_checkout_url(array $t, string $plan): string
{
    $price = (string) setting('stripe_price_' . $plan, '');
    if ($price === '') {
        throw new RuntimeException('This plan has no Stripe price yet. Ask your service provider to add it.');
    }
    $params = [
        'mode' => 'subscription',
        'line_items' => [['price' => $price, 'quantity' => 1]],
        'success_url' => tenant_url($t, '/admin/billing?paid=1'),
        'cancel_url' => tenant_url($t, '/admin/billing'),
        'client_reference_id' => (string) $t['id'],
        'metadata' => ['tenant_id' => (string) $t['id'], 'plan' => $plan],
        'subscription_data' => ['metadata' => ['tenant_id' => (string) $t['id'], 'plan' => $plan]],
        'allow_promotion_codes' => 'true',
    ];
    if ($t['stripe_customer_id']) {
        $params['customer'] = $t['stripe_customer_id'];
    } elseif ($email = owner_email($t)) {
        $params['customer_email'] = $email;
    }
    return (string) stripe_request('POST', '/v1/checkout/sessions', $params)['url'];
}

/** Stripe's hosted page where the customer changes card, plan or cancels. */
function stripe_portal_url(array $t): string
{
    return (string) stripe_request('POST', '/v1/billing_portal/sessions', [
        'customer' => $t['stripe_customer_id'], 'return_url' => tenant_url($t, '/admin/billing'),
    ])['url'];
}

/** Checks the Stripe-Signature header (HMAC-SHA256 of "timestamp.payload", 5-minute tolerance). */
function stripe_signature_ok(string $payload, string $header, string $secret): bool
{
    $ts = null;
    $sigs = [];
    foreach (explode(',', $header) as $part) {
        [$k, $v] = array_pad(explode('=', trim($part), 2), 2, '');
        if ($k === 't') {
            $ts = (int) $v;
        } elseif ($k === 'v1') {
            $sigs[] = $v;
        }
    }
    if (!$ts || !$sigs || abs(time() - $ts) > 300) {
        return false;
    }
    $expected = hash_hmac('sha256', $ts . '.' . $payload, $secret);
    foreach ($sigs as $s) {
        if (hash_equals($expected, $s)) {
            return true;
        }
    }
    return false;
}

function plan_for_price(?string $priceId): ?string
{
    foreach (array_keys(plans()) as $k) {
        if ($priceId && setting('stripe_price_' . $k) === $priceId) {
            return $k;
        }
    }
    return null;
}

function tenant_for_stripe(array $obj): ?array
{
    $id = (int) ($obj['metadata']['tenant_id'] ?? $obj['client_reference_id'] ?? 0);
    if ($id && ($t = row('SELECT * FROM tenants WHERE id = ?', [$id]))) {
        return $t;
    }
    foreach (['customer', 'subscription'] as $k) {
        $v = $obj[$k] ?? null;
        if (is_string($v) && $v !== '') {
            $col = $k === 'customer' ? 'stripe_customer_id' : 'stripe_subscription_id';
            if ($t = row("SELECT * FROM tenants WHERE $col = ?", [$v])) {
                return $t;
            }
        }
    }
    if (($obj['object'] ?? '') === 'subscription' && ($t = row('SELECT * FROM tenants WHERE stripe_subscription_id = ?', [$obj['id']]))) {
        return $t;
    }
    return null;
}

/** Applies one Stripe event. Returns a short description for the log. */
function stripe_handle_event(array $event): string
{
    $obj = $event['data']['object'] ?? [];
    $t = tenant_for_stripe($obj);
    $type = (string) ($event['type'] ?? '');
    if (!$t) {
        return "$type: no matching portal (ignored)";
    }
    $name = $t['name'];
    switch ($type) {
        case 'checkout.session.completed':
            update('tenants', ['billing_provider' => 'stripe', 'stripe_customer_id' => $obj['customer'] ?? null,
                'stripe_subscription_id' => $obj['subscription'] ?? null, 'subscription_status' => 'active', 'status' => 'active',
                'plan' => isset(plans()[$obj['metadata']['plan'] ?? '']) ? $obj['metadata']['plan'] : $t['plan']], 'id = ?', [$t['id']]);
            return "$name subscribed by card";
        case 'customer.subscription.created':
        case 'customer.subscription.updated':
            $s = (string) ($obj['status'] ?? '');
            $end = $obj['current_period_end'] ?? ($obj['items']['data'][0]['current_period_end'] ?? null);
            $plan = plan_for_price($obj['items']['data'][0]['price']['id'] ?? null) ?? $t['plan'];
            $status = in_array($s, ['active', 'trialing', 'past_due'], true) ? 'active' : ($s === 'canceled' || $s === 'unpaid' ? 'cancelled' : $t['status']);
            update('tenants', ['stripe_subscription_id' => $obj['id'], 'stripe_customer_id' => $obj['customer'] ?? $t['stripe_customer_id'],
                'subscription_status' => $s ?: null, 'status' => $status, 'plan' => $plan, 'billing_provider' => 'stripe',
                'current_period_end' => $end ? gmdate('Y-m-d H:i:s', (int) $end) : $t['current_period_end']], 'id = ?', [$t['id']]);
            return "$name subscription $s ($plan)" . (!empty($obj['cancel_at_period_end']) ? ', cancels at period end' : '');
        case 'customer.subscription.deleted':
            update('tenants', ['subscription_status' => 'canceled', 'status' => 'cancelled'], 'id = ?', [$t['id']]);
            return "$name subscription cancelled";
        case 'invoice.paid':
        case 'invoice.payment_succeeded':
        case 'invoice.payment_failed':
            $paid = $type !== 'invoice.payment_failed';
            $line = $obj['lines']['data'][0]['period'] ?? [];
            record_payment([
                'tenant_id' => $t['id'], 'provider' => 'stripe', 'external_id' => $obj['id'] ?? null,
                'amount_cents' => (int) ($paid ? ($obj['amount_paid'] ?? 0) : ($obj['amount_due'] ?? 0)), 'currency' => $obj['currency'] ?? 'usd',
                'status' => $paid ? 'paid' : 'failed', 'description' => 'Subscription ' . ($obj['number'] ?? ''),
                'invoice_url' => $obj['hosted_invoice_url'] ?? null,
                'period_start' => isset($line['start']) ? gmdate('Y-m-d', (int) $line['start']) : null,
                'period_end' => isset($line['end']) ? gmdate('Y-m-d', (int) $line['end']) : null,
                'paid_at' => $paid ? gmdate('Y-m-d H:i:s', (int) ($obj['status_transitions']['paid_at'] ?? time())) : null,
            ]);
            update('tenants', ['subscription_status' => $paid ? 'active' : 'past_due'] + ($paid ? ['status' => 'active'] : []), 'id = ?', [$t['id']]);
            if (!$paid && ($email = owner_email($t))) {
                send_mail([$email], $name . ': card payment failed', '<p>We could not charge your card for your ' . e(cfg('app_name')) . ' subscription. Please update your card in <a href="' . e(tenant_url($t, '/admin/billing')) . '">Admin → Billing</a>.</p>');
            }
            return "$name payment " . ($paid ? 'received ' : 'FAILED ') . money((int) ($paid ? ($obj['amount_paid'] ?? 0) : ($obj['amount_due'] ?? 0)), strtoupper($obj['currency'] ?? 'usd'));
    }
    return "$type for $name (no action needed)";
}

// ---------- Wave ----------
function wave_gql(string $query, array $variables = []): array
{
    $ch = curl_init((string) setting('wave_api_url', 'https://gql.waveapps.com/graphql/public'));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 25, CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . secret('wave_token'), 'Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode(['query' => $query, 'variables' => $variables ?: new stdClass()]),
    ]);
    $body = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    if ($body === false) {
        throw new RuntimeException('Could not reach Wave: ' . $err);
    }
    $data = json_decode((string) $body, true) ?: [];
    if (!empty($data['errors'])) {
        throw new RuntimeException('Wave: ' . ($data['errors'][0]['message'] ?? 'request failed'));
    }
    return $data['data'] ?? [];
}

function wave_check(array $result, string $what): array
{
    if (empty($result['didSucceed'])) {
        throw new RuntimeException("Wave could not $what: " . ($result['inputErrors'][0]['message'] ?? 'unknown error'));
    }
    return $result;
}

/** Creates (or reuses) the Wave customer, then creates, approves and emails a one-month invoice. */
function wave_send_invoice(array $t, string $plan): array
{
    $plans = plans();
    $product = (string) setting('wave_product_' . $plan, '');
    if ($product === '' || !isset($plans[$plan])) {
        throw new RuntimeException('This plan has no Wave product yet. Ask your service provider to add it.');
    }
    $business = (string) setting('wave_business_id');
    $email = owner_email($t);
    $customer = $t['wave_customer_id'];
    if (!$customer) {
        $r = wave_check(wave_gql('mutation ($input: CustomerCreateInput!) { customerCreate(input: $input) { didSucceed inputErrors { message } customer { id } } }',
            ['input' => ['businessId' => $business, 'name' => $t['name'], 'email' => $email]])['customerCreate'] ?? [], 'create the customer');
        $customer = $r['customer']['id'];
        update('tenants', ['wave_customer_id' => $customer], 'id = ?', [$t['id']]);
    }
    $start = gmdate('Y-m-d');
    $end = gmdate('Y-m-d', strtotime('+1 month'));
    $r = wave_check(wave_gql('mutation ($input: InvoiceCreateInput!) { invoiceCreate(input: $input) { didSucceed inputErrors { message } invoice { id viewUrl status } } }',
        ['input' => ['businessId' => $business, 'customerId' => $customer, 'status' => 'DRAFT',
            'memo' => cfg('app_name') . ' ' . $plans[$plan]['name'] . " plan, $start to $end",
            'items' => [['productId' => $product, 'quantity' => 1, 'unitPrice' => number_format($plans[$plan]['amount'], 2, '.', '')]]]])['invoiceCreate'] ?? [], 'create the invoice');
    $invoice = $r['invoice'];
    wave_check(wave_gql('mutation ($input: InvoiceApproveInput!) { invoiceApprove(input: $input) { didSucceed inputErrors { message } } }',
        ['input' => ['invoiceId' => $invoice['id']]])['invoiceApprove'] ?? [], 'approve the invoice');
    wave_check(wave_gql('mutation ($input: InvoiceSendInput!) { invoiceSend(input: $input) { didSucceed inputErrors { message } } }',
        ['input' => ['invoiceId' => $invoice['id'], 'to' => [$email], 'attachPDF' => true,
            'subject' => cfg('app_name') . ' subscription invoice', 'message' => 'Thank you for using ' . cfg('app_name') . '. Pay online with the link in this email.']])['invoiceSend'] ?? [], 'email the invoice');
    update('tenants', ['billing_provider' => 'wave', 'wave_invoice_id' => $invoice['id'], 'plan' => $plan], 'id = ?', [$t['id']]);
    record_payment(['tenant_id' => $t['id'], 'provider' => 'wave', 'external_id' => $invoice['id'], 'amount_cents' => (int) round($plans[$plan]['amount'] * 100),
        'status' => 'open', 'description' => $plans[$plan]['name'] . ' plan invoice', 'invoice_url' => $invoice['viewUrl'] ?? null,
        'period_start' => $start, 'period_end' => $end]);
    return $invoice;
}

/** Cron: mark paid Wave invoices, unlock portals, and raise the next invoice before renewal. */
function wave_sync(): array
{
    if (!wave_ready()) {
        return [];
    }
    $log = [];
    $GLOBALS['audit_system'] = true;
    foreach (rows("SELECT * FROM tenants WHERE billing_provider = 'wave'") as $t) {
        try {
            if ($t['wave_invoice_id']) {
                $inv = wave_gql('query ($b: ID!, $i: ID!) { business(id: $b) { invoice(id: $i) { id status amountPaid { value } } } }',
                    ['b' => setting('wave_business_id'), 'i' => $t['wave_invoice_id']])['business']['invoice'] ?? null;
                if ($inv && strtoupper((string) $inv['status']) === 'PAID') {
                    $p = row("SELECT * FROM payments WHERE provider = 'wave' AND external_id = ?", [$t['wave_invoice_id']]);
                    q("UPDATE payments SET status = 'paid', paid_at = ?, amount_cents = ? WHERE provider = 'wave' AND external_id = ?",
                        [gmdate('Y-m-d H:i:s'), (int) round((float) ($inv['amountPaid']['value'] ?? 0) * 100) ?: (int) ($p['amount_cents'] ?? 0), $t['wave_invoice_id']]);
                    update('tenants', ['status' => 'active', 'subscription_status' => 'active', 'wave_invoice_id' => null,
                        'current_period_end' => ($p['period_end'] ?? gmdate('Y-m-d', strtotime('+1 month'))) . ' 23:59:59'], 'id = ?', [$t['id']]);
                    audit('Wave invoice paid', $t['name'], true, 'Invoice ' . $t['wave_invoice_id']);
                    $log[] = $t['slug'] . ': Wave invoice paid';
                }
            } elseif ($t['status'] === 'active' && $t['current_period_end'] && strtotime($t['current_period_end']) < time() + 7 * 86400) {
                wave_send_invoice($t, $t['plan']);
                audit('Wave renewal invoice sent', $t['name'], true, 'Plan ' . $t['plan']);
                $log[] = $t['slug'] . ': renewal invoice sent';
            }
        } catch (Throwable $e) {
            audit('Wave sync', $t['name'], false, $e->getMessage());
            $log[] = $t['slug'] . ': Wave error ' . $e->getMessage();
        }
    }
    return $log;
}
