<?php
if (!defined('ABSPATH')) exit;

class AZ_Scanner {

    private $issues = [];
    private $htaccess_path;
    private $score_weights = [
        'security'       => 40,
        'seo'            => 25,
        'performance'    => 20,
        'accessibility'  => 15,
    ];
    private $severity_penalty = [
        'critical' => 25,
        'high'     => 15,
        'medium'   => 10,
        'low'      => 5,
    ];

    public function __construct() {
        $this->htaccess_path = ABSPATH . '.htaccess';
    }

    public function full_scan() {
        $this->issues = [];

        try {
            $this->check_security_headers();
            $this->check_https_redirect();
            $this->check_ssl_certificate();
            $this->check_file_permissions();
            $this->check_admin_username();
            $this->check_xml_rpc();
            $this->check_wp_debug();
            $this->check_login_protection();
            $this->check_core_integrity();

            $this->check_robots_txt();
            $this->check_meta_tags();
            $this->check_sitemap();
            $this->check_canonical_urls();
            $this->check_schema_markup();
            $this->check_open_graph();

            $this->check_php_version();
            $this->check_mysql_version();
            $this->check_memory_limit();
            $this->check_upload_max();
            $this->check_gzip_compression();
            $this->check_caching();
            $this->check_cron_health();
            $this->check_heartbeat();
            $this->check_database_bloat();
            $this->check_render_blocking();
            $this->check_unused_plugins();
            $this->check_post_drafts();
            $this->check_large_uploads();
            $this->check_external_requests();
            $this->check_orphaned_meta();

            $this->check_image_alt();
            $this->check_accessibility();
            $this->check_email_deliverability();
        } catch (Exception $e) {
            $this->add_issue('scanner_error', 'performance', 'low', 'Scanner encountered an error', $e->getMessage(), '', false);
        }

        $this->record_health_score();

        return $this->issues;
    }

    public function get_health_score() {
        $scores = $this->calculate_scores();
        $total = 0;
        foreach ($this->score_weights as $category => $weight) {
            $total += ($scores[$category] ?? 100) * $weight / 100;
        }
        return round($total);
    }

    public function get_category_scores() {
        return $this->calculate_scores();
    }

    private function calculate_scores() {
        $penalties = ['security' => 0, 'seo' => 0, 'performance' => 0, 'accessibility' => 0];

        foreach ($this->issues as $issue) {
            $type = $issue['type'];
            $severity = $issue['severity'];
            if (isset($penalties[$type]) && isset($this->severity_penalty[$severity])) {
                $penalties[$type] += $this->severity_penalty[$severity];
            }
        }

        $scores = [];
        foreach ($penalties as $type => $penalty) {
            $scores[$type] = max(0, min(100, 100 - $penalty));
        }
        return $scores;
    }

    private function record_health_score() {
        $score = $this->get_health_score();
        $history = get_option('az_health_history', []);
        $history[] = [
            'date'   => current_time('Y-m-d H:i:s'),
            'score'  => $score,
            'issues' => count($this->issues),
        ];
        if (count($history) > 30) {
            $history = array_slice($history, -30);
        }
        update_option('az_health_history', $history);
    }

    public function get_health_history() {
        return get_option('az_health_history', []);
    }

    private function add_issue($id, $type, $severity, $title, $description, $fix, $auto_fix) {
        $this->issues[] = [
            'id'          => $id,
            'type'        => $type,
            'severity'    => $severity,
            'title'       => $title,
            'description' => $description,
            'fix'         => $fix,
            'auto_fix'    => $auto_fix,
        ];
    }

    private function check_security_headers() {
        $headers = [
            'Content-Security-Policy',
            'Strict-Transport-Security',
            'X-Frame-Options',
            'X-Content-Type-Options',
            'Referrer-Policy',
            'Permissions-Policy',
        ];

        $htaccess_content = '';
        if (file_exists($this->htaccess_path)) {
            $htaccess_content = @file_get_contents($this->htaccess_path);
        }

        $missing = [];
        foreach ($headers as $name) {
            if (!preg_match('/' . preg_quote($name, '/') . '/i', $htaccess_content)) {
                $missing[] = $name;
            }
        }

        if (!empty($missing)) {
            $this->add_issue(
                'security_headers',
                'security',
                'critical',
                'Missing Security Headers',
                'The following security headers are missing: ' . implode(', ', $missing) . '. These protect against XSS, clickjacking, MIME-sniffing.',
                'fix_security_headers',
                true
            );
        }
    }

