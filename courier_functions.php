<?php
/**
 * courier_functions.php
 * -----------------------------------------------------------
 * Core library for the Courier & Logistics module.
 * Include this file after your existing db.php / config.php:
 *
 *   require_once __DIR__ . '/db.php';       // must expose $conn (mysqli)
 *   require_once __DIR__ . '/config.php';
 *   require_once __DIR__ . '/includes/courier_functions.php';
 *
 * Assumes $conn is a mysqli connection (adjust the 3 db calls below
 * if your project uses PDO instead).
 * -----------------------------------------------------------
 */

if (!function_exists('cl_get_setting')) {
    /**
     * Read one value from the `settings` key/value table.
     * Cached per-request so repeated calls don't hit the DB again.
     */
    function cl_get_setting(mysqli $conn, string $key, $default = null) {
        static $cache = [];
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }
        $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1");
        $stmt->bind_param('s', $key);
        $stmt->execute();
        $res = $stmt->get_result();
        $value = $default;
        if ($row = $res->fetch_assoc()) {
            $value = $row['setting_value'];
        }
        $stmt->close();
        $cache[$key] = $value;
        return $value;
    }
}

if (!function_exists('cl_curl_request')) {
    /**
     * Generic curl wrapper. Returns ['ok'=>bool,'http_code'=>int,'body'=>string,'json'=>array|null,'error'=>string|null]
     */
    function cl_curl_request(string $url, string $method = 'GET', array $headers = [], $payload = null, int $timeout = 15): array {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        if ($payload !== null) {
            $body = is_string($payload) ? $payload : json_encode($payload, JSON_UNESCAPED_UNICODE);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            if (!in_array('Content-Type: application/json', $headers)) {
                $headers[] = 'Content-Type: application/json';
            }
        }
        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        $body = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        $json = null;
        if ($body !== false && $body !== '') {
            $decoded = json_decode($body, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $json = $decoded;
            }
        }

        return [
            'ok'        => ($body !== false && $error === '' && $httpCode >= 200 && $httpCode < 300),
            'http_code' => $httpCode,
            'body'      => $body,
            'json'      => $json,
            'error'     => $error ?: null,
        ];
    }
}

/* =============================================================
 * POINT 1: Dynamic Success Rate & Auto-Hold Logic
 * ============================================================= */

if (!function_exists('cl_fetch_courier_success_rate')) {
    /**
     * Calls the courier provider's API to fetch the customer's success rate
     * (based on phone number / customer id, per your courier provider's docs).
     * Returns a float percentage, or null on failure.
     */
    function cl_fetch_courier_success_rate(mysqli $conn, array $order): ?float {
        $apiUrl = cl_get_setting($conn, 'courier_api_url');
        $apiKey = cl_get_setting($conn, 'courier_api_key');
        if (!$apiUrl || !$apiKey) {
            return null;
        }

        $query = http_build_query(['phone' => $order['customer_phone']]);
        $result = cl_curl_request(
            $apiUrl . '?' . $query,
            'GET',
            ['Authorization: Bearer ' . $apiKey]
        );

        if ($result['ok'] && isset($result['json']['success_rate'])) {
            return (float) $result['json']['success_rate'];
        }
        return null;
    }
}

