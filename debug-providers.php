<?php
define('WP_USE_THEMES', false);
require_once('../../../wp-load.php');

$engine = vmsai()->image_engine();
echo "Active providers slugs: " . implode(', ', array_keys($engine->providers())) . "\n";

foreach(['VMSAI_Image_OpenAI', 'VMSAI_Image_Pollinations', 'VMSAI_Image_Pexels'] as $class) {
    echo "$class: " . (class_exists($class) ? 'EXISTS' : 'MISSING') . "\n";
}