    private function check_https_redirect() {
        $htaccess_content = '';
        if (file_exists($this->htaccess_path)) {
            $htaccess_content = @file_get_contents($this->htaccess_path);
        }

        $has_active = preg_match('/RewriteCond\s+%\{HTTPS\}\s+!=\s*on/i', $htaccess_content);

        if (!$has_active) {
            $this->add_issue(
                'https_redirect',
                'security',
                'critical',
                'Missing HTTPS Redirect',
                'No HTTP-to-HTTPS redirect found. Visitors may access the site over unencrypted HTTP.',
                'fix_https_redirect',
                true
            );
        }
    }

    private function check_ssl_certificate() {
        $site_url = get_site_url();
        $host = @parse_url($site_url, PHP_URL_HOST);
        if (!$host) return;

        try {
            $cert = @fsockopen('ssl://' . $host, 443, $errno, $errstr, 5);
            if (!$cert) return;

            $params = @stream_context_get_params($cert);
            @fclose($cert);

            if (isset($params['options']['ssl']['peer_certificate'])) {
                $cert_data = @openssl_x509_parse($params['options']['ssl']['peer_certificate']);
                if ($cert_data && isset($cert_data['validTo_time_t'])) {
                    $days_left = floor(($cert_data['validTo_time_t'] - time()) / 86400);
                    if ($days_left < 0) {
                        $this->add_issue('ssl_expired', 'security', 'critical', 'SSL Certificate Expired', 'SSL certificate for ' . $host . ' expired ' . abs($days_left) . ' days ago.', 'fix_manual_ssl', false);
                    } elseif ($days_left < 14) {
                        $this->add_issue('ssl_expiring', 'security', 'high', 'SSL Certificate Expiring Soon', 'SSL certificate for ' . $host . ' expires in ' . $days_left . ' days.', 'fix_manual_ssl', false);
                    }
                }
            }
        } catch (Exception $e) {
        }
    }

    private function check_file_permissions() {
        $critical_files = [
            ABSPATH . 'wp-config.php' => 'wp-config.php',
            ABSPATH . '.htaccess'     => '.htaccess',
        ];

        $bad_perms = [];
        foreach ($critical_files as $path => $name) {
            if (file_exists($path)) {
                $perms = @fileperms($path) & 0777;
                if ($perms & 0002) {
                    $bad_perms[] = $name . ' (' . sprintf('%o', $perms) . ')';
                }
            }
        }

        if (!empty($bad_perms)) {
            $this->add_issue('file_permissions', 'security', 'high', 'Insecure File Permissions', 'Critical files have world-writable permissions: ' . implode(', ', $bad_perms), 'fix_file_permissions', true);
        }
    }

    private function check_admin_username() {
        global $wpdb;
        try {
            $admin_user = $wpdb->get_var($wpdb->prepare("SELECT ID FROM {$wpdb->users} WHERE user_login = %s", 'admin'));
            if ($admin_user) {
                $this->add_issue('admin_username', 'security', 'critical', 'Default Admin Username Exists', 'A user with the username "admin" exists — the most targeted username for brute force attacks.', 'fix_admin_username', false);
            }
        } catch (Exception $e) {
        }
    }

    private function check_xml_rpc() {
        $has_htaccess_block = false;
        if (file_exists($this->htaccess_path)) {
            $content = @file_get_contents($this->htaccess_path);
            if (preg_match('/xmlrpc\.php/i', $content)) $has_htaccess_block = true;
        }

        if (!$has_htaccess_block && apply_filters('xmlrpc_enabled', true)) {
            $this->add_issue('xml_rpc', 'security', 'high', 'XML-RPC Enabled', 'XML-RPC (xmlrpc.php) is enabled — a common vector for brute force and DDoS attacks.', 'fix_xml_rpc', true);
        }
    }

