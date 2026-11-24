<?php // Founder block: name and phone from /super → Prospects (outreach_name, outreach_phone); photo public/assets/img/founder.jpg if present ?>
<?php $sq = $sq ?? (lead_source() ? 'src=' . lead_source() : ''); ?>
  <?php $fName = trim((string) setting('outreach_name', '')); $fPhone = trim((string) setting('outreach_phone', '')); ?>
  <?php if ($fName !== ''): $fPhoto = is_file(PUBLIC_DIR . '/assets/img/founder.jpg'); ?>
  <section class="founder">
    <?php if ($fPhoto): ?><img src="<?= e(asset('img/founder.jpg')) ?>" alt="<?= e($fName) ?>" width="120" height="120"><?php else: ?><span class="founder-ini"><?= e(strtoupper(mb_substr($fName, 0, 1) . mb_substr((string) strrchr(' ' . $fName, ' '), 1, 1))) ?></span><?php endif; ?>
    <div>
      <h2>Hi, I'm <?= e(explode(' ', $fName)[0]) ?>.</h2>
      <p>I build <?= e(cfg('app_name')) ?> and I answer every email and call myself. Tell me how your front door works today and I'll set it up for you, free.</p>
      <p class="founder-contact"><?php if ($fPhone !== ''): ?><a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $fPhone)) ?>"><?= e($fPhone) ?></a> · <?php endif; ?><a href="mailto:<?= e(contact_email()) ?>"><?= e(contact_email()) ?></a> · <a href="<?= e(base_path()) ?>/demo<?= $sq ? '?' . $sq : '' ?>">Book a 10-minute demo</a></p>
    </div>
  </section>
  <?php endif; ?>
