<?php
define('WP_USE_THEMES', false);
require_once('../../../wp-load.php');

$key = 'openai_key';
$val = VMSAI_Settings::credential($key);
echo "Key $key: " . ($val ? 'EXISTS (length ' . strlen($val) . ')' : 'EMPTY') . "\n";
