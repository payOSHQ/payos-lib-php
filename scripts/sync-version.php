#!/usr/bin/env php
<?php

/**
 * Sync version from composer.json to PayOS.php
 */

$composerPath = __DIR__ . '/../composer.json';
$payosPath = __DIR__ . '/../src/PayOS.php';

// Read composer.json
if (!file_exists($composerPath)) {
    echo "Error: composer.json not found\n";
    exit(1);
}

$composerContent = file_get_contents($composerPath);
$composerData = json_decode($composerContent, true);

if (!isset($composerData['version'])) {
    echo "Error: version not found in composer.json\n";
    exit(1);
}

$version = $composerData['version'];

// Read PayOS.php
if (!file_exists($payosPath)) {
    echo "Error: PayOS.php not found\n";
    exit(1);
}

$payosContent = file_get_contents($payosPath);

// Update VERSION constant
$pattern = '/const VERSION = "[^"]+";/';
$replacement = 'const VERSION = "' . $version . '";';
$newContent = preg_replace($pattern, $replacement, $payosContent, 1, $count);

if ($count === 0) {
    echo "Error: VERSION constant not found in PayOS.php\n";
    exit(1);
}

// Write updated content
file_put_contents($payosPath, $newContent);

echo "✓ Version synced: {$version}\n";
exit(0);
