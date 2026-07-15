<?php
/**
 * Legacy standalone reports entrypoint.
 * Redirect to the admin reports route to keep a single source of truth.
 */

require_once __DIR__ . '/../../config/config.php';

$query = $_GET;
$query['page'] = 'reports';

$target = APP_URL . '/admin/?' . http_build_query($query);
header('Location: ' . $target, true, 302);
exit;
