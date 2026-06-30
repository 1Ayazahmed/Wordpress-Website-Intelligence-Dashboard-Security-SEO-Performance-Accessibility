<?php
if (!defined('ABSPATH')) exit;

class AZ_Backup {

    public function create_backup($type = 'full') {
        $backup_name = 'backup_' . $type . '_' . current_time('Ymd_H_i_s');
        $backup_dir = AZ_BACKUP_DIR . $backup_name . '/';

        if (!file_exists($backup_dir)) {
            wp_mkdir_p($backup_dir);
        }

        $files_to_backup = [
            ABSPATH . '.htaccess'        => '.htaccess',
            ABSPATH . 'robots.txt'       => 'robots.txt',
            ABSPATH . 'wp-config.php'    => 'wp-config.php',
        ];

        $backed_up_files = [];

        foreach ($files_to_backup as $source => $dest_filename) {
            if (file_exists($source)) {
                $dest = $backup_dir . $dest_filename;
                if (copy($source, $dest)) {
                    $backed_up_files[] = $dest_filename;
                }
            }
        }

        AZ_Logger::log("Backup created: {$backup_name} with " . count($backed_up_files) . " files", 'SUCCESS');

        return [
            'success'     => true,
            'backup_name' => $backup_name,
            'files'       => $backed_up_files,
        ];
    }

    public function restore_backup($backup_name) {
        $backup_dir = AZ_BACKUP_DIR . $backup_name . '/';

        if (!file_exists($backup_dir)) {
            return [
                'success' => false,
                'message' => 'Backup directory not found: ' . $backup_name,
                'files'   => [],
            ];
        }

        $restore_map = [
            '.htaccess'     => ABSPATH . '.htaccess',
            'robots.txt'    => ABSPATH . 'robots.txt',
            'wp-config.php' => ABSPATH . 'wp-config.php',
        ];

        $restored_files = [];

        foreach ($restore_map as $filename => $dest) {
            $source = $backup_dir . $filename;
            if (file_exists($source)) {
                if (copy($source, $dest)) {
                    $restored_files[] = $filename;
                }
            }
        }

        if (!empty($restored_files)) {
            AZ_Logger::log("Backup restored: {$backup_name} with " . count($restored_files) . " files", 'SUCCESS');
            return [
                'success' => true,
                'message' => 'Restored ' . count($restored_files) . ' files from ' . $backup_name,
                'files'   => $restored_files,
            ];
        }

        return [
            'success' => false,
            'message' => 'No files found to restore in ' . $backup_name,
            'files'   => [],
        ];
    }

    public function get_backups() {
        $backups = [];
        $backup_dir = AZ_BACKUP_DIR;

        if (!file_exists($backup_dir)) {
            return $backups;
        }

        $directories = glob($backup_dir . 'backup_*', GLOB_ONLYDIR);

        if ($directories) {
            foreach ($directories as $dir) {
                $backup_name = basename($dir);
                $size = $this->get_dir_size($dir);
                $backups[] = [
                    'name' => $backup_name,
                    'date' => date('Y-m-d H:i:s', filemtime($dir)),
                    'size' => size_format($size),
                ];
            }
        }

        usort($backups, function ($a, $b) {
            return strtotime($b['date']) - strtotime($a['date']);
        });

        return $backups;
    }

    public function delete_backup($backup_name) {
        $backup_dir = AZ_BACKUP_DIR . $backup_name . '/';

        if (!file_exists($backup_dir)) {
            return ['success' => false];
        }

        $this->recursive_delete($backup_dir);
        AZ_Logger::log("Backup deleted: {$backup_name}", 'WARNING');

        return ['success' => true];
    }

    private function recursive_delete($dir) {
        if (!file_exists($dir)) {
            return;
        }

        $files = scandir($dir);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->recursive_delete($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }

    private function get_dir_size($dir) {
        $size = 0;
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
        foreach ($files as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }
        return $size;
    }
}
