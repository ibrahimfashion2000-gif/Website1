<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}
require_once 'db.php';

if (($_SERVER['REQUEST_METHOD'] === 'POST') && !empty($_POST['order_ids'])) {
    $newStatus = $_POST['new_status'];
    $ids = $_POST['order_ids'];

    // প্রতিটি সিলেক্ট করা অর্ডারের তথ্য লুপ চালাবে
    foreach ($ids as $id) {
        $stmt = $pdo->prepare("UPDATE orders SET delivery_status = ? WHERE id = ?");
        $stmt->execute([$newStatus, $id]);

        // স্ট্যাটাস আপডেটের পর লগ যোগ করুন
        $logStmt = $pdo->prepare("INSERT INTO order_logs (order_id, action) VALUES (?, ?)");
        $logStmt->execute([$id, "Status changed to: " . $newStatus]);

        // হিস্ট্রিতে লগ যোগ
        $log = $pdo->prepare("INSERT INTO order_history (order_id, action_type, user_name) VALUES (?, ?, ?)");
        $log->execute([$id, "Bulk Updated to " . $newStatus, "Admin"]);

        // ========================================================
        // ২৭ নম্বর লাইন থেকে নতুন কোড শুরু (লুপের ভেতরেই থাকবে)
        // ========================================================
        $customerStmt = $pdo->prepare("SELECT customer_name, customer_email, customer_phone FROM orders WHERE id = ?");
        $customerStmt->execute([$id]);
        $customer = $customerStmt->fetch(PDO::FETCH_ASSOC);

        if ($customer) {
            $customerName = htmlspecialchars($customer['customer_name']);
            
            // ১. ইমেইল নোটিফিকেশন
            if (!empty($customer['customer_email'])) {
                $to = $customer['customer_email'];
                $subject = "Your Order Status Updated - #" . $id;
                $message = "<html><body><p>Hello " . $customerName . ",</p><p>Your order <strong>#" . $id . "</strong> status has been updated to: <strong>" . htmlspecialchars($newStatus) . "</strong>.</p></body></html>";
                $headers = "MIME-Version: 1.0\r\nContent-type:text/html;charset=UTF-8\r\nFrom: <no-reply@yourdomain.com>\r\n";
                @mail($to, $subject, $message, $headers);
            }

            // ২. হোয়াটসঅ্যাপ নোটিফিকেশন
            if (!empty($customer['customer_phone'])) {
                $phone = trim($customer['customer_phone']); 
                $whatsappMessage = "Hello " . $customerName . ",\n\nYour order *" . $id . "* status has been updated to: *" . $newStatus . "*.\n\nThank you!";
                $instanceId = "instanceXXXXX"; // আপনার API Instance ID এখানে দিন
                $apiToken = "your_api_token_here"; // আপনার API Token এখানে দিন

                $params = array('token' => $apiToken, 'to' => $phone, 'body' => $whatsappMessage);
                $curl = curl_init();
                curl_setopt_array($curl, array(
                    CURLOPT_URL => "https://api.ultramsg.com/" . $instanceId . "/messages/chat",
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_CUSTOMREQUEST => "POST",
                    CURLOPT_POSTFIELDS => http_build_query($params),
                    CURLOPT_HTTPHEADER => array("content-type: application/x-www-form-urlencoded"),
                ));
                curl_exec($curl);
                curl_close($curl);
            }
        }
        // ========================================================
        // নতুন কোড শেষ
        // ========================================================
    } // এই ব্র্যাকেটটি দিয়ে foreach লুপ শেষ হচ্ছে

    header("Location: admin_orders.php?status=updated");
    exit;
}
?>