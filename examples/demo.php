#!/usr/bin/env php
<?php

require __DIR__ . '/../vendor/autoload.php';

use JoeWare\Envsemble\EnvMerger;

echo "🚀 Envsemble Demo\n";
echo "================\n\n";

// Use the example files
$baseFile = __DIR__ . '/.env.base';
$patchesDir = __DIR__ . '/env-patches';
$outputFile = __DIR__ . '/.env.generated';

if (!file_exists($baseFile)) {
    echo "❌ Error: Example base file not found at {$baseFile}\n";
    exit(1);
}

if (!is_dir($patchesDir)) {
    echo "❌ Error: Example patches directory not found at {$patchesDir}\n";
    exit(1);
}

echo "📁 Files to process:\n";
echo "   Base: {$baseFile}\n";
echo "   Patches: {$patchesDir}\n";
echo "   Output: {$outputFile}\n\n";

try {
    $merger = new EnvMerger();
    
    echo "🔧 Merging files...\n";
    $result = $merger->merge($baseFile, $patchesDir, true);
    
    echo "✅ Merge completed!\n\n";
    
    // Display report
    echo "📊 Merge Report:\n";
    echo "┌─────────────────────┬───────┐\n";
    echo "│ Metric              │ Count │\n";
    echo "├─────────────────────┼───────┤\n";
    printf("│ %-19s │ %5d │\n", "Base file keys", $result->getBaseKeysCount());
    printf("│ %-19s │ %5d │\n", "Patch files processed", $result->getPatchFilesCount());
    printf("│ %-19s │ %5d │\n", "Keys added", $result->getAddedKeysCount());
    printf("│ %-19s │ %5d │\n", "Keys modified", $result->getModifiedKeysCount());
    printf("│ %-19s │ %5d │\n", "Keys deleted", $result->getDeletedKeysCount());
    printf("│ %-19s │ %5d │\n", "Final output keys", $result->getKeysCount());
    echo "└─────────────────────┴───────┘\n\n";
    
    if (!empty($result->getAddedKeys())) {
        echo "➕ Added keys: " . implode(', ', $result->getAddedKeys()) . "\n";
    }
    
    if (!empty($result->getModifiedKeys())) {
        echo "🔄 Modified keys: " . implode(', ', $result->getModifiedKeys()) . "\n";
    }
    
    if (!empty($result->getDeletedKeys())) {
        echo "🗑️  Deleted keys: " . implode(', ', $result->getDeletedKeys()) . "\n";
    }
    
    echo "\n";
    
    // Generate and save output
    $output = $merger->generateOutput($result, true);
    file_put_contents($outputFile, $output);
    
    echo "💾 Output saved to: {$outputFile}\n\n";
    
    echo "📋 Generated content preview (first 20 lines):\n";
    echo "─────────────────────────────────────────────\n";
    $lines = explode("\n", $output);
    $previewLines = array_slice($lines, 0, 20);
    foreach ($previewLines as $line) {
        echo $line . "\n";
    }
    if (count($lines) > 20) {
        echo "... (" . (count($lines) - 20) . " more lines)\n";
    }
    echo "─────────────────────────────────────────────\n\n";
    
    $efficiency = $result->getBaseKeysCount() > 0 
        ? round(($result->getKeysCount() / $result->getBaseKeysCount()) * 100, 1)
        : 0;
    
    echo "📈 Efficiency: {$efficiency}% keys retained/added from base\n";
    echo "🎉 Demo completed successfully!\n";
    
} catch (Exception $e) {
    echo "❌ Error: {$e->getMessage()}\n";
    exit(1);
}