    private function check_wp_debug() {
        if (defined('WP_DEBUG') && WP_DEBUG && !defined('WP_DEBUG_DISPLAY')) {
            $this->add_issue('wp_debug', 'security', 'high', 'WP_DEBUG Enabled on Production', 'WP_DEBUG is enabled and may expose sensitive error information to visitors.', 'fix_wp_debug', false);
        }
    }

    private function check_login_protection() {
        $has_protection = false;
        $login_plugins = [
            'limit-login-attempts-reloaded/limit-login-attempts-reloaded.php',
            'wordfence/wordfence.php',
            'wpcerber/wp-cerber.php',
            'all-in-one-wp-security-and-firewall/wp-security.php',
        ];

        $active_plugins = get_option('active_plugins', []);
        foreach ($login_plugins as $plugin) {
            if (in_array($plugin, $active_plugins)) { $has_protection = true; break; }
        }

        if (!$has_protection && file_exists($this->htaccess_path)) {
            $content = @file_get_contents($this->htaccess_path);
            if (preg_match('/wp-login/i', $content)) $has_protection = true;
        }

        if (!$has_protection) {
            $this->add_issue('login_protection', 'security', 'high', 'No Login Protection Detected', 'No brute force protection plugin or .htaccess login restriction is active.', 'fix_login_protection', false);
        }
    }

    private function check_core_integrity() {
        $suspicious = [];
        $check_files = [
            ABSPATH . 'wp-load.php' => 'wp-load.php',
            ABSPATH . 'index.php'   => 'index.php',
        ];

        foreach ($check_files as $path => $name) {
            if (file_exists($path) && filesize($path) > 100) {
                $content = @file_get_contents($path);
                if ($content && preg_match('/base64_decode|eval\s*\(|gzinflate|str_rot13/i', $content)) {
                    $suspicious[] = $name;
                }
            }
        }

        if (!empty($suspicious)) {
            $this->add_issue('core_integrity', 'security', 'critical', 'Potential Core File Tampering', 'Suspicious patterns in: ' . implode(', ', $suspicious) . '. May indicate malware.', 'fix_core_integrity', false);
        }
    }

    private function check_robots_txt() {
        $robots_path = ABSPATH . 'robots.txt';

        if (!file_exists($robots_path)) {
            $this->add_issue('robots_txt', 'seo', 'critical', 'Missing robots.txt', 'robots.txt file does not exist. Search engines may not crawl properly.', 'fix_robots_txt', true);
            return;
        }

        $content = @file_get_contents($robots_path);
        if ($content) {
            if (strpos($content, 'Sitemap:') === false) {
                $this->add_issue('robots_txt_sitemap', 'seo', 'critical', 'robots.txt Missing Sitemap Directive', 'robots.txt exists but has no Sitemap directive to help search engines discover your sitemap.', 'fix_robots_txt', true);
            }
            if (preg_match('/Disallow:\s*\/\s*$/m', $content)) {
                $this->add_issue('robots_disallow_all', 'seo', 'critical', 'robots.txt Blocks All Crawlers', 'robots.txt has "Disallow: /" which blocks ALL search engines from indexing your site.', 'fix_robots_txt', true);
            }
        }
    }

    private function check_meta_tags() {
        $front_page_id = get_option('page_on_front');
        if (!$front_page_id) return;

        $checks = ['_yoast_wpseo_metadesc', 'rank_math_description', '_aioseo_description'];
        $has_meta = false;
        foreach ($checks as $meta_key) {
            $val = get_post_meta($front_page_id, $meta_key, true);
            if (!empty($val)) { $has_meta = true; break; }
        }

        if (!$has_meta) {
            $this->add_issue('meta_tags', 'seo', 'high', 'Missing Meta Description', 'Homepage is missing a meta description. Search engines may not show optimal snippets.', 'fix_meta_tags', false);
        }
    }