if (!function_exists('cl_process_order_success_rate')) {
    /**
     * Main orchestrator for point 1 + 2.
     * Call this right after an order is placed / whenever you want to
     * (re)evaluate a customer against the courier's success rate.
     *
     * Returns array: ['status' => 'Confirmed'|'Hold', 'success_rate' => float|null, 'threshold' => float]
     */
    function cl_process_order_success_rate(mysqli $conn, int $orderId): array {
        $threshold = (float) cl_get_setting($conn, 'success_rate_threshold', 50);

        $stmt = $conn->prepare("SELECT id, customer_name, customer_phone, address, tracking_code FROM orders WHERE id = ? LIMIT 1");
        $stmt->bind_param('i', $orderId);
        $stmt->execute();
        $order = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$order) {
            return ['status' => 'error', 'success_rate' => null, 'threshold' => $threshold, 'message' => 'Order not found'];
        }

        $successRate = cl_fetch_courier_success_rate($conn, $order);

        if ($successRate !== null && $successRate >= $threshold) {
            $newStatus = 'Confirmed';
        } else {
            $newStatus = 'Hold';
        }

        // Persist result on the order
        $upd = $conn->prepare("UPDATE orders SET status = ?, courier_success_rate = ? WHERE id = ?");
        $upd->bind_param('sdi', $newStatus, $successRate, $orderId);
        $upd->execute();
        $upd->close();

        // Audit log
        $log = $conn->prepare("INSERT INTO courier_status_log (order_id, success_rate, threshold, result_status) VALUES (?,?,?,?)");
        $log->bind_param('idds', $orderId, $successRate, $threshold, $newStatus);
        $log->execute();
        $log->close();

        if ($newStatus === 'Confirmed') {
            // Allowed to call -> hand off to the universal AI Calling API (point 2)
            cl_trigger_ai_call($conn, array_merge($order, ['id' => $orderId]));
        }
        // If Hold -> calling is blocked. Do nothing further here.

        return ['status' => $newStatus, 'success_rate' => $successRate, 'threshold' => $threshold];
    }
}

/* =============================================================
 * POINT 2: Universal AI Calling API with Night Mode
 * ============================================================= */

if (!function_exists('cl_is_night_mode')) {
    /**
     * Compares current server time (H:i) against night_mode_start / night_mode_end
     * settings. Correctly handles ranges that cross midnight (e.g. 21:00 -> 08:00).
     */
    function cl_is_night_mode(mysqli $conn): bool {
        $start = cl_get_setting($conn, 'night_mode_start', '21:00');
        $end   = cl_get_setting($conn, 'night_mode_end', '08:00');
        $now   = date('H:i');

        if ($start === $end) {
            return false; // no window configured
        }

        if ($start < $end) {
            // Same-day window, e.g. 09:00 -> 18:00
            return ($now >= $start && $now < $end);
        }
        // Overnight window, e.g. 21:00 -> 08:00
        return ($now >= $start || $now < $end);
    }
}

if (!function_exists('cl_trigger_ai_call')) {
    /**
     * Universal AI Calling entry point. NEVER hardcode URL/key - always pulled
     * from `settings`. Decides day vs night automatically.
     */
    function cl_trigger_ai_call(mysqli $conn, array $order): array {
        if (cl_is_night_mode($conn)) {
            cl_queue_night_call($conn, $order);
            cl_send_night_mode_message($conn, $order);
            return ['mode' => 'night', 'queued' => true];
        }
        return cl_call_ai_api_now($conn, $order);
    }
}

if (!function_exists('cl_build_ai_payload')) {
    function cl_build_ai_payload(mysqli $conn, array $order): array {
        $customJson = cl_get_setting($conn, 'ai_custom_payload_json', '{}');
        $custom = json_decode($customJson, true);
        if (!is_array($custom)) {
            $custom = [];
        }
        // Merge order-specific fields into the admin-configured custom payload
        return array_merge($custom, [
            'order_id'       => $order['id'],
            'customer_name'  => $order['customer_name'] ?? '',
            'customer_phone' => $order['customer_phone'] ?? '',
            'address'        => $order['address'] ?? '',
        ]);
    }
}

if (!function_exists('cl_call_ai_api_now')) {
    function cl_call_ai_api_now(mysqli $conn, array $order): array {
        $apiUrl = cl_get_setting($conn, 'ai_api_url');
        $apiKey = cl_get_setting($conn, 'ai_api_key');
        if (!$apiUrl || !$apiKey) {
            return ['mode' => 'day', 'ok' => false, 'error' => 'AI API URL/Key not configured in settings'];
        }

        $payload = cl_build_ai_payload($conn, $order);
        $result = cl_curl_request($apiUrl, 'POST', ['Authorization: Bearer ' . $apiKey], $payload);

        return ['mode' => 'day', 'ok' => $result['ok'], 'response' => $result['json'] ?? $result['body'], 'error' => $result['error']];
    }
}

