<?php

require APP_DIR . '/controllers/platform.php';
require APP_DIR . '/controllers/kiosk.php';
require APP_DIR . '/controllers/api.php';
require APP_DIR . '/controllers/admin.php';

/**
 * Tiny router. Patterns like /admin/students/{id} pass `id` as a named argument.
 * Every POST is CSRF-checked here so no handler can forget it.
 */
function dispatch(string $path, array $routes): void
{
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $allowed = false;
    foreach ($routes as [$methods, $pattern, $handler]) {
        $re = '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>[0-9]+)', $pattern) . '$#';
        if (!preg_match($re, $path, $m)) {
            continue;
        }
        $allowed = true;
        if (!in_array($method, explode('|', $methods), true)) {
            continue;
        }
        if ($method === 'POST') {
            csrf_check();
        }
        $args = array_map('intval', array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY));
        $handler(...$args);
        return;
    }
    if ($allowed) {
        http_response_code(405);
        exit('Method not allowed');
    }
    not_found();
}

function platform_routes(string $path): void
{
    dispatch($path, [
        ['GET',      '/',                    'platform_home'],
        ['GET|POST', '/signup',              'platform_signup'],
        ['GET',      '/check-address',       'platform_check_slug'],
        ['GET|POST', '/super/login',         'super_login'],
        ['GET',      '/super/logout',        'super_logout'],
        ['GET',      '/super',               'super_tenants'],
        ['POST',     '/super/tenants/{id}',  'super_update_tenant'],
        ['POST',     '/super/demo',          'super_seed_demo'],
        ['GET',      '/cron',                'web_cron'],
    ]);
}

function tenant_routes(string $path): void
{
    dispatch($path, [
        // Kiosk (the screen students, parents and teachers use)
        ['GET',      '/',                          'kiosk_home'],
        ['GET|POST', '/login',                     'login_page'],
        ['GET',      '/logout',                    'logout_action'],
        ['GET',      '/api/search',                'api_search'],
        ['GET',      '/api/guardians',             'api_guardians'],
        ['GET',      '/api/status',                'api_status'],
        ['POST',     '/api/record',                'api_record'],
        // Admin portal
        ['GET',      '/admin',                     'admin_dashboard'],
        ['GET',      '/admin/students',            'admin_students'],
        ['POST',     '/admin/students',            'admin_student_save'],
        ['GET',      '/admin/students/{id}',       'admin_student_edit'],
        ['POST',     '/admin/students/{id}',       'admin_student_save'],
        ['POST',     '/admin/students/{id}/delete', 'admin_student_delete'],
        ['POST',     '/admin/students/{id}/guardians', 'admin_guardian_add'],
        ['POST',     '/admin/guardians/{id}/delete', 'admin_guardian_delete'],
        ['GET',      '/admin/teachers',            'admin_teachers'],
        ['POST',     '/admin/teachers',            'admin_teacher_save'],
        ['GET',      '/admin/teachers/{id}',       'admin_teacher_edit'],
        ['POST',     '/admin/teachers/{id}',       'admin_teacher_save'],
        ['POST',     '/admin/teachers/{id}/delete', 'admin_teacher_delete'],
        ['GET|POST', '/admin/materials',           'admin_materials'],
        ['POST',     '/admin/materials/{id}/delete', 'admin_material_delete'],
        ['GET|POST', '/admin/import',              'admin_import'],
        ['GET',      '/admin/import/sample-students.csv', 'admin_sample_students'],
        ['GET',      '/admin/import/sample-teachers.csv', 'admin_sample_teachers'],
        ['GET',      '/admin/logs',                'admin_logs'],
        ['GET',      '/admin/logs/export',         'admin_logs_export'],
        ['POST',     '/admin/logs/{id}/delete',    'admin_log_delete'],
        ['GET',      '/admin/reports',             'admin_reports'],
        ['POST',     '/admin/reports/settings',    'admin_report_settings'],
        ['POST',     '/admin/reports/send',        'admin_report_send'],
        ['GET|POST', '/admin/settings',            'admin_settings'],
        ['GET',      '/admin/users',               'admin_users'],
        ['POST',     '/admin/users',               'admin_user_save'],
        ['POST',     '/admin/users/{id}/delete',   'admin_user_delete'],
        ['POST',     '/admin/password',            'admin_password'],
    ]);
}
