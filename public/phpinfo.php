<?php
/**
 * Quick PHP configuration diagnostic
 * Run this on prod to see PHP settings
 * DELETE THIS FILE AFTER USE - it exposes sensitive info!
 * 
 * SECURITY: Only accessible to logged-in administrators
 */

// Bootstrap ConcreteCMS without running dispatcher
require __DIR__ . '/concrete/bootstrap/configure.php';
require __DIR__ . '/concrete/bootstrap/autoload.php';
$app = require __DIR__ . '/concrete/bootstrap/start.php';

use Concrete\Core\User\User;

// Check if user is logged in and is admin
$u = new User();
if (!$u->isRegistered()) {
    http_response_code(403);
    die('Access Denied. You must be logged in to view this page.');
}

// Check if user is in admin group
$ui = $u->getUserInfoObject();
if (!$ui) {
    http_response_code(403);
    die('Access Denied. Unable to verify user permissions.');
}

// Check multiple ways to verify admin status
$isAdmin = false;

// Method 1: Check Administrators group
$adminGroup = \Concrete\Core\User\Group\Group::getByName('Administrators');
if ($adminGroup && $ui->inGroup($adminGroup)) {
    $isAdmin = true;
}

// Method 2: Check if user is super admin (user ID 1)
if ($u->getUserID() == 1) {
    $isAdmin = true;
}

// Method 3: Check if user has access to dashboard
if (!$isAdmin) {
    $permissions = new \Concrete\Core\Permission\Checker();
    $permissions->setUserObject($ui);
    if ($permissions->canAccessTaskPermissions()) {
        $isAdmin = true;
    }
}

if (!$isAdmin) {
    http_response_code(403);
    die('Access Denied. Administrator privileges required. User ID: ' . $u->getUserID() . ', Groups: ' . implode(', ', array_map(function($g) { return $g->getGroupName(); }, $ui->getUserGroups())));
}

echo "<h2>PHP Configuration</h2>";
echo "<p><strong>php.ini location:</strong> " . php_ini_loaded_file() . "</p>";
echo "<p><strong>Additional ini files:</strong> " . php_ini_scanned_files() . "</p>";

echo "<h3>Error Logging Settings</h3>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Setting</th><th>Value</th></tr>";
echo "<tr><td>display_errors</td><td>" . ini_get('display_errors') . "</td></tr>";
echo "<tr><td>log_errors</td><td>" . ini_get('log_errors') . "</td></tr>";
echo "<tr><td>error_log</td><td>" . ini_get('error_log') . "</td></tr>";
echo "<tr><td>error_reporting</td><td>" . ini_get('error_reporting') . " (" . error_reporting() . ")</td></tr>";
echo "</table>";

echo "<h3>Test Error Log</h3>";
$testMessage = "Test error log entry at " . date('Y-m-d H:i:s');
error_log($testMessage);
echo "<p>Wrote test message to error log. Check: <code>" . ini_get('error_log') . "</code></p>";

echo "<h3>Common Log Locations</h3>";
echo "<ul>";
echo "<li>/var/log/apache2/error.log</li>";
echo "<li>/var/log/httpd/error_log</li>";
echo "<li>/var/log/php_errors.log</li>";
echo "<li>" . ini_get('error_log') . "</li>";
echo "</ul>";

echo "<h3>ConcreteCMS Log Location</h3>";
$concreteLog = __DIR__ . '/application/files/logs/concrete.log';
echo "<p>ConcreteCMS log: <code>" . $concreteLog . "</code></p>";
if (file_exists($concreteLog)) {
    echo "<p>Exists: Yes</p>";
    echo "<p>Size: " . filesize($concreteLog) . " bytes</p>";
    echo "<p>Last modified: " . date('Y-m-d H:i:s', filemtime($concreteLog)) . "</p>";
} else {
    echo "<p>Exists: No</p>";
}
