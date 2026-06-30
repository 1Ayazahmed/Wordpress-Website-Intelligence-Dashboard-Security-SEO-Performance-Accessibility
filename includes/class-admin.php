<?php
if (!defined('ABSPATH')) exit;

class AZ_Admin {

    public function __construct() {
        add_action('admin_menu', [$this, 'add_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('wp_ajax_az_full_scan', [$this, 'ajax_full_scan']);
        add_action('wp_ajax_az_fix_issue', [$this, 'ajax_fix_issue']);
        add_action('wp_ajax_az_fix_all_auto', [$this, 'ajax_fix_all_auto']);
        add_action('wp_ajax_az_backup_site', [$this, 'ajax_backup_site']);
        add_action('wp_ajax_az_restore_backup', [$this, 'ajax_restore_backup']);
        add_action('wp_ajax_az_get_backups', [$this, 'ajax_get_backups']);
        add_action('wp_ajax_az_delete_backup', [$this, 'ajax_delete_backup']);
        add_action('wp_ajax_az_ai_analyze', [$this, 'ajax_ai_analyze']);
        add_action('wp_ajax_az_save_ai_settings', [$this, 'ajax_save_ai_settings']);
        add_action('wp_ajax_az_install_plugins', [$this, 'ajax_install_plugins']);
        add_action('wp_ajax_az_clean_junk', [$this, 'ajax_clean_junk']);
        add_action('wp_ajax_az_optimize_database', [$this, 'ajax_optimize_database']);
        add_action('wp_ajax_az_get_logs', [$this, 'ajax_get_logs']);
        add_action('wp_ajax_az_get_server_info', [$this, 'ajax_get_server_info']);
        add_action('wp_ajax_az_get_health_history', [$this, 'ajax_get_health_history']);
        add_action('wp_ajax_az_export_report', [$this, 'ajax_export_report']);
        add_action('wp_ajax_az_send_report_email', [$this, 'ajax_send_report_email']);
        add_action('wp_ajax_az_save_schedule', [$this, 'ajax_save_schedule']);
        add_action('wp_ajax_az_get_dashboard_summary', [$this, 'ajax_get_dashboard_summary']);
        add_action('wp_ajax_az_fetch_ai_models', [$this, 'ajax_fetch_ai_models']);
        add_action('wp_ajax_az_auto_optimize', [$this, 'ajax_auto_optimize']);
        add_action('az_scheduled_scan', [$this, 'run_scheduled_scan']);
        add_action('admin_notices', [$this, 'show_critical_notice']);
    }

    public function add_menu() {
        add_menu_page(
            'AZ Optimizer',
            'AZ Optimizer',
            'manage_options',
            'az-dashboard',
            [$this, 'render_dashboard'],
            'dashicons-performance',
            80
        );
    }

    public function enqueue_assets($hook) {
        if ($hook !== 'toplevel_page_az-dashboard') {
            return;
        }

        wp_enqueue_style('az-admin', AZ_PLUGIN_URL . 'assets/css/admin.css', [], AZ_VERSION);
        wp_enqueue_script('az-admin', AZ_PLUGIN_URL . 'assets/js/admin.js', ['jquery'], AZ_VERSION, true);

        wp_localize_script('az-admin', 'az_ajax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('az_nonce'),
        ]);
    }

    public function render_dashboard() {
        include AZ_PLUGIN_DIR . 'templates/dashboard.php';
    }

    private function verify_nonce() {
        check_ajax_referer('az_nonce', 'nonce');
    }

    public function ajax_get_dashboard_summary() {
        $this->verify_nonce();
        $scanner = new AZ_Scanner();
        $issues = $scanner->full_scan();

        $counts = ['critical' => 0, 'high' => 0, 'medium' => 0, 'low' => 0];
        foreach ($issues as $issue) {
            if (isset($counts[$issue['severity']])) {
                $counts[$issue['severity']]++;
            }
        }

        $health_score = $scanner->get_health_score();
        $category_scores = $scanner->get_category_scores();
        $history = $scanner->get_health_history();
        $server_info = $scanner->get_server_info();
        $scan_count = get_option('az_scan_count', 0);
        $last_scan = get_option('az_last_scan_time', 'Never');

        update_option('az_scan_count', $scan_count + 1);
        update_option('az_last_scan_time', current_time('Y-m-d H:i:s'));

        $summary = [
            'issues'          => $issues,
            'total'           => count($issues),
            'critical'        => $counts['critical'],
            'high'            => $counts['high'],
            'medium'          => $counts['medium'],
            'low'             => $counts['low'],
            'health_score'    => $health_score,
            'category_scores' => $category_scores,
            'history'         => $history,
            'server_info'     => $server_info,
            'scan_count'      => $scan_count + 1,
            'last_scan'       => $last_scan,
        ];

        wp_send_json_success($summary);
    }

    public function ajax_full_scan() {
        $this->verify_nonce();
        $scanner = new AZ_Scanner();
        $issues = $scanner->full_scan();

        $counts = ['critical' => 0, 'high' => 0, 'medium' => 0, 'low' => 0];
        foreach ($issues as $issue) {
            if (isset($counts[$issue['severity']])) {
                $counts[$issue['severity']]++;
            }
        }

        $health_score = $scanner->get_health_score();
        $category_scores = $scanner->get_category_scores();

        update_option('az_last_scan_results', [
            'issues' => $issues,
            'time'   => current_time('Y-m-d H:i:s'),
            'score'  => $health_score,
        ]);

        AZ_Logger::log('Scan completed: ' . count($issues) . ' issues (Score: ' . $health_score . ')', 'SUCCESS');

        $this->maybe_send_critical_alert($issues);

        wp_send_json_success([
            'issues'          => $issues,
            'total'           => count($issues),
            'critical'        => $counts['critical'],
            'high'            => $counts['high'],
            'medium'          => $counts['medium'],
            'low'             => $counts['low'],
            'health_score'    => $health_score,
            'category_scores' => $category_scores,
        ]);
    }

    private function maybe_send_critical_alert($issues) {
        $critical_issues = array_filter($issues, function ($i) {
            return $i['severity'] === 'critical';
        });

        if (!empty($critical_issues) && get_option('az_email_alerts', false)) {
            $to = get_option('az_alert_email', get_option('admin_email'));
            $subject = 'Critical Issues Found - ' . get_bloginfo('name');
            $message = 'The following critical issues were detected:' . "\n\n";
            foreach ($critical_issues as $issue) {
                $message .= '- ' . $issue['title'] . "\n  " . $issue['description'] . "\n\n";
            }
            $message .= 'View dashboard: ' . admin_url('admin.php?page=az-dashboard');
            wp_mail($to, $subject, $message);
        }
    }

    public function ajax_fix_issue() {
        $this->verify_nonce();
        $fix_id = isset($_POST['fix_id']) ? sanitize_text_field($_POST['fix_id']) : '';

        if (empty($fix_id)) {
            wp_send_json_error(['message' => 'No fix ID provided']);
        }

        $fixer = new AZ_Fixer();
        $result = $fixer->fix($fix_id);

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    public function ajax_fix_all_auto() {
        $this->verify_nonce();

        $install_results = [];
        if (current_user_can('install_plugins')) {
            $install_results = $this->install_recommended_plugins();
        }

        $scanner = new AZ_Scanner();
        $issues = $scanner->full_scan();
        $fixer = new AZ_Fixer();

        $fixed = 0;
        $failed = 0;
        $fix_results = [];

        foreach ($issues as $issue) {
            if (!empty($issue['auto_fix'])) {
                $result = $fixer->fix($issue['fix']);
                if ($result['success']) {
                    $fixed++;
                    $fix_results[] = ['id' => $issue['id'], 'title' => $issue['title'], 'status' => 'fixed', 'message' => $result['message']];
                } else {
                    $failed++;
                    $fix_results[] = ['id' => $issue['id'], 'title' => $issue['title'], 'status' => 'failed', 'message' => $result['message']];
                }
            }
        }

        $this->auto_configure_rankmath();
        $this->auto_configure_smush();

        AZ_Logger::log("Auto-optimize: {$fixed} fixes, {$failed} failed", 'SUCCESS');

        wp_send_json_success([
            'fixed' => $fixed,
            'failed' => $failed,
            'install_results' => $install_results,
            'results' => $fix_results,
        ]);
    }

    public function ajax_backup_site() {
        $this->verify_nonce();
        $backup = new AZ_Backup();
        $result = $backup->create_backup();
        wp_send_json($result['success'] ? wp_send_json_success($result) : wp_send_json_error($result));
    }

    public function ajax_restore_backup() {
        $this->verify_nonce();
        $backup_name = isset($_POST['backup_name']) ? sanitize_text_field($_POST['backup_name']) : '';
        if (empty($backup_name)) {
            wp_send_json_error(['message' => 'No backup name provided']);
        }
        $backup = new AZ_Backup();
        $result = $backup->restore_backup($backup_name);
        wp_send_json($result['success'] ? wp_send_json_success($result) : wp_send_json_error($result));
    }

    public function ajax_get_backups() {
        $this->verify_nonce();
        $backup = new AZ_Backup();
        $backups = $backup->get_backups();
        wp_send_json_success(['backups' => $backups]);
    }

    public function ajax_delete_backup() {
        $this->verify_nonce();
        $backup_name = isset($_POST['backup_name']) ? sanitize_text_field($_POST['backup_name']) : '';
        if (empty($backup_name)) {
            wp_send_json_error(['message' => 'No backup name provided']);
        }
        $backup = new AZ_Backup();
        $result = $backup->delete_backup($backup_name);
        wp_send_json($result['success'] ? wp_send_json_success($result) : wp_send_json_error($result));
    }

    public function ajax_ai_analyze() {
        $this->verify_nonce();
        $scanner = new AZ_Scanner();
        $issues = $scanner->full_scan();
        $ai = new AZ_AI_Integration();
        $result = $ai->analyze($issues);
        wp_send_json_success(['analysis' => $result]);
    }

    public function ajax_fetch_ai_models() {
        $this->verify_nonce();
        $ai = new AZ_AI_Integration();
        $result = $ai->fetch_models();
        if (isset($result['success'])) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    public function ajax_save_ai_settings() {
        $this->verify_nonce();
        $api_key     = isset($_POST['api_key']) ? sanitize_text_field($_POST['api_key']) : '';
        $base_url    = isset($_POST['base_url']) ? sanitize_text_field($_POST['base_url']) : 'https://api.openai.com/v1';
        $model       = isset($_POST['model']) ? sanitize_text_field($_POST['model']) : 'gpt-4o-mini';
        $custom_model = isset($_POST['custom_model']) ? sanitize_text_field($_POST['custom_model']) : '';

        if ($model === 'custom' && !empty($custom_model)) {
            $model = $custom_model;
        }

        update_option('az_openai_api_key', $api_key);
        update_option('az_openai_base_url', $base_url);
        update_option('az_openai_model', $model);
        update_option('az_custom_model', $custom_model);

        AZ_Logger::log('AI settings saved', 'INFO');
        wp_send_json_success(['message' => 'AI settings saved successfully']);
    }

    public function ajax_install_plugins() {
        $this->verify_nonce();
        if (!current_user_can('install_plugins')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }

        include_once ABSPATH . 'wp-admin/includes/plugin.php';
        include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        include_once ABSPATH . 'wp-admin/includes/plugin-install.php';

        $plugins = [
            'seo-by-rank-math/rank-math.php' => ['slug' => 'seo-by-rank-math', 'name' => 'Rank Math SEO'],
            'wp-smushit/wp-smush.php'        => ['slug' => 'wp-smushit', 'name' => 'Smush Image Optimization'],
            'wp-asset-clean-up/wpacu.php'    => ['slug' => 'wp-asset-clean-up', 'name' => 'Asset CleanUp'],
        ];

        $results = [];
        foreach ($plugins as $plugin_path => $info) {
            if (is_plugin_active($plugin_path)) {
                $results[] = ['name' => $info['name'], 'status' => 'already_active', 'message' => 'Already active'];
                continue;
            }

            $api = plugins_api('plugin_information', [
                'slug'   => $info['slug'],
                'fields' => ['sections' => false],
            ]);

            if (is_wp_error($api)) {
                $results[] = ['name' => $info['name'], 'status' => 'failed', 'message' => $api->get_error_message()];
                continue;
            }

            $upgrader = new Plugin_Upgrader(new Automatic_Upgrader_Skin());
            $installed = $upgrader->install($api->download_link);

            if ($installed) {
                $activated = activate_plugin($plugin_path);
                if (is_wp_error($activated)) {
                    $results[] = ['name' => $info['name'], 'status' => 'installed', 'message' => 'Installed but activation failed: ' . $activated->get_error_message()];
                } else {
                    $results[] = ['name' => $info['name'], 'status' => 'installed_and_activated', 'message' => 'Installed and activated'];
                }
            } else {
                $results[] = ['name' => $info['name'], 'status' => 'failed', 'message' => 'Installation failed'];
            }
        }

        wp_send_json_success(['results' => $results]);
    }

    private function install_recommended_plugins() {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        if (!class_exists('Plugin_Upgrader')) {
            require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        }
        if (!function_exists('plugins_api')) {
            require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
        }

        $plugins = [
            'seo-by-rank-math/rank-math.php' => ['slug' => 'seo-by-rank-math', 'name' => 'Rank Math SEO'],
            'wp-smushit/wp-smush.php'        => ['slug' => 'wp-smushit', 'name' => 'Smush'],
            'wp-asset-clean-up/wpacu.php'    => ['slug' => 'wp-asset-clean-up', 'name' => 'Asset CleanUp'],
        ];

        $results = [];
        foreach ($plugins as $plugin_path => $info) {
            if (is_plugin_active($plugin_path)) {
                $results[] = $info['name'] . ': Already active';
                continue;
            }
            $api = plugins_api('plugin_information', ['slug' => $info['slug'], 'fields' => ['sections' => false]]);
            if (is_wp_error($api)) {
                $results[] = $info['name'] . ': Failed - ' . $api->get_error_message();
                continue;
            }
            $upgrader = new Plugin_Upgrader(new Automatic_Upgrader_Skin());
            if ($upgrader->install($api->download_link)) {
                $activated = activate_plugin($plugin_path);
                if (!is_wp_error($activated)) {
                    $results[] = $info['name'] . ': Installed & activated';
                } else {
                    $results[] = $info['name'] . ': Installed but activation failed';
                }
            } else {
                $results[] = $info['name'] . ': Installation failed';
            }
        }
        return $results;
    }

    private function auto_configure_rankmath() {
        if (!is_plugin_active('seo-by-rank-math/rank-math.php')) return;

        if (!get_option('rank_math_configured_by_az')) {
            update_option('rank_math_configured_by_az', true);

            update_option('rank_math_general_settings', [
                'homepage_title'        => get_bloginfo('name'),
                'homepage_description'  => get_bloginfo('description'),
                'homepage_facebook_author' => '',
            ]);

            update_option('rank-math-options-general', [
                'breadcrumbs'         => 'on',
                'disable_author_archives' => 'on',
                'sitemap_exclude_terms'  => [],
            ]);

            update_option('rank-math-options-titles', [
                'local_seo'                      => 'on',
                'knowledgegraph_type'            => 'person',
                'knowledgegraph_name'            => get_bloginfo('name'),
                'separator_output'               => '-',
                'homepage_title'                 => '%sitename% %separator% %sitedesc%',
                'homepage_description'           => get_bloginfo('description'),
                'homepage_custom_robots'         => 'on',
                'noindex_paginated_pages'        => 'on',
                'link_suggestions'               => 'on',
                'post_title'                     => '%title% %sep% %sitename%',
                'page_title'                     => '%title% %sep% %sitename%',
            ]);

            update_option('rank-math-options-sitemap', [
                'items_per_page'      => 200,
                'include_images'      => 'on',
                'exclude_post_types'  => [],
                'exclude_taxonomies'  => [],
            ]);

            AZ_Logger::log('Rank Math SEO configured automatically', 'SUCCESS');
        }
    }

    private function auto_configure_smush() {
        if (!is_plugin_active('wp-smushit/wp-smush.php')) return;

        if (!get_option('smush_configured_by_az')) {
            update_option('smush_configured_by_az', true);

            $smush_settings = WP_Smush::get_instance()->core()->settings;
            if ($smush_settings) {
                $settings = [
                    'auto'       => true,
                    'lossy'      => true,
                    'strip_exif' => true,
                    'resize'     => true,
                    'resize_sizes' => ['width' => 1920, 'height' => 1080],
                    'original'   => false,
                    'backup'     => true,
                    'png_to_jpg' => false,
                    'nextgen'    => false,
                    's3'         => false,
                    'detection'  => false,
                    'cdn'        => false,
                    'webp'       => true,
                    'webp_mod'   => false,
                ];

                $current = get_option('wp-smush-settings', []);
                foreach ($settings as $key => $val) {
                    $current[$key] = $val;
                }
                update_option('wp-smush-settings', $current);
            }

            AZ_Logger::log('Smush configured automatically', 'SUCCESS');
        }
    }

    public function ajax_auto_optimize() {
        $this->verify_nonce();

        $steps = [];
        $steps[] = 'Installing recommended plugins...';
        $plugin_results = [];
        if (current_user_can('install_plugins')) {
            $plugin_results = $this->install_recommended_plugins();
        }
        $steps[] = 'Plugins done: ' . implode(' | ', $plugin_results);

        $steps[] = 'Configuring Rank Math SEO...';
        $this->auto_configure_rankmath();
        $steps[] = 'Rank Math configured';

        $steps[] = 'Configuring Smush...';
        $this->auto_configure_smush();
        $steps[] = 'Smush configured';

        $steps[] = 'Running fixes...';
        $fixer = new AZ_Fixer();
        $fix_methods = [
            'fix_security_headers', 'fix_https_redirect', 'fix_robots_txt',
            'fix_sitemap', 'fix_gzip_compression', 'fix_xml_rpc',
            'fix_file_permissions', 'fix_memory_limit', 'fix_heartbeat',
            'fix_database_bloat', 'fix_render_blocking', 'fix_image_alt',
            'fix_color_contrast', 'fix_touch_targets', 'fix_skip_link',
            'fix_main_landmark', 'fix_focusable_elements', 'fix_aria_labels',
            'fix_orphaned_meta', 'fix_post_drafts',
        ];

        $fixed = 0; $failed_fixes = 0;
        foreach ($fix_methods as $method) {
            $result = $fixer->fix(str_replace('fix_', '', $method));
            if ($result['success']) $fixed++; else $failed_fixes++;
        }
        $steps[] = "Fixes: {$fixed} applied, {$failed_fixes} skipped";

        $steps[] = 'Regenerating sitemap...';
        $fixer->fix('sitemap');
        $steps[] = 'Sitemap generated';

        $steps[] = 'Cleaning junk files...';
        foreach ([ABSPATH . 'readme.html', ABSPATH . 'license.txt'] as $f) {
            if (file_exists($f)) @unlink($f);
        }
        $steps[] = 'Junk cleaned';

        AZ_Logger::log('Full auto-optimize completed', 'SUCCESS');

        wp_send_json_success([
            'message' => 'Site fully optimized!',
            'steps'   => $steps,
            'fixed'   => $fixed,
        ]);
    }

    public function ajax_clean_junk() {
        $this->verify_nonce();
        $files = [ABSPATH . 'readme.html', ABSPATH . 'license.txt'];
        $cleaned = [];
        $failed = [];
        foreach ($files as $file) {
            if (file_exists($file)) {
                unlink($file) ? $cleaned[] = basename($file) : $failed[] = basename($file);
            }
        }
        if (!empty($cleaned)) {
            AZ_Logger::log('Junk files cleaned: ' . implode(', ', $cleaned), 'INFO');
        }
        wp_send_json_success(['cleaned' => $cleaned, 'failed' => $failed, 'message' => 'Cleaned ' . count($cleaned) . ' files']);
    }

    public function ajax_optimize_database() {
        $this->verify_nonce();
        $fixer = new AZ_Fixer();
        $result = $fixer->fix_database_bloat();
        wp_send_json($result['success'] ? wp_send_json_success($result) : wp_send_json_error($result));
    }

    public function ajax_get_logs() {
        $this->verify_nonce();
        $log_file = AZ_LOG_FILE;
        if (!file_exists($log_file)) {
            wp_send_json_success(['logs' => 'No logs available yet.']);
        }
        $contents = file_exists($log_file) ? file_get_contents($log_file) : '';
        if (strlen($contents) > 500000) {
            $contents = substr($contents, -500000) . "\n... [Truncated]";
        }
        wp_send_json_success(['logs' => esc_textarea($contents)]);
    }

    public function ajax_get_server_info() {
        $this->verify_nonce();
        $scanner = new AZ_Scanner();
        wp_send_json_success(['server_info' => $scanner->get_server_info()]);
    }

    public function ajax_get_health_history() {
        $this->verify_nonce();
        $scanner = new AZ_Scanner();
        wp_send_json_success(['history' => $scanner->get_health_history()]);
    }

    public function ajax_export_report() {
        $this->verify_nonce();
        $format = isset($_POST['format']) ? sanitize_text_field($_POST['format']) : 'csv';

        $scanner = new AZ_Scanner();
        $issues = $scanner->full_scan();
        $health_score = $scanner->get_health_score();
        $server_info = $scanner->get_server_info();

        if ($format === 'csv') {
            $filename = 'az-report-' . current_time('Y-m-d') . '.csv';
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            $output = fopen('php://output', 'w');

            fputcsv($output, ['Health Score: ' . $health_score]);
            fputcsv($output, ['']);
            fputcsv($output, ['Type', 'Severity', 'Title', 'Description', 'Auto-Fix']);

            foreach ($issues as $issue) {
                fputcsv($output, [$issue['type'], $issue['severity'], $issue['title'], $issue['description'], $issue['auto_fix'] ? 'Yes' : 'No']);
            }

            fputcsv($output, ['']);
            fputcsv($output, ['Server Info']);
            fputcsv($output, ['PHP', $server_info['php_version']]);
            fputcsv($output, ['MySQL', $server_info['mysql_version']]);
            fputcsv($output, ['WP', $server_info['wp_version']]);
            fputcsv($output, ['Memory Limit', $server_info['wp_memory_limit']]);

            fclose($output);
            exit;
        }

        wp_send_json_success(['message' => 'Unsupported format']);
    }

    public function ajax_send_report_email() {
        $this->verify_nonce();
        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        if (!is_email($email)) {
            wp_send_json_error(['message' => 'Invalid email address']);
        }

        $scanner = new AZ_Scanner();
        $issues = $scanner->full_scan();
        $health_score = $scanner->get_health_score();

        $subject = 'SEO/Performance Report - ' . get_bloginfo('name') . ' - ' . current_time('Y-m-d');
        $message = 'Site Health Score: ' . $health_score . '/100' . "\n\n";
        $message .= 'Issues Found: ' . count($issues) . "\n\n";

        $counts = ['critical' => 0, 'high' => 0, 'medium' => 0, 'low' => 0];
        foreach ($issues as $issue) {
            if (isset($counts[$issue['severity']])) {
                $counts[$issue['severity']]++;
            }
        }
        $message .= 'Critical: ' . $counts['critical'] . ' | High: ' . $counts['high'] . ' | Medium: ' . $counts['medium'] . ' | Low: ' . $counts['low'] . "\n\n";
        $message .= 'Dashboard: ' . admin_url('admin.php?page=az-dashboard');

        $sent = wp_mail($email, $subject, $message);
        if ($sent) {
            AZ_Logger::log('Report emailed to ' . $email, 'SUCCESS');
            wp_send_json_success(['message' => 'Report sent to ' . $email]);
        } else {
            wp_send_json_error(['message' => 'Failed to send email. Check SMTP configuration.']);
        }
    }

    public function ajax_save_schedule() {
        $this->verify_nonce();
        $enabled = isset($_POST['enabled']) ? (bool) $_POST['enabled'] : false;
        $frequency = isset($_POST['frequency']) ? sanitize_text_field($_POST['frequency']) : 'daily';
        $email_alerts = isset($_POST['email_alerts']) ? (bool) $_POST['email_alerts'] : false;
        $alert_email = isset($_POST['alert_email']) ? sanitize_email($_POST['alert_email']) : get_option('admin_email');

        update_option('az_scheduled_scan_enabled', $enabled);
        update_option('az_scheduled_scan_frequency', $frequency);
        update_option('az_email_alerts', $email_alerts);
        update_option('az_alert_email', $alert_email);

        $hook = 'az_scheduled_scan';
        $timestamp = wp_next_scheduled($hook);
        if ($enabled) {
            $schedules = ['hourly' => HOUR_IN_SECONDS, 'daily' => DAY_IN_SECONDS, 'weekly' => WEEK_IN_SECONDS];
            $interval = isset($schedules[$frequency]) ? $schedules[$frequency] : DAY_IN_SECONDS;

            if ($timestamp) {
                wp_unschedule_event($timestamp, $hook);
            }
            wp_schedule_event(time() + $interval, $frequency, $hook);
            AZ_Logger::log('Scheduled scan enabled: ' . $frequency, 'INFO');
        } else {
            if ($timestamp) {
                wp_unschedule_event($timestamp, $hook);
            }
            AZ_Logger::log('Scheduled scan disabled', 'INFO');
        }

        wp_send_json_success(['message' => 'Schedule settings saved']);
    }

    public function run_scheduled_scan() {
        $scanner = new AZ_Scanner();
        $issues = $scanner->full_scan();
        $health_score = $scanner->get_health_score();

        AZ_Logger::log('Scheduled scan: ' . count($issues) . ' issues (Score: ' . $health_score . ')', 'INFO');

        $critical_issues = array_filter($issues, function ($i) {
            return $i['severity'] === 'critical';
        });

        if (!empty($critical_issues) && get_option('az_email_alerts', false)) {
            $to = get_option('az_alert_email', get_option('admin_email'));
            $subject = '[AZ Optimizer] Critical Issues Detected - ' . get_bloginfo('name');
            $message = 'Scheduled scan found ' . count($critical_issues) . ' critical issue(s):' . "\n\n";
            foreach ($critical_issues as $issue) {
                $message .= '- ' . $issue['title'] . "\n  " . $issue['description'] . "\n\n";
            }
            $message .= 'Health Score: ' . $health_score . '/100' . "\n";
            $message .= 'Dashboard: ' . admin_url('admin.php?page=az-dashboard');
            wp_mail($to, $subject, $message);
        }
    }

    public function show_critical_notice() {
        $screen = get_current_screen();
        if ($screen && $screen->id === 'toplevel_page_az-dashboard') {
            return;
        }

        $last_results = get_option('az_last_scan_results');
        if (!$last_results || empty($last_results['issues'])) {
            return;
        }

        $critical_count = 0;
        foreach ($last_results['issues'] as $issue) {
            if ($issue['severity'] === 'critical') {
                $critical_count++;
            }
        }

        if ($critical_count > 0) {
            $score = $last_results['score'] ?? 'N/A';
            echo '<div class="notice notice-error is-dismissible">';
            echo '<p><strong>AZ Optimizer:</strong> ' . $critical_count . ' critical issue(s) found. ';
            echo 'Site health score: ' . $score . '/100. ';
            echo '<a href="' . admin_url('admin.php?page=az-dashboard') . '">View Dashboard</a></p>';
            echo '</div>';
        }

        $last_scan = get_option('az_last_scan_time');
        if ($last_scan && strtotime($last_scan) < strtotime('-7 days')) {
            echo '<div class="notice notice-warning is-dismissible">';
            echo '<p><strong>AZ Optimizer:</strong> Last scan was ' . human_time_diff(strtotime($last_scan)) . ' ago. ';
            echo '<a href="' . admin_url('admin.php?page=az-dashboard') . '">Run a new scan</a></p>';
            echo '</div>';
        }
    }
}