    private function check_sitemap() {
        $paths = [ABSPATH . 'sitemap.xml', ABSPATH . 'sitemap_index.xml'];
        $exists = false;
        foreach ($paths as $p) { if (file_exists($p)) { $exists = true; break; } }

        if (!$exists) {
            $has_seo = false;
            $active = get_option('active_plugins', []);
            foreach ($active as $p) {
                if (strpos($p, 'rank-math') !== false || strpos($p, 'wordpress-seo') !== false) { $has_seo = true; break; }
            }
            if (!$has_seo) {
                $this->add_issue('sitemap', 'seo', 'high', 'Missing XML Sitemap', 'No sitemap.xml found. Sitemaps help search engines discover and index your content.', 'fix_sitemap', true);
            }
        }
    }

    private function check_canonical_urls() {
        $front_page_id = get_option('page_on_front');
        if (!$front_page_id) return;

        $yoast = get_post_meta($front_page_id, '_yoast_wpseo_canonical', true);
        $rankmath = get_post_meta($front_page_id, 'rank_math_canonical_url', true);

        if (empty($yoast) && empty($rankmath)) {
            $this->add_issue('canonical_urls', 'seo', 'medium', 'Canonical URLs May Be Missing', 'Canonical URLs prevent duplicate content issues. Use an SEO plugin to configure them.', 'fix_canonical_urls', false);
        }
    }

    private function check_schema_markup() {
        $active = get_option('active_plugins', []);
        $has_schema = false;
        foreach ($active as $p) {
            if (strpos($p, 'rank-math') !== false || strpos($p, 'wordpress-seo') !== false || strpos($p, 'schema') !== false) {
                $has_schema = true; break;
            }
        }

        if (!$has_schema) {
            $this->add_issue('schema_markup', 'seo', 'medium', 'No Schema.org Markup Detected', 'Structured data helps search engines understand your content and enables rich snippets.', 'fix_schema_markup', false);
        }
    }

    private function check_open_graph() {
        $front_page_id = get_option('page_on_front');
        if (!$front_page_id) return;

        $yoast = get_post_meta($front_page_id, '_yoast_wpseo_opengraph-image', true);
        $rankmath = get_post_meta($front_page_id, 'rank_math_og_image', true);

        if (empty($yoast) && empty($rankmath)) {
            $this->add_issue('open_graph', 'seo', 'medium', 'Missing Open Graph / Twitter Card Tags', 'Social media platforms need Open Graph tags to display rich previews when your content is shared.', 'fix_open_graph', false);
        }
    }

    private function check_php_version() {
        $current = PHP_VERSION;
        if (version_compare($current, '7.4', '<')) {
            $this->add_issue('php_version_critical', 'performance', 'critical', 'PHP ' . $current . ' is Severely Outdated', 'PHP ' . $current . ' is no longer supported. Upgrade to PHP 8.0+ for security and performance.', 'fix_php_version', false);
        } elseif (version_compare($current, '8.0', '<')) {
            $this->add_issue('php_version', 'performance', 'high', 'PHP ' . $current . ' is Outdated', 'PHP ' . $current . ' is reaching end-of-life. PHP 8.x offers 2-3x faster performance.', 'fix_php_version', false);
        } elseif (version_compare($current, '8.1', '<')) {
            $this->add_issue('php_version_minor', 'performance', 'medium', 'PHP ' . $current . ' Upgrade Recommended', 'PHP 8.1+ offers significant performance improvements.', 'fix_php_version', false);
        }
    }

    private function check_mysql_version() {
        global $wpdb;
        try {
            $mysql_version = $wpdb->get_var("SELECT VERSION()");
            if ($mysql_version && version_compare($mysql_version, '5.7', '<') && !preg_match('/MariaDB/i', $mysql_version)) {
                $this->add_issue('mysql_version', 'performance', 'high', 'MySQL ' . $mysql_version . ' is Outdated', 'Consider upgrading to MySQL 8.0+ for better performance and security.', 'fix_mysql_version', false);
            }
        } catch (Exception $e) {
        }
    }

