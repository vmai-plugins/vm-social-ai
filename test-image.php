<?php
define('WP_USE_THEMES', false);
require_once('../../../wp-load.php');

$result = vmsai()->image_engine()->create('test image of a cat');
print_r($result);
