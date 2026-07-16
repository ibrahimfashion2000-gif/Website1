<?php
/**
 * cron/night_queue_worker.php
 * -----------------------------------------------------------
 * Schedule this with XAMPP's cron equivalent (Windows Task Scheduler)
 * or a real cron job every 15-30 minutes, e.g.:
 *
 *   C:\xampp\php\php.exe C:\xampp\htdocs\your-project\cron\night_queue_worker.php
 *
 * It will only actually process rows once cl_is_night_mode() is false
 * (i.e. once your configured daytime window starts), so it's safe to
 * run it frequently around the clock.
 * -----------------------------------------------------------
 */

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/courier_functions.php';

$result = cl_flush_calling_queue($conn, 100);

echo '[' . date('Y-m-d H:i:s') . '] ' . json_encode($result) . PHP_EOL;