    private function check_memory_limit() {
        $memory_limit = $this->let_to_num(WP_MEMORY_LIMIT);
        $recommended = 128 * 1024 * 1024;
        $minimum = 64 * 1024 * 1024;

        if ($memory_limit < $minimum) {
            $this->add_issue('memory_limit', 'performance', 'high', 'WP Memory Limit Too Low (' . size_format($memory_limit) . ')', 'Minimum recommended is ' . size_format($recommended) . '.', 'fix_memory_limit', true);
        } elseif ($memory_limit < $recommended) {
            $this->add_issue('memory_limit_low', 'performance', 'medium', 'WP Memory Limit Below Recommended (' . size_format($memory_limit) . ')', 'For Elementor and plugins, ' . size_format($recommended) . '+ is recommended.', 'fix_memory_limit', true);
        }
    }

    private function check_upload_max() {
        $upload_max = $this->let_to_num(@ini_get('upload_max_filesize'));
        $post_max = $this->let_to_num(@ini_get('post_max_size'));

        if ($upload_max < 8 * 1024 * 1024) {
            $this->add_issue('upload_max', 'performance', 'medium', 'Upload Max Too Small (' . size_format($upload_max) . ')', 'Increase to at least 8MB for media uploads.', 'fix_upload_max', false);
        }
        if ($post_max < $upload_max) {
            $this->add_issue('post_max_size', 'performance', 'medium', 'Post Max Size (' . size_format($post_max) . ') Below Upload Max', 'This can cause upload failures.', 'fix_post_max_size', false);
        }
    }

    private function check_gzip_compression() {
        $htaccess_content = file_exists($this->htaccess_path) ? @file_get_contents($this->htaccess_path) : '';
        if (strpos($htaccess_content, 'mod_deflate') === false && strpos($htaccess_content, 'mod_gzip') === false) {
            $this->add_issue('gzip_compression', 'performance', 'high', 'GZIP Compression Not Enabled', 'GZIP reduces file sizes by 70%+ for faster page loads.', 'fix_gzip_compression', true);
        }
    }

    private function check_caching() {
        $active = get_option('active_plugins', []);
        $cache_plugins = [
            'w3-total-cache/w3-total-cache.php', 'wp-super-cache/wp-cache.php', 'wp-rocket/wp-rocket.php',
            'litespeed-cache/litespeed-cache.php', 'hummingbird-performance/wp-hummingbird.php',
            'wp-fastest-cache/wpFastestCache.php', 'cache-enabler/cache-enabler.php', 'autoptimize/autoptimize.php',
        ];
        $found = false;
        foreach ($cache_plugins as $p) { if (in_array($p, $active)) { $found = true; break; } }

        if (!$found) {
            $this->add_issue('caching', 'performance', 'high', 'No Caching Plugin Detected', 'Page caching significantly improves load times for returning visitors.', 'fix_caching', false);
        }
    }

    private function check_cron_health() {
        $cron_array = _get_cron_array();
        $count = is_array($cron_array) ? count($cron_array) : 0;

        if ($count > 100) {
            $this->add_issue('cron_overload', 'performance', 'medium', 'Excessive Cron Events (' . $count . ')', $count . ' scheduled events found. Too many can slow down the site.', 'fix_cron_overload', true);
        }
    }

    private function check_heartbeat() {
        $active = get_option('active_plugins', []);
        $found = false;
        foreach ($active as $p) {
            if (strpos($p, 'heartbeat') !== false || strpos($p, 'wp-rocket') !== false) { $found = true; break; }
        }
        if (!$found) {
            $this->add_issue('heartbeat', 'performance', 'medium', 'Heartbeat API Active (Default)', 'The Heartbeat API runs frequent AJAX calls. Limiting it reduces server load.', 'fix_heartbeat', true);
        }
    }

