#!/usr/bin/env php
<?php
/**
 * Compile theme preset SCSS files to CSS
 * 
 * Usage: php compile-theme-presets.php [preset-name]
 * If no preset name is provided, all presets will be compiled.
 */

require __DIR__ . '/vendor/autoload.php';

use ScssPhp\ScssPhp\Compiler;

$themePath = __DIR__ . '/public/application/themes/atomik_gz';
$presetsPath = $themePath . '/css/presets';
$skinsPath = $themePath . '/css/skins';

if (!is_dir($presetsPath)) {
    die("Error: Presets directory not found: {$presetsPath}\n");
}

if (!is_dir($skinsPath)) {
    die("Error: Skins directory not found: {$skinsPath}\n");
}

// Get preset name from command line argument
$presetName = isset($argv[1]) ? $argv[1] : null;

// Get all preset directories
$presets = [];
if ($presetName) {
    $presetDir = $presetsPath . '/' . $presetName;
    if (!is_dir($presetDir)) {
        die("Error: Preset '{$presetName}' not found in {$presetsPath}\n");
    }
    $presets[$presetName] = $presetDir;
} else {
    // Compile all presets
    $dirs = scandir($presetsPath);
    foreach ($dirs as $dir) {
        if ($dir !== '.' && $dir !== '..' && is_dir($presetsPath . '/' . $dir)) {
            $presets[$dir] = $presetsPath . '/' . $dir;
        }
    }
}

if (empty($presets)) {
    die("No presets found to compile.\n");
}

$compiler = new Compiler();

// Set up import paths - SCSS compiler needs to resolve @concretecms/bedrock and bootstrap imports
$bedrockPath = __DIR__ . '/public/concrete/bedrock/assets';
$compiler->setImportPaths([
    $themePath . '/css/presets',
    $themePath . '/css/scss',
    $bedrockPath,
]);

// Preprocess SCSS to resolve @concretecms/bedrock aliases
function preprocessScss($content, $baseDir) {
    // Replace @concretecms/bedrock imports with actual paths
    $content = preg_replace_callback(
        '/@import\s+["\']@concretecms\/([^"\']+)["\'];/',
        function($matches) use ($baseDir) {
            $path = $baseDir . '/@concretecms/' . $matches[1];
            return '@import "' . str_replace('\\', '/', $path) . '";';
        },
        $content
    );
    return $content;
}

foreach ($presets as $name => $presetDir) {
    $mainScss = $presetDir . '/main.scss';
    $outputCss = $skinsPath . '/' . $name . '.css';
    
    if (!file_exists($mainScss)) {
        echo "Warning: main.scss not found for preset '{$name}', skipping...\n";
        continue;
    }
    
    echo "Compiling {$name}...\n";
    
    try {
        $scssContent = file_get_contents($mainScss);
        // Preprocess to resolve @concretecms/bedrock imports
        $scssContent = preprocessScss($scssContent, $bedrockPath);
        // Add import path for the current preset directory
        $compiler->addImportPath($presetDir);
        $css = $compiler->compileString($scssContent, $mainScss)->getCss();
        
        file_put_contents($outputCss, $css);
        echo "  ✓ Compiled to {$outputCss}\n";
    } catch (Exception $e) {
        echo "  ✗ Error compiling {$name}: " . $e->getMessage() . "\n";
    }
}

echo "\nDone!\n";
