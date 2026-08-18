<?php
define('WP_USE_THEMES', false);
require_once('../../../wp-load.php');

$logs = VMSAI_Logger::recent(50);
foreach($logs as $log) {
    echo "{$log['created_at']} [{$log['level']}] [{$log['scope']}] {$log['message']}\n";
    if ($log['context']) echo "Context: {$log['context']}\n";
    echo "------------------\n";
}