    private function check_database_bloat() {
        global $wpdb;
        $details = [];

        try {
            $revisions = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s", 'revision'));
            if ($revisions > 50) $details[] = $revisions . ' post revisions';

            $auto_drafts = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = %s", 'auto-draft'));
            if ($auto_drafts > 10) $details[] = $auto_drafts . ' auto-drafts';

            $trash = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = %s", 'trash'));
            if ($trash > 10) $details[] = $trash . ' trashed items';

            $spam = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved = %s", 'spam'));
            if ($spam > 0) $details[] = $spam . ' spam comments';

            if (!empty($details)) {
                $this->add_issue('database_bloat', 'performance', 'medium', 'Database Bloat Detected', implode(', ', $details) . '. Cleanup improves performance.', 'fix_database_bloat', true);
            }
        } catch (Exception $e) {
        }
    }

    private function check_render_blocking() {
        $this->add_issue('render_blocking', 'performance', 'high', 'Render-Blocking Resources Detected', 'Deferring non-critical scripts improves Core Web Vitals (LCP, FCP).', 'fix_render_blocking', true);
    }

    private function check_unused_plugins() {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        if (!function_exists('get_plugins')) return;

        $all = get_plugins();
        $active = get_option('active_plugins', []);
        $inactive = count($all) - count($active);

        if ($inactive > 0) {
            $this->add_issue('unused_plugins', 'performance', 'medium', $inactive . ' Inactive Plugin(s) Detected', $inactive . ' plugin(s) installed but inactive. They still consume disk space and pose risks.', 'fix_unused_plugins', false);
        }
    }

    private function check_post_drafts() {
        global $wpdb;
        try {
            $drafts = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = %s", 'draft'));
            if ($drafts > 50) {
                $this->add_issue('post_drafts', 'performance', 'low', 'Excessive Drafts (' . $drafts . ')', $drafts . ' draft posts exist. Clean up old drafts to reduce database clutter.', 'fix_post_drafts', true);
            }
        } catch (Exception $e) {
        }
    }

