<?php
define('WP_USE_THEMES', false);
require_once('../../../wp-load.php');

echo "Syncing models...\n";
$result = VMSAI_Model_Sync::run();
print_r($result);

echo "\nCurrent Gemini Model: " . (VMSAI_Settings::get('image_model')['gemini'] ?? 'NOT SET (defaulting to imagen-3.0-generate-001)') . "\n";
