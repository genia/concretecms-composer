<?php

defined('C5_EXECUTE') or die('Access Denied.');

# Load in the composer vendor files
# For flat structure: vendor is at concrete/vendor/
# For public/ structure: vendor is at ../../../vendor/ (from public/application/bootstrap/)
# Try flat structure first, then fall back to public structure
if (file_exists(__DIR__ . "/../../concrete/vendor/autoload.php")) {
    require_once __DIR__ . "/../../concrete/vendor/autoload.php";
    $vendor_path = __DIR__ . "/../../concrete/vendor";
} else {
    require_once __DIR__ . "/../../../vendor/autoload.php";
    $vendor_path = __DIR__ . "/../../../vendor";
}

# Try loading in environment info
try {
    (new \Symfony\Component\Dotenv\Dotenv('CONCRETE5_ENV'))
        ->usePutenv()->load(__DIR__ . '/../../../.env');
} catch (\Symfony\Component\Dotenv\Exception\PathException $e) {
    // Ignore missing file exception
}

# Add the vendor directory to the include path
ini_set('include_path', $vendor_path . PATH_SEPARATOR . get_include_path());
