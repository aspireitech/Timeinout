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
    foreach ($routes as $route) {
        [$methods, $pattern, $handler] = $route;
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
            if (isset($route[3])) { // 4th item = audit label: log this change automatically
                audit_request($route[3]);
            }
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
        ['GET|POST', '/super/verify',        'super_verify'],
        ['GET',      '/super/logout',        'super_logout'],
        ['GET',      '/super',               'super_tenants'],
        ['POST',     '/super/tenants/{id}',  'super_update_tenant', 'Changed subscriber plan/status'],
        ['POST',     '/super/demo',          'super_seed_demo',     'Rebuilt demo portal'],
        ['GET|POST', '/super/settings',      'super_settings'],
        ['GET',      '/super/audit',         'super_audit'],
        ['GET',      '/cron',                'web_cron'],
    ]);
}

function tenant_routes(string $path): void
{
    // A 4th item names the change for the audit log; those posts are logged automatically.
    dispatch($path, [
        // Kiosk (the screen students, parents and teachers use)
        ['GET',      '/',                          'kiosk_home'],
        ['GET|POST', '/login',                     'login_page'],
        ['GET|POST', '/login/verify',              'login_verify'],
        ['GET',      '/logout',                    'logout_action'],
        ['GET',      '/api/search',                'api_search'],
        ['GET',      '/api/guardians',             'api_guardians'],
        ['GET',      '/api/status',                'api_status'],
        ['POST',     '/api/record',                'api_record'],
        // Admin portal (owners and admins only)
        ['GET',      '/admin',                     'admin_dashboard'],
        ['GET',      '/admin/students',            'admin_students'],
        ['POST',     '/admin/students',            'admin_student_save',    'Added student'],
        ['GET',      '/admin/students/{id}',       'admin_student_edit'],
        ['POST',     '/admin/students/{id}',       'admin_student_save',    'Edited student'],
        ['POST',     '/admin/students/{id}/delete', 'admin_student_delete', 'Deleted student'],
        ['POST',     '/admin/students/{id}/guardians', 'admin_guardian_add', 'Added guardian'],
        ['POST',     '/admin/guardians/{id}/delete', 'admin_guardian_delete', 'Removed guardian'],
        ['GET',      '/admin/teachers',            'admin_teachers'],
        ['POST',     '/admin/teachers',            'admin_teacher_save',    'Added teacher'],
        ['GET',      '/admin/teachers/{id}',       'admin_teacher_edit'],
        ['POST',     '/admin/teachers/{id}',       'admin_teacher_save',    'Edited teacher'],
        ['POST',     '/admin/teachers/{id}/delete', 'admin_teacher_delete', 'Deleted teacher'],
        ['GET',      '/admin/materials',           'admin_materials'],
        ['POST',     '/admin/materials',           'admin_materials',       'Changed materials'],
        ['POST',     '/admin/materials/{id}/delete', 'admin_material_delete', 'Deleted material'],
        ['GET',      '/admin/import',              'admin_import'],
        ['POST',     '/admin/import',              'admin_import',          'Imported CSV'],
        ['GET',      '/admin/import/sample-students.csv', 'admin_sample_students'],
        ['GET',      '/admin/import/sample-teachers.csv', 'admin_sample_teachers'],
        ['GET',      '/admin/attendance',          'admin_attendance'],
        ['GET',      '/admin/logs',                'admin_logs'],
        ['GET',      '/admin/logs/export',         'admin_logs_export'],
        ['POST',     '/admin/logs/{id}/delete',    'admin_log_delete',      'Deleted attendance entry'],
        ['GET',      '/admin/reports',             'admin_reports'],
        ['GET',      '/admin/reports/download',    'admin_report_download'],
        ['POST',     '/admin/reports/settings',    'admin_report_settings', 'Changed report schedule'],
        ['POST',     '/admin/reports/send',        'admin_report_send'],
        ['GET',      '/admin/settings',            'admin_settings'],
        ['POST',     '/admin/settings',            'admin_settings',        'Changed settings'],
        ['GET',      '/admin/users',               'admin_users'],
        ['POST',     '/admin/users',               'admin_user_save',       'Added user'],
        ['POST',     '/admin/users/{id}/delete',   'admin_user_delete',     'Removed user'],
        ['POST',     '/admin/users/{id}/password', 'admin_user_password',   'Reset user password'],
        ['POST',     '/admin/password',            'admin_password',        'Changed own password'],
        ['GET',      '/admin/audit',               'admin_audit'],
    ]);
}
