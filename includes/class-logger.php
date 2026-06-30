<?php
if (!defined('ABSPATH')) exit;

class AZ_Logger {

    public static function log($message, $type = 'INFO') {
        $valid_types = ['INFO', 'ERROR', 'WARNING', 'SUCCESS'];
        if (!in_array($type, $valid_types)) {
            $type = 'INFO';
        }

        $timestamp = current_time('Y-m-d H:i:s');
        $log_entry = "[{$timestamp}] [{$type}] {$message}" . PHP_EOL;
        $log_file = AZ_LOG_FILE;

        $log_dir = dirname($log_file);
        if (!file_exists($log_dir)) {
            wp_mkdir_p($log_dir);
        }

        file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
    }
}
