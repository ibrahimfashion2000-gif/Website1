<?php
/**
 * scan_handler.php
 * -----------------------------------------------------------
 * Endpoint hit by the barcode scanner page (item 24: "Send Courier by Barcode")
 * and also usable for a manual status-override button in the admin UI.
 *
 * Expected POST params:
 *   code        -> the scanned barcode value (order id or tracking code)
 *   new_status  -> optional, defaults to 'in_transit'
 *   manual      -> optional "1" when this is a manual override (no physical scan)
 *
 * Session requirement:
 *   $_SESSION['user_permissions'] must be an array containing 'courier_manage'
 *   for normal scans, and additionally 'manual_override' for manual overrides.
 * -----------------------------------------------------------
 */

session_start();
require_once __DIR__ . '/db.php';               // must expose $conn (mysqli)
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');

function cl_has_permission(string $perm): bool {
    return isset($_SESSION['user_permissions'])
        && is_array($_SESSION['user_permissions'])
        && in_array($perm, $_SESSION['user_permissions'], true);
}

// ---- Auth check --------------------------------------------------
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Not logged in']);
    exit;
}

$isManual = isset($_POST['manual']) && $_POST['manual'] == '1';

if ($isManual) {
    // Manual status change without a physical scan requires an extra permission
    if (!cl_has_permission('manual_override')) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'You do not have permission for manual override']);
        exit;
    }
} else {
    if (!cl_has_permission('courier_manage')) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'You do not have permission to manage courier status']);
        exit;
    }
}

// ---- Input ---------------------------------------------------------
$code      = trim($_POST['code'] ?? '');
$newStatus = trim($_POST['new_status'] ?? 'in_transit');

$allowedStatuses = ['in_courier', 'in_transit', 'hand_delivery', 'hand_delivery_completed', 'others', 'return_request', 'return_collected'];
if (!in_array($newStatus, $allowedStatuses, true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid new_status value']);
    exit;
}

if ($code === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'code is required']);
    exit;
}

// ---- Find the order by tracking_code OR raw numeric order id -------
if (ctype_digit($code)) {
    $stmt = $conn->prepare("SELECT id, courier_status FROM orders WHERE id = ? OR tracking_code = ? LIMIT 1");
    $stmt->bind_param('is', $code, $code);
} else {
    $stmt = $conn->prepare("SELECT id, courier_status FROM orders WHERE tracking_code = ? LIMIT 1");
    $stmt->bind_param('s', $code);
}
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'No matching order/tracking code found']);
    exit;
}

$oldStatus = $order['courier_status'] ?? null;

// ---- Update status ---------------------------------------------------
$upd = $conn->prepare("UPDATE orders SET courier_status = ? WHERE id = ?");
$upd->bind_param('si', $newStatus, $order['id']);
$upd->execute();
$upd->close();

// ---- Audit log ---------------------------------------------------------
$log = $conn->prepare("INSERT INTO scan_log (order_id, scanned_code, scanned_by, method, old_status, new_status) VALUES (?,?,?,?,?,?)");
$method = $isManual ? 'manual' : 'scan';
$log->bind_param('isisss', $order['id'], $code, $_SESSION['user_id'], $method, $oldStatus, $newStatus);
$log->execute();
$log->close();

echo json_encode([
    'ok'         => true,
    'order_id'   => $order['id'],
    'old_status' => $oldStatus,
    'new_status' => $newStatus,
    'method'     => $method,
]);