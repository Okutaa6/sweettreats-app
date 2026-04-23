<?php
// Ambil CSS via HTTP (sama seperti yang dilakukan browser)
$url = 'https://app.sweettreats.id/assets/css/style.sweettreats.css?v=' . rand();

$ctx = stream_context_create(['http' => ['timeout' => 5, 'ignore_errors' => true]]);
$content = @file_get_contents($url, false, $ctx);

echo "<pre>";
echo "URL: $url\n\n";
echo "HTTP headers:\n";
if (isset($http_response_header)) {
    foreach ($http_response_header as $h) echo "  $h\n";
}
echo "\nContent length: " . ($content === false ? 'FAILED' : number_format(strlen($content)) . ' bytes') . "\n";

if ($content !== false) {
    echo "auth-body count: " . substr_count($content, 'auth-body') . "\n";
    echo "auth-card count: " . substr_count($content, 'auth-card') . "\n";
    echo "btn-auth count : " . substr_count($content, 'btn-auth') . "\n";

    echo "\n--- FIRST 500 chars ---\n";
    echo htmlspecialchars(substr($content, 0, 500));

    echo "\n\n--- LAST 300 chars ---\n";
    echo htmlspecialchars(substr($content, -300));
}
echo "</pre>";
