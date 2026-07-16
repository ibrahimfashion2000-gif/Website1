<?php
/**
 * process_order.php
 * -----------------------------------------------------------
 * Call this from your Orders page (AJAX button "Check & Process")
 * or automatically right after an order is created.
 *
 * POST/GET param: order_id
 * -----------------------------------------------------------
 */

session_start();
require_once __DIR__ . '/db.php';               // must expose $conn (mysqli)
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/courier_functions.php';

header('Content-Type: application/json; charset=utf-8');

$orderId = isset($_REQUEST['order_id']) ? (int) $_REQUEST['order_id'] : 0;

if ($orderId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'order_id is required']);
    exit;
}

$result = cl_process_order_success_rate($conn, $orderId);

echo json_encode(['ok' => true, 'result' => $result]);