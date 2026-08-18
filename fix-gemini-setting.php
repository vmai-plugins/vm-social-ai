<?php
define('WP_USE_THEMES', false);
require_once('../../../wp-load.php');

$settings = VMSAI_Settings::all();
if (isset($settings['image_model']['gemini']) && $settings['image_model']['gemini'] === 'imagen-3') {
    $settings['image_model']['gemini'] = 'imagen-3.0-generate-001';
    VMSAI_Settings::update($settings);
    echo "Updated Gemini model from 'imagen-3' to 'imagen-3.0-generate-001'.\n";
} else {
    echo "Gemini model setting was: " . ($settings['image_model']['gemini'] ?? 'NOT SET') . "\n";
}

// Also clear the circuit breaker for gemini
VMSAI_Circuit::reset('image:gemini');
VMSAI_Circuit::reset('image:pollinations');
echo "Reset circuit breakers for Gemini and Pollinations.\n";
