<?php
/**
 * includes/profit_calculator.php
 * Ads Analytics Module — Core Profit/CPO Engine
 * Place in: /htdocs/automagic-erp/includes/profit_calculator.php
 * Requires: $pdo (PDO) from db.php, $dollar_rate from config.php
 * এই ফাইল index.php/process_order.php/checkout.php থেকে require হবে।
 */

if (!isset($pdo) || !($pdo instanceof PDO)) {
    // db.php আগে require করা হয়নি — নিরাপত্তার জন্য বন্ধ করে দিচ্ছি
    return;
}

/**
 * নির্দিষ্ট তারিখের জন্য কার্যকর ডলার রেট বের করা।
 * dollar_rate_history টেবিলে এন্ট্রি থাকলে সেটা, না থাকলে config.php এর ডিফল্ট।
 */
function get_dollar_rate_for_date(PDO $pdo, string $date): float {
    global $dollar_rate;
    try {
        $stmt = $pdo->prepare(
            "SELECT rate_bdt FROM dollar_rate_history 
             WHERE effective_date <= :date ORDER BY effective_date DESC LIMIT 1"
        );
        $stmt->execute(['date' => $date]);
        $rate = $stmt->fetchColumn();
        if ($rate !== false && $rate !== null) {
            return (float) $rate;
        }
    } catch (PDOException $e) {
        // টেবিল না থাকলে/এরর হলে নিচের ডিফল্টে ফলব্যাক
    }
    return (float) ($dollar_rate ?? 130);
}

/**
 * GLOBAL Daily CPO = (সেদিনের মোট Ad Spend USD * রেট) / সেদিনের মোট অর্ডার সংখ্যা
 */
function calculate_global_daily_cpo(PDO $pdo, string $date): float {
    $rate = get_dollar_rate_for_date($pdo, $date);

    try {
        $stmt = $pdo->prepare(
            "SELECT COALESCE(SUM(spend_usd),0) FROM daily_ad_spend WHERE `date` = :date"
        );
        $stmt->execute(['date' => $date]);
        $spend_usd = (float) $stmt->fetchColumn();
    } catch (PDOException $e) {
        $spend_usd = 0.0;
    }

    try {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM orders WHERE DATE(created_at) = :date"
        );
        $stmt->execute(['date' => $date]);
        $total_orders = (int) $stmt->fetchColumn();
    } catch (PDOException $e) {
        $total_orders = 0;
    }

    if ($total_orders === 0) {
        return 0.0;
    }
    return round(($spend_usd * $rate) / $total_orders, 2);
}

/**
 * CAMPAIGN-SPECIFIC CPO = (ক্যাম্পেইন Spend USD * রেট) / সেই ক্যাম্পেইন থেকে আসা অর্ডার সংখ্যা
 */
function calculate_campaign_cpo(PDO $pdo, string $date, string $campaign_id): float {
    $rate = get_dollar_rate_for_date($pdo, $date);

    try {
        $stmt = $pdo->prepare(
            "SELECT COALESCE(SUM(spend_usd),0) FROM daily_ad_spend 
             WHERE `date` = :date AND campaign_id = :cid"
        );
        $stmt->execute(['date' => $date, 'cid' => $campaign_id]);
        $spend_usd = (float) $stmt->fetchColumn();
    } catch (PDOException $e) {
        $spend_usd = 0.0;
    }

    try {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM orders 
             WHERE DATE(created_at) = :date AND campaign_id = :cid"
        );
        $stmt->execute(['date' => $date, 'cid' => $campaign_id]);
        $orders = (int) $stmt->fetchColumn();
    } catch (PDOException $e) {
        $orders = 0;
    }

    if ($orders === 0) {
        // এই ক্যাম্পেইন থেকে এখনো কোনো অর্ডার আসেনি — গ্লোবাল CPO ব্যবহার হবে
        return calculate_global_daily_cpo($pdo, $date);
    }
    return round(($spend_usd * $rate) / $orders, 2);
}

/**
 * অর্ডারের জন্য সঠিক CPO রিসলভ করা — campaign_id থাকলে campaign-specific, না থাকলে global
 */
function resolve_order_cpo(PDO $pdo, string $date, ?string $campaign_id): float {
    if (!empty($campaign_id)) {
        return calculate_campaign_cpo($pdo, $date, $campaign_id);
    }
    return calculate_global_daily_cpo($pdo, $date);
}

