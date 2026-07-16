<?php
/**
 * capture_incomplete.php
 *
 * Lightweight AJAX endpoint hit by the checkout form (e.g. on blur/keyup
 * of the phone field, or on an interval) to capture leads in real time,
 * before the customer finishes or abandons checkout.
 *
 * Expects POST: name, phone, product_id, address (optional)
 * Responds: JSON { success: bool, message: string }
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$name       = trim((string) ($_POST['name'] ?? ''));
$phoneRaw   = trim((string) ($_POST['phone'] ?? ''));
$product_id = filter_var($_POST['product_id'] ?? null, FILTER_VALIDATE_INT);
$address    = trim((string) ($_POST['address'] ?? ''));

// Phone + product are the minimum needed to be able to follow up later.
if ($phoneRaw === '' || !$product_id) {
    echo json_encode(['success' => false, 'message' => 'Phone and product_id are required']);
    exit;
}

// Keep only digits and a leading +
$phone = preg_replace('/[^0-9+]/', '', $phoneRaw);

if (strlen($phone) < 6) {
    echo json_encode(['success' => false, 'message' => 'Invalid phone number']);
    exit;
}

try {
    // Snapshot product name/price so incomplete_orders keeps accurate
    // history even if the product's price changes later.
    $stmt = $pdo->prepare("SELECT name, price FROM products WHERE id = ? LIMIT 1");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        echo json_encode(['success' => false, 'message' => 'Invalid product']);
        exit;
    }

    $session_id = session_id() ?: null;

    // Upsert on (phone, product_id): repeated pings from the same visitor
    // filling out the same checkout update one row instead of duplicating.
    // Note: this resets sms_sent back to 0, which is intentional — a
    // customer actively re-engaging with the form deserves a fresh window.
    $sql = "INSERT INTO incomplete_orders
                (name, phone, product_id, product_name, product_price, address, session_id, status, created_at, updated_at)
            VALUES
                (:name, :phone, :product_id, :product_name, :product_price, :address, :session_id, 'pending', NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                name          = VALUES(name),
                product_name  = VALUES(product_name),
                product_price = VALUES(product_price),
                address       = VALUES(address),
                session_id    = VALUES(session_id),
                status        = 'pending',
                sms_sent      = 0,
                updated_at    = NOW()";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':name'          => $name !== '' ? $name : null,
        ':phone'         => $phone,
        ':product_id'    => $product_id,
        ':product_name'  => $product['name'],
        ':product_price' => $product['price'],
        ':address'       => $address !== '' ? $address : null,
        ':session_id'    => $session_id,
    ]);

    echo json_encode(['success' => true, 'message' => 'Captured']);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
    // error_log($e->getMessage()); // log internally — never expose raw DB errors to the client
}