<?php
$hosts = ['google.com', 'api.pollinations.ai', 'generativelanguage.googleapis.com', 'api-inference.huggingface.co'];
foreach($hosts as $host) {
    $ip = gethostbyname($host);
    echo "DNS $host: " . ($ip === $host ? 'FAILED' : "OK ($ip)") . "\n";
}

$url = 'https://image.pollinations.ai/prompt/test';
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$res = curl_exec($ch);
if (curl_errno($ch)) {
    echo "CURL $url: FAILED - " . curl_error($ch) . "\n";
} else {
    echo "CURL $url: OK (" . strlen($res) . " bytes)\n";
}
curl_close($ch);