/**
 * একটি অর্ডারের সব order_items থেকে প্রোডাক্ট কস্ট যোগ করা
 * (purchase_cost, other_cost, gateway_charge, commission — সব quantity দিয়ে গুণ করে)
 * রিটার্ন করে: ['purchase_cost'=>, 'other_cost'=>, 'gateway_charge'=>, 'commission'=>, 'selling_price'=>]
 */
function get_order_item_costs(PDO $pdo, int $order_id): array {
    $totals = [
        'selling_price'  => 0.0,
        'purchase_cost'  => 0.0,
        'other_cost'     => 0.0,
        'gateway_charge' => 0.0,
        'commission'     => 0.0,
    ];

    try {
        $stmt = $pdo->prepare(
            "SELECT oi.quantity, oi.price, 
                    p.purchase_cost, p.other_cost, p.gateway_charge, p.commission
             FROM order_items oi
             JOIN products p ON p.id = oi.product_id
             WHERE oi.order_id = :order_id"
        );
        $stmt->execute(['order_id' => $order_id]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $r) {
            $qty = (int) $r['quantity'];
            $totals['selling_price']  += (float) $r['price'] * $qty;
            $totals['purchase_cost']  += (float) $r['purchase_cost'] * $qty;
            $totals['other_cost']     += (float) $r['other_cost'] * $qty;
            $totals['gateway_charge'] += (float) $r['gateway_charge'] * $qty;
            $totals['commission']     += (float) $r['commission'] * $qty;
        }
    } catch (PDOException $e) {
        // order_items/products এ প্রয়োজনীয় কলাম না থাকলে সব 0 থাকবে
    }

    return $totals;
}

/**
 * Net Profit = Selling Price - (Purchase + Other + Delivery + Gateway + Commission + CPO + Discount)
 */
function calculate_net_profit(array $order_data): float {
    $selling_price  = (float) ($order_data['selling_price'] ?? 0);
    $purchase_cost  = (float) ($order_data['purchase_cost'] ?? 0);
    $other_cost     = (float) ($order_data['other_cost'] ?? 0);
    $delivery_cost  = (float) ($order_data['delivery_cost'] ?? 0);
    $gateway_charge = (float) ($order_data['gateway_charge'] ?? 0);
    $commission     = (float) ($order_data['commission'] ?? 0);
    $discount       = (float) ($order_data['discount'] ?? 0);
    $cpo            = (float) ($order_data['ads_cost_snapshot'] ?? 0);

    $total_cost = $purchase_cost + $other_cost + $delivery_cost
                + $gateway_charge + $commission + $cpo + $discount;

    return round($selling_price - $total_cost, 2);
}

/**
 * SNAPSHOT WRITER — অর্ডার তৈরি হওয়ার মুহূর্তে (process_order.php/checkout.php থেকে) কল করতে হবে।
 * CPO ও Net Profit হিসাব করে সরাসরি orders টেবিলে ফ্রিজ করে সেভ করে দেয়, যাতে
 * পরে dollar_rate বা ad spend পরিবর্তন হলেও পুরনো অর্ডারের প্রফিট না বদলায়।
 */
function snapshot_order_profit(PDO $pdo, int $order_id, ?string $campaign_id = null): bool {
    try {
        $stmt = $pdo->prepare("SELECT created_at, delivery_cost, discount FROM orders WHERE id = :id");
        $stmt->execute(['id' => $order_id]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$order) {
            return false;
        }

        $date = date('Y-m-d', strtotime($order['created_at']));
        $cpo  = resolve_order_cpo($pdo, $date, $campaign_id);

        $item_costs = get_order_item_costs($pdo, $order_id);

        $net_profit = calculate_net_profit([
            'selling_price'     => $item_costs['selling_price'],
            'purchase_cost'     => $item_costs['purchase_cost'],
            'other_cost'        => $item_costs['other_cost'],
            'delivery_cost'     => $order['delivery_cost'] ?? 0,
            'gateway_charge'    => $item_costs['gateway_charge'],
            'commission'        => $item_costs['commission'],
            'discount'          => $order['discount'] ?? 0,
            'ads_cost_snapshot' => $cpo,
        ]);

        $stmt = $pdo->prepare(
            "UPDATE orders SET ads_cost_snapshot = :cpo, net_profit = :profit WHERE id = :id"
        );
        return $stmt->execute([
            'cpo'    => $cpo,
            'profit' => $net_profit,
            'id'     => $order_id,
        ]);
    } catch (PDOException $e) {
        return false;
    }
}