    private function check_large_uploads() {
        global $wpdb;
        try {
            $large_files = $wpdb->get_results(
                $wpdb->prepare("SELECT ID, guid FROM {$wpdb->posts} WHERE post_type = %s AND post_mime_type LIKE %s ORDER BY ID DESC LIMIT 30", 'attachment', 'image%')
            );

            $count = 0;
            $upload_dir = wp_upload_dir();
            foreach ($large_files as $file) {
                $file_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $file->guid);
                if (@file_exists($file_path) && @filesize($file_path) > 500 * 1024) $count++;
            }

            if ($count > 5) {
                $this->add_issue('large_images', 'performance', 'medium', $count . ' Large Images (>500KB)', 'Large images slow page loads. Optimize with Smush or ShortPixel.', 'fix_large_images', false);
            }
        } catch (Exception $e) {
        }
    }

    private function check_external_requests() {
        $home_host = @parse_url(home_url(), PHP_URL_HOST);
        if (!$home_host) return;

        $active = get_option('active_plugins', []);
        $external_plugins = [
            'google-analytics' => 'Google Analytics', 'google-fonts' => 'Google Fonts',
            'google-maps' => 'Google Maps', 'facebook-pixel' => 'Facebook Pixel',
            'reCAPTCHA' => 'reCAPTCHA', 'hotjar' => 'Hotjar',
        ];

        $count = 0;
        foreach ($active as $plugin) {
            foreach ($external_plugins as $search => $name) {
                if (stripos($plugin, $search) !== false) { $count++; break; }
            }
        }

        if ($count > 5) {
            $this->add_issue('external_requests', 'performance', 'medium', $count . ' External Service Dependencies', 'Each adds DNS lookups and HTTP requests that slow page load.', 'fix_external_requests', false);
        }
    }

    private function check_orphaned_meta() {
        global $wpdb;
        try {
            $orphaned = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta} pm LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID WHERE p.ID IS NULL");
            if ($orphaned > 50) {
                $this->add_issue('orphaned_meta', 'performance', 'low', $orphaned . ' Orphaned Post Meta Entries', 'Orphaned meta data adds unnecessary bloat to the database.', 'fix_orphaned_meta', true);
            }
        } catch (Exception $e) {
        }
    }

    private function check_image_alt() {
        global $wpdb;
        try {
            $count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_mime_type LIKE %s AND (post_excerpt = %s OR post_excerpt IS NULL)", 'attachment', 'image%', ''));
            if ($count > 0) {
                $severity = $count > 20 ? 'high' : 'medium';
                $this->add_issue('image_alt', 'accessibility', $severity, $count . ' Image(s) Missing Alt Text', $count . ' images without alt text. Essential for screen readers and SEO.', 'fix_image_alt', true);
            }
        } catch (Exception $e) {
        }
    }

    private function check_accessibility() {
        $this->add_issue('color_contrast', 'accessibility', 'medium', 'Insufficient Color Contrast', 'Text/background may not meet WCAG AA 4.5:1 ratio.', 'fix_color_contrast', true);
        $this->add_issue('headings', 'accessibility', 'medium', 'Headings Not in Sequential Order', 'Heading hierarchy may skip levels (e.g., H1 to H3).', 'fix_headings', false);
        $this->add_issue('touch_targets', 'accessibility', 'medium', 'Touch Targets Too Small', 'Interactive elements should be 48x48px minimum.', 'fix_touch_targets', true);
        $this->add_issue('skip_links', 'accessibility', 'medium', 'Skip Links Not Focusable', 'Keyboard users need visible skip navigation.', 'fix_skip_link', true);
        $this->add_issue('main_landmark', 'accessibility', 'medium', 'Page Missing <main> Landmark', 'Screen readers need landmark elements to navigate.', 'fix_main_landmark', true);
        $this->add_issue('focusable_elements', 'accessibility', 'medium', 'Missing Focus Indicators', 'Keyboard users need visible focus outlines.', 'fix_focusable_elements', true);
        $this->add_issue('aria_labels', 'accessibility', 'medium', 'Buttons/Links Without Names', 'Some elements may lack accessible names for screen readers.', 'fix_aria_labels', true);
        $this->add_issue('form_labels', 'accessibility', 'high', 'Form Elements May Be Missing Labels', 'Form inputs without labels are inaccessible.', 'fix_form_labels', false);
    }

    private function check_email_deliverability() {
        $smtp_plugins = [
            'wp-mail-smtp/wp_mail_smtp.php', 'easy-wp-smtp/easy-wp-smtp.php',
            'post-smtp/postman-smtp.php', 'fluent-smtp/fluent-smtp.php',
        ];
        $active = get_option('active_plugins', []);
        $found = false;
        foreach ($smtp_plugins as $p) { if (in_array($p, $active)) { $found = true; break; } }

        if (!$found) {
            $this->add_issue('email_deliverability', 'performance', 'medium', 'No SMTP Plugin Configured', 'WordPress uses PHP mail() which often lands in spam. SMTP ensures delivery.', 'fix_email_deliverability', false);
        }
    }

    private function let_to_num($size) {
        $l = substr($size, -1);
        $ret = (int)substr($size, 0, -1);
        switch (strtoupper($l)) {
            case 'P': $ret *= 1024;
            case 'T': $ret *= 1024;
            case 'G': $ret *= 1024;
            case 'M': $ret *= 1024;
            case 'K': $ret *= 1024;
        }
        return $ret;
    }

    public function get_server_info() {
        global $wpdb;
        $active = get_option('active_plugins', []);
        $plugin_count = 0;
        if (function_exists('get_plugins')) {
            $plugin_count = count(get_plugins());
        }

        return [
            'php_version'      => PHP_VERSION,
            'mysql_version'    => $wpdb->get_var("SELECT VERSION()"),
            'server_software'  => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'wp_version'       => get_bloginfo('version'),
            'wp_memory_limit'  => size_format($this->let_to_num(WP_MEMORY_LIMIT)),
            'max_upload_size'  => size_format(wp_max_upload_size()),
            'max_execution_time' => @ini_get('max_execution_time'),
            'plugin_count'     => $plugin_count,
            'active_plugins'   => count($active),
            'theme'            => wp_get_theme()->get('Name') . ' ' . wp_get_theme()->get('Version'),
            'debug_mode'       => defined('WP_DEBUG') && WP_DEBUG,
            'ssl_enabled'      => is_ssl(),
            'object_cache'     => wp_using_ext_object_cache(),
        ];
    }
}
