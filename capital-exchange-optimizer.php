<?php
/**
 * Plugin Name: AZ Optimizer
 * Plugin URI:  https://github.com/1Ayazahmed
 * Description: Enterprise all-in-one WordPress optimizer — security hardening, SEO audit, performance tuning, accessibility fixes, AI analysis, automated backups, health monitoring, and scheduled scans.
 * Version:     3.0.0
 * Requires PHP: 7.4
 * Requires at least: 5.6
 * Author:      Ayaz Ahmed
 * Author URI:  https://github.com/1Ayazahmed
 * License:     GPL v2 or later
 * Text Domain: az-optimizer
 */

if (!defined('ABSPATH')) {
    exit;
}

define('AZ_VERSION', '3.0.0');
define('AZ_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AZ_PLUGIN_URL', plugin_dir_url(__FILE__));
define('AZ_BACKUP_DIR', AZ_PLUGIN_DIR . 'backups/');
define('AZ_LOG_FILE', AZ_PLUGIN_DIR . 'az-optimization-log.txt');

spl_autoload_register(function ($class) {
    $prefix = 'AZ_';
    if (strpos($class, $prefix) !== 0) {
        return;
    }
    $class_name = strtolower(str_replace($prefix, '', $class));
    $file = AZ_PLUGIN_DIR . 'includes/class-' . $class_name . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

$az_includes = [
    'class-logger.php',
    'class-backup.php',
    'class-scanner.php',
    'class-fixer.php',
    'class-ai-integration.php',
    'class-admin.php',
];

foreach ($az_includes as $include) {
    $path = AZ_PLUGIN_DIR . 'includes/' . $include;
    if (file_exists($path)) {
        require_once $path;
    }
}

register_activation_hook(__FILE__, 'az_activate');
function az_activate() {
    if (!file_exists(AZ_BACKUP_DIR)) {
        wp_mkdir_p(AZ_BACKUP_DIR);
    }

    $htaccess = AZ_BACKUP_DIR . '.htaccess';
    if (!file_exists($htaccess)) {
        $content = "Options -Indexes\n<FilesMatch \"\\.(php|php3|php4|php5|phtml|pl|py|jsp|asp|aspx|cgi|sh|bash)$\">\n    Require all denied\n</FilesMatch>\n";
        @file_put_contents($htaccess, $content);
    }

    if (!file_exists(AZ_LOG_FILE)) {
        @file_put_contents(AZ_LOG_FILE, '');
    }

    if (class_exists('AZ_Logger')) {
        AZ_Logger::log('Plugin activated (v' . AZ_VERSION . ')', 'SUCCESS');
    }

    if (!wp_next_scheduled('az_scheduled_scan') && get_option('az_scheduled_scan_enabled', false)) {
        $frequency = get_option('az_scheduled_scan_frequency', 'daily');
        wp_schedule_event(time() + DAY_IN_SECONDS, $frequency, 'az_scheduled_scan');
    }
}

register_deactivation_hook(__FILE__, 'az_deactivate');
function az_deactivate() {
    $timestamp = wp_next_scheduled('az_scheduled_scan');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'az_scheduled_scan');
    }

    if (class_exists('AZ_Logger')) {
        AZ_Logger::log('Plugin deactivated (v' . AZ_VERSION . ')', 'WARNING');
    }
}

add_filter('cron_schedules', 'az_add_cron_schedules');
function az_add_cron_schedules($schedules) {
    $schedules['weekly'] = [
        'interval' => WEEK_IN_SECONDS,
        'display'  => 'Once Weekly',
    ];
    return $schedules;
}

new AZ_Admin();
