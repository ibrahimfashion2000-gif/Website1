<?php
/**
 * bulk_invoice_generator.php
 * -----------------------------------------------------------
 * Item 23: "Bulk Print" — generates a single print-ready PDF containing
 * one invoice/label per selected order, with correct Bengali rendering
 * for customer name & address.
 *
 * SETUP (one-time):
 *   1. composer require dompdf/dompdf   (run inside your project root)
 *   2. Put SolaimanLipi.ttf and/or Kalpurush.ttf in: /fonts/
 *      (download the actual .ttf files yourself - they are not bundled here)
 *   3. Make sure /storage/dompdf_fonts/ is writable by PHP (dompdf caches
 *      font metrics there after registerFont()).
 *
 * USAGE:
 *   bulk_invoice_generator.php?order_ids=12,13,14
 *   (order_ids can also come from a POST array of checkboxes: order_ids[])
 * -----------------------------------------------------------
 */

require_once __DIR__ . '/db.php';                 // must expose $conn (mysqli)
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/vendor/autoload.php';    // composer autoload for dompdf

use Dompdf\Dompdf;
use Dompdf\Options;

// ---------------------------------------------------------------
// 1. Collect selected order IDs
// ---------------------------------------------------------------
$orderIds = [];
if (!empty($_REQUEST['order_ids'])) {
    if (is_array($_REQUEST['order_ids'])) {
        $orderIds = array_map('intval', $_REQUEST['order_ids']);
    } else {
        $orderIds = array_map('intval', explode(',', $_REQUEST['order_ids']));
    }
}
$orderIds = array_filter($orderIds, fn($id) => $id > 0);

if (empty($orderIds)) {
    http_response_code(400);
    echo 'No order_ids supplied.';
    exit;
}

// ---------------------------------------------------------------
// 2. Fetch orders
// ---------------------------------------------------------------
$placeholders = implode(',', array_fill(0, count($orderIds), '?'));
$types = str_repeat('i', count($orderIds));
$stmt = $conn->prepare("SELECT id, customer_name, customer_phone, address, tracking_code, status
                         FROM orders WHERE id IN ($placeholders)");
$stmt->bind_param($types, ...$orderIds);
$stmt->execute();
$orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if (empty($orders)) {
    http_response_code(404);
    echo 'No matching orders found.';
    exit;
}

// ---------------------------------------------------------------
// 3. Configure Dompdf + Bengali fonts
// ---------------------------------------------------------------
$fontDir      = __DIR__ . '/fonts/';           // where the .ttf files live
$fontCacheDir = __DIR__ . '/storage/dompdf_fonts/';
if (!is_dir($fontCacheDir)) {
    mkdir($fontCacheDir, 0775, true);
}

$options = new Options();
$options->set('isRemoteEnabled', true);        // allow loading local images if used
$options->set('isHtml5ParserEnabled', true);
$options->set('fontDir', $fontCacheDir);
$options->set('fontCache', $fontCacheDir);
$options->set('defaultFont', 'SolaimanLipi');  // fallback font for the whole document

$dompdf = new Dompdf($options);

// Register Bengali fonts by pointing dompdf at the raw .ttf files.
// This must run before Dompdf::loadHtml(). registerFont() caches metrics into
// $fontCacheDir, so this only needs to fully re-run once per font (dompdf will
// reuse the cache after that).
$fontMetrics = $dompdf->getFontMetrics();

if (file_exists($fontDir . 'SolaimanLipi.ttf')) {
    $fontMetrics->registerFont(
        ['family' => 'SolaimanLipi', 'style' => 'normal', 'weight' => 'normal'],
        $fontDir . 'SolaimanLipi.ttf'
    );
}
if (file_exists($fontDir . 'Kalpurush.ttf')) {
    $fontMetrics->registerFont(
        ['family' => 'Kalpurush', 'style' => 'normal', 'weight' => 'normal'],
        $fontDir . 'Kalpurush.ttf'
    );
}

// ---------------------------------------------------------------
// 4. Build the HTML (one label/invoice block per order, page-broken)
// ---------------------------------------------------------------
function cl_esc(string $v): string {
    return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
}

$shopName = 'YourBrand'; // Optionally pull from settings table

$html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>
    /* Bengali font family names below MUST match the family passed to registerFont() above */
    @font-face { font-family: "SolaimanLipi"; src: url("' . $fontDir . 'SolaimanLipi.ttf"); }
    @font-face { font-family: "Kalpurush"; src: url("' . $fontDir . 'Kalpurush.ttf"); }

    body { font-family: "SolaimanLipi", sans-serif; font-size: 13px; }
    .label { page-break-after: always; border: 1px solid #333; padding: 16px; }
    .label:last-child { page-break-after: auto; }
    .shop-name { font-size: 18px; font-weight: bold; margin-bottom: 8px; }
    .row { margin-bottom: 4px; }
    .row .lbl { font-weight: bold; display: inline-block; width: 110px; }
    .tracking { font-size: 20px; font-weight: bold; margin-top: 12px; letter-spacing: 1px; }
    </style></head><body>';

foreach ($orders as $order) {
    $html .= '<div class="label">';
    $html .= '<div class="shop-name">' . cl_esc($shopName) . '</div>';
    $html .= '<div class="row"><span class="lbl">Order ID:</span> ' . cl_esc((string)$order['id']) . '</div>';
    $html .= '<div class="row"><span class="lbl">Name:</span> ' . cl_esc($order['customer_name'] ?? '') . '</div>';
    $html .= '<div class="row"><span class="lbl">Phone:</span> ' . cl_esc($order['customer_phone'] ?? '') . '</div>';
    $html .= '<div class="row"><span class="lbl">Address:</span> ' . cl_esc($order['address'] ?? '') . '</div>';
    $html .= '<div class="row"><span class="lbl">Status:</span> ' . cl_esc($order['status'] ?? '') . '</div>';
    $html .= '<div class="tracking">' . cl_esc($order['tracking_code'] ?? '') . '</div>';
    $html .= '</div>';
}

$html .= '</body></html>';

// ---------------------------------------------------------------
// 5. Render & stream to the browser (opens print dialog / downloads)
// ---------------------------------------------------------------
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A6', 'portrait'); // typical courier label size; use 'A4' for full invoices
$dompdf->render();
$dompdf->stream('bulk_labels_' . date('Ymd_His') . '.pdf', ['Attachment' => false]);