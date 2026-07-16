<?php
// ডেলিভারি চার্জের লিস্ট
$delivery_rates = [
    'Inside Dhaka' => 60,
    'Sub-Urban' => 100,
    'Outside Dhaka' => 150
];

// ============================================================
// Ads Analytics Module — Global Settings
// ============================================================

// ডলার রেট (BDT) — dollar_rate_history টেবিলে কোনো এন্ট্রি না থাকলে এটাই ব্যবহার হবে
$dollar_rate = 130;

// ট্র্যাকিং সেটিংস (UTM / Pixel / CAPI কনফিগ)
$ads_tracking_config = [
    'meta_pixel_id'      => '',   // Facebook/Instagram Pixel ID
    'meta_capi_token'    => '',   // Meta Conversion API access token
    'tiktok_pixel_id'    => '',
    'ga4_measurement_id' => '',
    'ga4_api_secret'     => '',
];

// অটো-সিঙ্ক ইন্টারভাল (মিনিট) — cron/ থেকে ব্যবহৃত হবে
$ads_sync_interval_minutes = 30;