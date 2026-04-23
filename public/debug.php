<?php
$file = __DIR__ . '/assets/css/style.sweettreats.css';

echo "<pre style='font-family:monospace;font-size:13px;'>";
echo "=== FILE INFO ===\n";
echo "Path          : $file\n";
echo "file_exists   : " . (file_exists($file) ? 'TRUE' : 'FALSE') . "\n";
echo "is_readable   : " . (is_readable($file) ? 'TRUE' : 'FALSE') . "\n";

if (file_exists($file)) {
    echo "filesize      : " . number_format(filesize($file)) . " bytes\n";
    echo "filemtime     : " . date('Y-m-d H:i:s', filemtime($file)) . "\n";
    echo "perms         : " . substr(sprintf('%o', fileperms($file)), -4) . "\n";

    $content = file_get_contents($file);
    echo "total lines   : " . substr_count($content, "\n") . "\n";
    echo "total chars   : " . strlen($content) . "\n\n";

    echo "=== KLASS CHECK ===\n";
    $classes = ['auth-body', 'auth-card', 'card-header-brand', 'btn-auth',
                'field-group', 'forgot-link', 'footer-note', 'logo-wrap',
                'st-btn-brand', 'st-card', 'st-table', 'st-filter'];
    foreach ($classes as $cls) {
        $count = substr_count($content, $cls);
        $mark = $count > 0 ? '[OK ]' : '[MISS]';
        echo "$mark .$cls: $count times\n";
    }

    echo "\n=== LAST 5 LINES ===\n";
    $lines = explode("\n", $content);
    $total = count($lines);
    for ($i = max(0, $total - 5); $i < $total; $i++) {
        echo "Line " . ($i+1) . ": " . htmlspecialchars(substr($lines[$i], 0, 100)) . "\n";
    }
}

echo "\n=== COMPONENTS FILE ===\n";
$comp = __DIR__ . '/assets/css/components.sweettreats.css';
echo "exists: " . (file_exists($comp) ? 'YES (' . number_format(filesize($comp)) . ' bytes)' : 'NO') . "\n";

echo "\n=== OPCACHE ===\n";
echo function_exists('opcache_get_status') ? 'available' : 'not available';

echo "</pre>";
