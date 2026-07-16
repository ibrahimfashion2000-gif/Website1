<?php
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // এখন ডেলিভারি চার্জ পোস্ট ডাটা থেকেই আসছে, তাই ইনপুট ফিল্ডটি hidden রাখার প্রয়োজন নেই
    $stmt = $pdo->prepare("INSERT INTO orders (order_date, ref_number, customer_name, mobile_number, address, delivery_area, delivery_charge, discount, total_amount, customer_note) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $_POST['order_date'], $_POST['ref_number'], $_POST['customer_name'], $_POST['mobile_number'], 
        $_POST['address'], $_POST['delivery_area'], $_POST['delivery_charge'], $_POST['discount'], 
        $_POST['total_amount'], $_POST['customer_note']
    ]);
    header("Location: admin_orders.php");
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-8">
    <div class="max-w-2xl mx-auto bg-white p-6 rounded shadow">
        <h2 class="text-2xl font-bold mb-4">Add New Order</h2>
        <form method="POST" id="orderForm">
            <input type="date" name="order_date" class="w-full p-2 mb-2 border" required>
            <input type="text" name="ref_number" placeholder="Ref Number" class="w-full p-2 mb-2 border">
            <input type="text" name="customer_name" placeholder="Customer Name" class="w-full p-2 mb-2 border" required>
            <input type="text" name="mobile_number" placeholder="Mobile Number" class="w-full p-2 mb-2 border" required>
            
            <label>Delivery Area:</label>
            <select name="delivery_area" id="delivery_area" onchange="calculateTotal()" class="w-full p-2 mb-2 border" required>
                <option value="Inside Dhaka" data-charge="60">Inside Dhaka (60 TK)</option>
                <option value="Sub-Urban" data-charge="100">Sub-Urban (100 TK)</option>
                <option value="Outside Dhaka" data-charge="150">Outside Dhaka (150 TK)</option>
            </select>

            <input type="number" name="product_price" id="product_price" placeholder="Product Price" class="w-full p-2 mb-2 border" oninput="calculateTotal()" required>
            <input type="hidden" name="delivery_charge" id="delivery_charge">
            <input type="number" name="discount" id="discount" placeholder="Discount" class="w-full p-2 mb-2 border" oninput="calculateTotal()">
            
            <p class="font-bold text-lg">Total Amount: <span id="total_display">0</span> TK</p>
            <input type="hidden" name="total_amount" id="total_amount">
            
            <textarea name="customer_note" placeholder="Note" class="w-full p-2 mb-2 border"></textarea>
            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded">Save Order</button>
        </form>
    </div>

    <script>
    function calculateTotal() {
        let price = parseFloat(document.getElementById('product_price').value) || 0;
        let discount = parseFloat(document.getElementById('discount').value) || 0;
        let areaSelect = document.getElementById('delivery_area');
        let charge = parseFloat(areaSelect.options[areaSelect.selectedIndex].getAttribute('data-charge'));
        
        let total = price + charge - discount;
        
        document.getElementById('delivery_charge').value = charge;
        document.getElementById('total_amount').value = total;
        document.getElementById('total_display').innerText = total;
}

function updateDeliveryCharge() {
    var areaSelect = document.getElementById('delivery_area');
    var charge = parseFloat(areaSelect.options[areaSelect.selectedIndex].getAttribute('data-charge')) || 0; 
    
    document.getElementById('delivery_charge').value = charge;
    
    calculateTotal();
}

function calculateGrandTotal() {
    var subTotal = parseFloat(document.getElementById('sub_total').value) || 0;
    var discount = parseFloat(document.getElementById('discount').value) || 0;
    var deliveryCharge = parseFloat(document.getElementById('delivery_charge').value) || 0;

    var grandTotal = subTotal - discount + deliveryCharge;
    document.getElementById('grand_total').innerText = grandTotal + ' TK';
}
<script>
document.addEventListener("DOMContentLoaded", function() {
    // আপনার চেকআউট ফর্মের ইনপুট ফিল্ডের ID বা Name অনুযায়ী এগুলো পরিবর্তন করুন
    const nameInput = document.querySelector('input[name="billing_name"]'); 
    const phoneInput = document.querySelector('input[name="billing_phone"]');
    const productInput = document.querySelector('input[name="product_id"]'); // হিডেন ফিল্ড বা ভ্যালু

    let timeout = null;

    function captureData() {
        const name = nameInput ? nameInput.value : '';
        const phone = phoneInput ? phoneInput.value : '';
        const productId = productInput ? productInput.value : '';

        // অন্তত ফোন নম্বরটি টাইপ করা শুরু করলে আমরা ডেটা ব্যাকএন্ডে পাঠাবো
        if (phone.length >= 5) {
            const formData = new FormData();
            formData.append('name', name);
            formData.append('phone', phone);
            formData.append('product_id', productId);

            // ব্যাকগ্রাউন্ডে capture_incomplete.php ফাইলে ডেটা পাঠানো
            fetch('capture_incomplete.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => console.log("Data captured:", data))
            .catch(error => console.error("Error capturing data:", error));
        }
    }

    // কাস্টমার টাইপ করা বন্ধ করার ২ সেকেন্ড পর অটোমেটিক সেভ হবে (Debounce Logic)
    if (nameInput) {
        nameInput.addEventListener('keyup', function() {
            clearTimeout(timeout);
            timeout = setTimeout(captureData, 2000);
        });
    }

    if (phoneInput) {
        phoneInput.addEventListener('keyup', function() {
            clearTimeout(timeout);
            timeout = setTimeout(captureData, 2000);
        });
    }
});
</script>
</body>
</html>