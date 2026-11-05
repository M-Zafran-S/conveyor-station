<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Put your exact Firebase URL ending with .json
$testUrl = "https://your-project-id-default-rtdb.asia-southeast1.firebasedatabase.app/ProductionData.json";

echo "<h2>1. Checking PHP Extensions</h2>";
echo "cURL loaded: " . (function_exists('curl_version') ? '✅ Yes' : '❌ No (cURL extension missing)') . "<br>";
echo "OpenSSL loaded: " . (extension_loaded('openssl') ? '✅ Yes' : '❌ No') . "<br>";

echo "<h2>2. Testing Outbound cURL to Firebase</h2>";
$ch = curl_init($testUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
$errno = curl_errno($ch);
curl_close($ch);

echo "HTTP Status Code: <strong>$httpCode</strong><br>";
if ($errno) {
    echo "❌ cURL Error ($errno): <strong>$error</strong><br>";
} else {
    echo "✅ Raw Response: <pre>" . htmlspecialchars($response) . "</pre>";
}
?>