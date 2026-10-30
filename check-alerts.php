<?php
/**
 * Run the alert check now (same code as the plugin cron). POST only: Unraid's
 * local_prepend.php has already checked csrf_token for every POST. The Main page no
 * longer calls this; alerts run from the cron.
 */
header('Content-Type: application/json');
header('Cache-Control: no-store');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['ran' => false, 'reason' => 'POST required']);
    exit;
}

require_once __DIR__ . '/include/sg-alerts.php';
echo json_encode(sg_alerts_run());