if (!function_exists('cl_queue_night_call')) {
    function cl_queue_night_call(mysqli $conn, array $order): void {
        $payload = json_encode(cl_build_ai_payload($conn, $order), JSON_UNESCAPED_UNICODE);
        $stmt = $conn->prepare("INSERT INTO calling_queue (order_id, phone, payload, status) VALUES (?,?,?,'pending')");
        $stmt->bind_param('iss', $order['id'], $order['customer_phone'], $payload);
        $stmt->execute();
        $stmt->close();
    }
}

if (!function_exists('cl_send_night_mode_message')) {
    /**
     * Sends the configured night-mode WhatsApp/SMS text via curl to whatever
     * gateway is configured in settings (sms_whatsapp_api_url/api_key).
     * Adjust the request shape to match your specific SMS/WhatsApp provider's API docs.
     */
    function cl_send_night_mode_message(mysqli $conn, array $order): array {
        $apiUrl   = cl_get_setting($conn, 'sms_whatsapp_api_url');
        $apiKey   = cl_get_setting($conn, 'sms_whatsapp_api_key');
        $template = cl_get_setting($conn, 'night_mode_message', '');
        $shopName = cl_get_setting($conn, 'shop_name', '');

        if (!$apiUrl || !$apiKey) {
            return ['ok' => false, 'error' => 'SMS/WhatsApp gateway not configured in settings'];
        }

        $message = str_replace(
            ['{customer_name}', '{shop_name}'],
            [$order['customer_name'] ?? '', $shopName],
            $template
        );

        $payload = [
            'to'      => $order['customer_phone'] ?? '',
            'message' => $message,
        ];

        $result = cl_curl_request($apiUrl, 'POST', ['Authorization: Bearer ' . $apiKey], $payload);
        return ['ok' => $result['ok'], 'response' => $result['json'] ?? $result['body'], 'error' => $result['error']];
    }
}

/* =============================================================
 * Night-queue flusher (used by cron/night_queue_worker.php)
 * Run this via a scheduled task once daytime starts, to process
 * anything that was queued overnight.
 * ============================================================= */
if (!function_exists('cl_flush_calling_queue')) {
    function cl_flush_calling_queue(mysqli $conn, int $limit = 50): array {
        if (cl_is_night_mode($conn)) {
            return ['processed' => 0, 'message' => 'Still night mode, skipping'];
        }

        $stmt = $conn->prepare("SELECT id, order_id, phone, payload FROM calling_queue WHERE status = 'pending' ORDER BY id ASC LIMIT ?");
        $stmt->bind_param('i', $limit);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $processed = 0;
        foreach ($rows as $row) {
            $orderStmt = $conn->prepare("SELECT id, customer_name, customer_phone, address FROM orders WHERE id = ? LIMIT 1");
            $orderStmt->bind_param('i', $row['order_id']);
            $orderStmt->execute();
            $order = $orderStmt->get_result()->fetch_assoc();
            $orderStmt->close();

            if (!$order) {
                $upd = $conn->prepare("UPDATE calling_queue SET status='failed', last_error='order not found', processed_at=NOW() WHERE id=?");
                $upd->bind_param('i', $row['id']);
                $upd->execute();
                $upd->close();
                continue;
            }

            $result = cl_call_ai_api_now($conn, $order);
            $status = $result['ok'] ? 'processed' : 'failed';
            $error  = $result['ok'] ? null : ($result['error'] ?? 'unknown error');

            $upd = $conn->prepare("UPDATE calling_queue SET status=?, last_error=?, attempts = attempts + 1, processed_at=NOW() WHERE id=?");
            $upd->bind_param('ssi', $status, $error, $row['id']);
            $upd->execute();
            $upd->close();

            $processed++;
        }

        return ['processed' => $processed];
    }
}