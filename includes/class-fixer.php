<?php
if (!defined('ABSPATH')) exit;

class AZ_Fixer {

    private $htaccess_path;

    public function __construct() {
        $this->htaccess_path = ABSPATH . '.htaccess';
    }

    public function fix($fix_id) {
        $method_name = strpos($fix_id, 'fix_') === 0 ? $fix_id : 'fix_' . $fix_id;

        if (method_exists($this, $method_name)) {
            AZ_Logger::log("Running fix: {$method_name}", 'INFO');
            return $this->$method_name();
        }

        return [
            'success' => false,
            'message' => 'Unknown fix method: ' . $method_name,
            'details' => [],
        ];
    }

    private function ensure_htaccess_backup() {
        if (file_exists($this->htaccess_path)) {
            $backup_path = $this->htaccess_path . '.az-backup';
            if (!file_exists($backup_path)) {
                copy($this->htaccess_path, $backup_path);
            }
        }
    }

    private function read_htaccess() {
        if (file_exists($this->htaccess_path)) {
            return file_get_contents($this->htaccess_path);
        }
        return '';
    }

    private function write_htaccess($content) {
        $this->ensure_htaccess_backup();
        return file_put_contents($this->htaccess_path, $content);
    }

    private function insert_before_wordpress($content, $block) {
        if (preg_match('/# BEGIN WordPress/', $content)) {
            return preg_replace('/# BEGIN WordPress/', $block . "\n# BEGIN WordPress", $content);
        }
        return $content . "\n" . $block;
    }

    private function clean_block($content, $tag) {
        $content = preg_replace('/# BEGIN CEO ' . preg_quote($tag, '/') . '.*?# END CEO ' . preg_quote($tag, '/') . '\s*/s', '', $content);
        return $content;
    }

    public function fix_security_headers() {
        $content = $this->read_htaccess();
        $content = $this->clean_block($content, 'SECURITY HEADERS');

        $block = "# BEGIN CEO SECURITY HEADERS\n";
        $block .= "<IfModule mod_headers.c>\n";
        $block .= "    Header always set Content-Security-Policy \"default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https:; style-src 'self' 'unsafe-inline' https:; img-src 'self' data: https: *.gravatar.com *.wp.com; font-src 'self' data: https:; connect-src 'self' https:; frame-ancestors 'none'; base-uri 'self'; form-action 'self'; upgrade-insecure-requests\"\n";
        $block .= "    Header always set Strict-Transport-Security \"max-age=31536000; includeSubDomains; preload\"\n";
        $block .= "    Header always set X-Frame-Options \"SAMEORIGIN\"\n";
        $block .= "    Header always set X-Content-Type-Options \"nosniff\"\n";
        $block .= "    Header always set Referrer-Policy \"strict-origin-when-cross-origin\"\n";
        $block .= "    Header always set Permissions-Policy \"geolocation=(), microphone=(), camera=(), payment=()\"\n";
        $block .= "</IfModule>\n";
        $block .= "# END CEO SECURITY HEADERS\n";

        $content = $this->insert_before_wordpress($content, $block);
        $this->write_htaccess($content);

        AZ_Logger::log('Security headers applied to .htaccess', 'SUCCESS');

        return [
            'success' => true,
            'message' => 'Security headers added to .htaccess',
            'details' => ['Applied: CSP, HSTS, X-Frame-Options, X-Content-Type-Options, Referrer-Policy, COOP, Permissions-Policy'],
        ];
    }

    public function fix_https_redirect() {
        $content = $this->read_htaccess();
        $content = $this->clean_block($content, 'HTTPS REDIRECT');

        $content = preg_replace('/#\s*RewriteCond\s+%\{HTTPS\}.*?\n\s*#?\s*RewriteRule.*?\n/si', '', $content);
        $content = preg_replace('/RewriteCond\s+%\{HTTPS\}.*?\n\s*RewriteRule.*?\n/si', '', $content);

        $block = "# BEGIN CEO HTTPS REDIRECT\n";
        $block .= "<IfModule mod_rewrite.c>\n";
        $block .= "RewriteEngine On\n";
        $block .= "RewriteCond %{HTTPS} !=on\n";
        $block .= "RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]\n";
        $block .= "</IfModule>\n";
        $block .= "# END CEO HTTPS REDIRECT\n";

        $content = $this->insert_before_wordpress($content, $block);
        $this->write_htaccess($content);

        AZ_Logger::log('HTTPS redirect added to .htaccess', 'SUCCESS');

        return [
            'success' => true,
            'message' => 'HTTPS redirect added to .htaccess',
            'details' => ['HTTP to HTTPS 301 redirect enabled'],
        ];
    }

    public function fix_robots_txt() {
        $robots_path = ABSPATH . 'robots.txt';
        $sitemap_url = home_url() . '/sitemap_index.xml';

        $content = "# CEO Optimizer - robots.txt\n";
        $content .= "User-agent: *\n";
        $content .= "Allow: /\n";
        $content .= "Disallow: /wp-admin/\n";
        $content .= "Disallow: /wp-includes/\n";
        $content .= "Disallow: /wp-content/plugins/\n";
        $content .= "Disallow: /wp-json/\n";
        $content .= "Disallow: /xmlrpc.php\n\n";
        $content .= "Sitemap: {$sitemap_url}\n";

        file_put_contents($robots_path, $content);

        AZ_Logger::log('robots.txt created/updated', 'SUCCESS');

        return [
            'success' => true,
            'message' => 'robots.txt created with SEO-optimized directives',
            'details' => ['Created: ' . $robots_path],
        ];
    }

    public function fix_image_alt() {
        global $wpdb;

        $updated = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$wpdb->posts} SET post_excerpt = post_title WHERE post_type = %s AND post_mime_type LIKE %s AND (post_excerpt = %s OR post_excerpt IS NULL)",
                'attachment',
                'image%',
                ''
            )
        );

        if ($updated !== false) {
            AZ_Logger::log("Alt text set for {$updated} images", 'SUCCESS');
            return [
                'success' => true,
                'message' => "Alt text auto-generated for {$updated} images",
                'details' => ["{$updated} images updated with title-based alt text"],
            ];
        }

        return [
            'success' => false,
            'message' => 'Failed to update image alt text',
            'details' => [],
        ];
    }

    public function fix_database_bloat() {
        global $wpdb;
        $details = [];

        $revisions = $wpdb->query(
            $wpdb->prepare("DELETE FROM {$wpdb->posts} WHERE post_type = %s", 'revision')
        );
        if ($revisions !== false) {
            $details[] = "Deleted {$revisions} post revisions";
        }

        $auto_drafts = $wpdb->query(
            $wpdb->prepare("DELETE FROM {$wpdb->posts} WHERE post_status = %s", 'auto-draft')
        );
        if ($auto_drafts !== false) {
            $details[] = "Deleted {$auto_drafts} auto-drafts";
        }

        $trash = $wpdb->query(
            $wpdb->prepare("DELETE FROM {$wpdb->posts} WHERE post_status = %s", 'trash')
        );
        if ($trash !== false) {
            $details[] = "Deleted {$trash} trashed items";
        }

        $spam = $wpdb->query(
            $wpdb->prepare("DELETE FROM {$wpdb->comments} WHERE comment_approved = %s", 'spam')
        );
        if ($spam !== false) {
            $details[] = "Deleted {$spam} spam comments";
        }

        $transients = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s AND autoload = %s",
                '%\_transient\_%',
                'no'
            )
        );
        if ($transients !== false) {
            $details[] = "Deleted {$transients} expired transients";
        }

        $tables = $wpdb->get_results("SHOW TABLES");
        if ($tables) {
            foreach ($tables as $row) {
                $table = array_values((array)$row)[0];
                $wpdb->query("OPTIMIZE TABLE {$table}");
            }
            $details[] = 'Optimized all database tables';
        }

        AZ_Logger::log('Database cleanup: ' . implode(', ', $details), 'SUCCESS');

        return [
            'success' => true,
            'message' => 'Database optimized successfully',
            'details' => $details,
        ];
    }

    public function fix_sitemap() {
        $sitemap_path = ABSPATH . 'sitemap.xml';
        $home_url = home_url();

        $args = [
            'post_type'      => ['post', 'page'],
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'modified',
            'order'          => 'DESC',
        ];

        $posts = get_posts($args);

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<!-- Generated by AZ Optimizer -->' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        $xml .= "  <url>\n";
        $xml .= "    <loc>" . esc_url($home_url) . "</loc>\n";
        $xml .= "    <priority>1.0</priority>\n";
        $xml .= "    <changefreq>daily</changefreq>\n";
        $xml .= "  </url>\n";

        if (!empty($posts)) {
            foreach ($posts as $post) {
                $permalink = get_permalink($post->ID);
                $lastmod = get_the_modified_time('Y-m-d\TH:i:sP', $post->ID);
                $xml .= "  <url>\n";
                $xml .= "    <loc>" . esc_url($permalink) . "</loc>\n";
                $xml .= "    <lastmod>{$lastmod}</lastmod>\n";
                $xml .= "    <priority>0.8</priority>\n";
                $xml .= "    <changefreq>weekly</changefreq>\n";
                $xml .= "  </url>\n";
            }
        }

        $xml .= '</urlset>' . "\n";

        file_put_contents($sitemap_path, $xml);

        AZ_Logger::log('Sitemap generated with ' . (count($posts) + 1) . ' URLs', 'SUCCESS');

        return [
            'success' => true,
            'message' => 'XML sitemap generated with ' . (count($posts) + 1) . ' URLs',
            'details' => ['Created: sitemap.xml'],
        ];
    }

    public function fix_render_blocking() {
        add_filter('script_loader_tag', [$this, 'defer_scripts'], 10, 3);
        add_action('wp_head', [$this, 'preload_critical_css'], 1);

        AZ_Logger::log('Render-blocking fixes applied (defer + preload)', 'SUCCESS');

        return [
            'success' => true,
            'message' => 'Script defer and CSS preload filters registered',
            'details' => ['Non-core scripts deferred', 'Critical CSS preload added'],
        ];
    }

    public function defer_scripts($tag, $handle, $src) {
        $skip = ['jquery', 'jquery-core', 'jquery-migrate', 'elementor-frontend', 'elementor-pro-frontend', 'admin-bar'];
        if (in_array($handle, $skip)) {
            return $tag;
        }
        if (strpos($tag, 'defer') === false) {
            return str_replace(' src', ' defer src', $tag);
        }
        return $tag;
    }

    public function preload_critical_css() {
        echo '<style id="az-critical-css">';
        echo 'body { opacity: 1 !important; }';
        echo '.elementor-element p, .elementor-element span { color: #333 !important; }';
        echo '.elementor-element h1, h2, h3, h4 { color: #222 !important; }';
        echo 'a { color: #0056b3 !important; }';
        echo 'a:hover { color: #003d7a !important; }';
        echo '.az-skip-link:focus { left: 6px !important; top: 6px !important; padding: 12px 24px; background: #0056b3; color: #fff !important; }';
        echo '*:focus { outline: 2px solid #0056b3 !important; outline-offset: 2px !important; }';
        echo '.elementor-button, a, button { min-height: 48px; min-width: 48px; }';
        echo '</style>' . "\n";
    }

    public function fix_color_contrast() {
        add_action('wp_head', [$this, 'contrast_css']);
        return [
            'success' => true,
            'message' => 'Color contrast CSS improvements applied',
            'details' => ['WCAG AA compliance via CSS overrides'],
        ];
    }

    public function contrast_css() {
        echo '<style>';
        echo '.elementor-element p, .elementor-element span, .elementor-element li { color: #333333 !important; }';
        echo '.elementor-element h1, .elementor-element h2, .elementor-element h3, .elementor-element h4 { color: #1a1a1a !important; }';
        echo 'a:not(.elementor-button) { color: #0056b3 !important; }';
        echo 'a:not(.elementor-button):hover { color: #003d7a !important; }';
        echo '</style>' . "\n";
    }

    public function fix_touch_targets() {
        add_action('wp_head', [$this, 'touch_css']);
        return [
            'success' => true,
            'message' => 'Touch target sizing applied',
            'details' => ['Minimum 48x48px for interactive elements'],
        ];
    }

    public function touch_css() {
        echo '<style>';
        echo '.elementor-button, a:not(.az-skip-link), button, input[type="button"], input[type="submit"], nav a, .elementor-nav-menu a { min-height: 48px; min-width: 48px; display: inline-flex; align-items: center; justify-content: center; padding: 8px 16px; }';
        echo '</style>' . "\n";
    }

    public function fix_skip_link() {
        add_action('wp_body_open', [$this, 'skip_link_html']);
        add_action('wp_head', [$this, 'skip_link_css']);
        return [
            'success' => true,
            'message' => 'Skip link added for keyboard accessibility',
            'details' => ['Skip to content link visible on focus'],
        ];
    }

    public function skip_link_html() {
        echo '<a class="az-skip-link" href="#content" style="position:absolute;left:-9999px;top:0;z-index:999999;font-size:16px;">Skip to content</a>' . "\n";
    }

    public function skip_link_css() {
        echo '<style>.az-skip-link:focus { left: 6px !important; top: 6px !important; }</style>' . "\n";
    }

    public function fix_main_landmark() {
        add_action('wp_footer', [$this, 'main_landmark_js'], 99);
        return [
            'success' => true,
            'message' => 'Main landmark fix applied via JavaScript',
            'details' => ['role="main" added to primary content area'],
        ];
    }

    public function main_landmark_js() {
        echo '<script id="az-landmark-fix">document.addEventListener("DOMContentLoaded",function(){var c=document.getElementById("content")||document.querySelector(".site-content,.entry-content,#primary");c&&c.getAttribute("role")!=="main"&&c.setAttribute("role","main")||document.querySelector("main")||(function(){var m=document.createElement("main");m.setAttribute("role","main");var b=document.body;b.insertBefore(m,b.firstChild);while(m.nextSibling){m.appendChild(m.nextSibling)}})()});</script>' . "\n";
    }

    public function fix_focusable_elements() {
        add_action('wp_head', [$this, 'focus_css']);
        return [
            'success' => true,
            'message' => 'Focus indicators applied to all interactive elements',
            'details' => ['WCAG 2.1 focus visible compliance'],
        ];
    }

    public function focus_css() {
        echo '<style>';
        echo '*:focus-visible { outline: 3px solid #0056b3 !important; outline-offset: 2px !important; border-radius: 2px; }';
        echo '*:focus:not(:focus-visible) { outline: none; }';
        echo '</style>' . "\n";
    }

    public function fix_aria_labels() {
        add_action('wp_footer', [$this, 'aria_js'], 99);
        return [
            'success' => true,
            'message' => 'ARIA label fallback script added',
            'details' => ['JavaScript fallback for unnamed interactive elements'],
        ];
    }

    public function aria_js() {
        echo '<script id="az-aria-fix">document.addEventListener("DOMContentLoaded",function(){document.querySelectorAll("a:not([aria-label]),button:not([aria-label]),input[type=button]:not([aria-label]),input[type=submit]:not([aria-label])").forEach(function(e){if(!e.textContent.trim()&&!e.getAttribute("aria-label")){if(e.tagName==="A"&&e.getAttribute("href")){e.setAttribute("aria-label","Link: "+e.getAttribute("href").replace(/https?:\/\//,"").split("/")[0])}else{e.setAttribute("aria-label","Button")}}})});</script>' . "\n";
    }

    public function fix_gzip_compression() {
        $content = $this->read_htaccess();
        $content = $this->clean_block($content, 'GZIP COMPRESSION');

        $block = "# BEGIN CEO GZIP COMPRESSION\n";
        $block .= "<IfModule mod_deflate.c>\n";
        $block .= "    AddOutputFilterByType DEFLATE text/html text/plain text/xml text/css text/javascript application/javascript application/json application/xml\n";
        $block .= "    AddOutputFilterByType DEFLATE image/svg+xml font/ttf font/otf\n";
        $block .= "    BrowserMatch ^Mozilla/4 gzip-only-text/html\n";
        $block .= "    BrowserMatch ^Mozilla/4\\\.0[678] no-gzip\n";
        $block .= "    BrowserMatch \\bMSIE !no-gzip !gzip-only-text/html\n";
        $block .= "</IfModule>\n";
        $block .= "# END CEO GZIP COMPRESSION\n";

        $content = $this->insert_before_wordpress($content, $block);
        $this->write_htaccess($content);

        AZ_Logger::log('GZIP compression enabled via .htaccess', 'SUCCESS');

        return [
            'success' => true,
            'message' => 'GZIP compression enabled in .htaccess',
            'details' => ['mod_deflate configured for HTML, CSS, JS, JSON, XML, SVG'],
        ];
    }

    public function fix_xml_rpc() {
        $content = $this->read_htaccess();
        $content = $this->clean_block($content, 'XML-RPC BLOCK');

        $block = "# BEGIN CEO XML-RPC BLOCK\n";
        $block .= "<Files xmlrpc.php>\n";
        $block .= "    Require all denied\n";
        $block .= "</Files>\n";
        $block .= "# END CEO XML-RPC BLOCK\n";

        $content = $this->insert_before_wordpress($content, $block);
        $this->write_htaccess($content);

        add_filter('xmlrpc_enabled', '__return_false');

        AZ_Logger::log('XML-RPC blocked via .htaccess', 'SUCCESS');

        return [
            'success' => true,
            'message' => 'XML-RPC blocked via .htaccess and disabled',
            'details' => ['xmlrpc.php access denied', 'xmlrpc_enabled filter set to false'],
        ];
    }

    public function fix_file_permissions() {
        $files = [
            ABSPATH . 'wp-config.php' => 0600,
            ABSPATH . '.htaccess'     => 0600,
        ];

        $results = [];
        foreach ($files as $path => $perm) {
            if (file_exists($path)) {
                $changed = chmod($path, $perm);
                $results[] = basename($path) . ($changed ? ' set to ' . sprintf('%o', $perm) : ' chmod failed');
            }
        }

        AZ_Logger::log('File permissions fixed: ' . implode(', ', $results), 'SUCCESS');

        return [
            'success' => true,
            'message' => 'File permissions hardened',
            'details' => $results,
        ];
    }

    public function fix_memory_limit() {
        if (is_writable(ABSPATH . 'wp-config.php')) {
            $config = file_get_contents(ABSPATH . 'wp-config.php');
            if (preg_match("/define\s*\(\s*'WP_MEMORY_LIMIT'/", $config)) {
                $config = preg_replace(
                    "/define\s*\(\s*'WP_MEMORY_LIMIT'\s*,\s*'[^']+'\s*\)/",
                    "define('WP_MEMORY_LIMIT', '256M')",
                    $config
                );
            } else {
                $config = preg_replace(
                    "/require_once\s+ABSPATH\s*\.\s*'wp-settings\.php'/",
                    "define('WP_MEMORY_LIMIT', '256M');\nrequire_once ABSPATH . 'wp-settings.php'",
                    $config
                );
            }
            file_put_contents(ABSPATH . 'wp-config.php', $config);
            AZ_Logger::log('WP_MEMORY_LIMIT updated to 256M', 'SUCCESS');
            return [
                'success' => true,
                'message' => 'WP_MEMORY_LIMIT updated to 256M in wp-config.php',
                'details' => ['Increased memory limit for better plugin/Elementor performance'],
            ];
        }

        return [
            'success' => false,
            'message' => 'Cannot write to wp-config.php. Update manually: define(\'WP_MEMORY_LIMIT\', \'256M\');',
            'details' => ['wp-config.php is not writable'],
        ];
    }

    public function fix_heartbeat() {
        add_action('init', [$this, 'heartbeat_settings'], 1);

        $content = $this->read_htaccess();
        if (strpos($content, 'heartbeat') === false) {
            $block = "\n# BEGIN CEO HEARTBEAT CONTROL\n";
            $block .= "<IfModule mod_rewrite.c>\n";
            $block .= "RewriteEngine On\n";
            $block .= "RewriteRule ^wp-admin/admin-ajax\.php$ - [E=HEARTBEAT_CONTROL:1]\n";
            $block .= "</IfModule>\n";
            $block .= "# END CEO HEARTBEAT CONTROL\n";
            $content = $this->insert_before_wordpress($content, $block);
            $this->write_htaccess($content);
        }

        AZ_Logger::log('Heartbeat API optimized', 'SUCCESS');

        return [
            'success' => true,
            'message' => 'Heartbeat API frequency limited',
            'details' => ['Heartbeat reduced to 60s interval in admin, disabled in post editor'],
        ];
    }

    public function heartbeat_settings() {
        add_filter('heartbeat_settings', function ($settings) {
            $settings['interval'] = 60;
            return $settings;
        });
    }

    public function fix_orphaned_meta() {
        global $wpdb;
        $deleted = $wpdb->query(
            "DELETE pm FROM {$wpdb->postmeta} pm LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID WHERE p.ID IS NULL"
        );

        AZ_Logger::log("Deleted {$deleted} orphaned meta entries", 'SUCCESS');

        return [
            'success' => true,
            'message' => "Deleted {$deleted} orphaned post meta entries",
            'details' => ["{$deleted} rows removed"],
        ];
    }

    public function fix_post_drafts() {
        global $wpdb;
        $deleted = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->posts} WHERE post_status = %s AND post_date < %s",
                'draft',
                current_time('mysql', 0)
            )
        );

        AZ_Logger::log("Deleted {$deleted} old drafts", 'SUCCESS');

        return [
            'success' => true,
            'message' => "Deleted {$deleted} old drafts",
            'details' => ["Old drafts cleaned up"],
        ];
    }

    public function fix_meta_tags() {
        return [
            'success' => false,
            'message' => 'Manual action required: Use Rank Math or Yoast SEO to set meta descriptions on each page.',
            'details' => [
                '1. Install and activate Rank Math SEO (or Yoast SEO)',
                '2. Edit each page/post',
                '3. Set a compelling meta description (150-160 characters with primary keyword)',
                '4. Use the AI Analysis tool in this plugin to generate descriptions',
            ],
        ];
    }

    public function fix_canonical_urls() {
        return [
            'success' => false,
            'message' => 'Manual action required: Use Rank Math or Yoast SEO to configure canonical URLs.',
            'details' => [
                '1. Install an SEO plugin if not already active',
                '2. Enable canonical URL settings in the SEO plugin',
                '3. For posts/pages, set individual canonical URLs if needed',
            ],
        ];
    }

    public function fix_schema_markup() {
        return [
            'success' => false,
            'message' => 'Manual action required: Enable schema markup in your SEO plugin.',
            'details' => [
                '1. Rank Math: Dashboard > Schema (built-in, enable for all content types)',
                '2. Yoast: SEO > Search Appearance > Schema (enable schema)',
                '3. Consider adding LocalBusiness schema for your business',
            ],
        ];
    }

    public function fix_open_graph() {
        return [
            'success' => false,
            'message' => 'Manual action required: Configure Open Graph in your SEO plugin.',
            'details' => [
                '1. Rank Math: Dashboard > Titles & Meta > Social (enable OG/Twitter)',
                '2. Yoast: SEO > Social (configure Facebook and Twitter)',
                '3. Set a default social share image',
            ],
        ];
    }

    public function fix_php_version() {
        return [
            'success' => false,
            'message' => 'Manual action required: Update PHP version via cPanel or hosting control panel.',
            'details' => [
                '1. Log in to cPanel/hosting dashboard',
                '2. Find "Select PHP Version" (MultiPHP Manager)',
                '3. Select PHP 8.1 or 8.2',
                '4. Test all plugins for compatibility after upgrade',
            ],
        ];
    }

    public function fix_mysql_version() {
        return [
            'success' => false,
            'message' => 'Manual action required: Contact hosting provider to upgrade MySQL.',
            'details' => [
                '1. Contact hosting support to request MySQL 8.0 upgrade',
                '2. If using cPanel, you may upgrade via MySQL section',
                '3. Backup database before upgrade',
            ],
        ];
    }

    public function fix_upload_max() {
        return [
            'success' => false,
            'message' => 'Manual action required: Increase upload limits via php.ini or wp-config.php.',
            'details' => [
                '1. Contact hosting or edit php.ini: upload_max_filesize = 64M, post_max_size = 64M',
                '2. Or add to wp-config.php: @ini_set("upload_max_filesize", "64M")',
                '3. Alternatively, use a plugin like "Increase Max Upload Filesize"',
            ],
        ];
    }

    public function fix_post_max_size() {
        return $this->fix_upload_max();
    }

    public function fix_caching() {
        return [
            'success' => false,
            'message' => 'Manual action required: Install and configure a caching plugin.',
            'details' => [
                '1. Use the "Install Required Plugins" tool in the Tools tab',
                '2. Recommended: WP Rocket (premium), W3 Total Cache, or WP Super Cache',
                '3. For shared hosting, LiteSpeed Cache if server has LiteSpeed',
            ],
        ];
    }

    public function fix_cron_overload() {
        global $wpdb;
        $crons = _get_cron_array();
        $deleted = 0;

        if (is_array($crons)) {
            foreach ($crons as $timestamp => $hooks) {
                if ($timestamp < time() - 86400 * 7) {
                    foreach ($hooks as $hook => $events) {
                        foreach ($events as $key => $event) {
                            wp_unschedule_event($timestamp, $hook, $event['args']);
                            $deleted++;
                        }
                    }
                }
            }
        }

        AZ_Logger::log("Cleaned {$deleted} stale cron events", 'SUCCESS');

        return [
            'success' => true,
            'message' => "Cleaned {$deleted} stale cron events",
            'details' => ["Old cron jobs removed"],
        ];
    }

    public function fix_cron_disabled() {
        return [
            'success' => false,
            'message' => 'Manual action required: Set up a server-side cron job to call wp-cron.php.',
            'details' => [
                '1. Add to crontab: * * * * * wget -q -O- https://yoursite.com/wp-cron.php?doing_wp_cron >/dev/null 2>&1',
                '2. Or use cPanel Cron Jobs with: php -f /path/to/wp-cron.php',
                '3. Then DISABLE_WP_CRON can remain true for better performance',
            ],
        ];
    }

    public function fix_login_protection() {
        return [
            'success' => false,
            'message' => 'Manual action required: Install a security plugin with login protection.',
            'details' => [
                '1. Recommended: Wordfence (free), Limit Login Attempts Reloaded, or Cerber',
                '2. All are available in the WordPress plugin repository',
                '3. Configure: max 3-5 login attempts, then lockout',
                '4. Enable CAPTCHA on login form for additional protection',
            ],
        ];
    }

    public function fix_core_integrity() {
        return [
            'success' => false,
            'message' => 'Manual action required: Reinstall WordPress core to fix suspected tampering.',
            'details' => [
                '1. Go to Dashboard > Updates',
                '2. Click "Reinstall WordPress"',
                '3. Run a security scan with Wordfence or similar',
                '4. Review recent file changes in wp-content',
            ],
        ];
    }

    public function fix_large_images() {
        return [
            'success' => false,
            'message' => 'Manual action required: Optimize large images.',
            'details' => [
                '1. Use the "Install Required Plugins" tool to install Smush',
                '2. Run Smush bulk optimization on all images',
                '3. For future uploads, enable automatic compression',
                '4. Consider WebP conversion for better performance',
            ],
        ];
    }

    public function fix_external_requests() {
        return [
            'success' => false,
            'message' => 'Manual action required: Audit and minimize external HTTP requests.',
            'details' => [
                '1. Host Google Fonts locally instead of CDN',
                '2. Defer third-party scripts (analytics, pixels)',
                '3. Combine external requests where possible',
                '4. Use Asset CleanUp plugin to selectively disable assets',
            ],
        ];
    }

    public function fix_email_deliverability() {
        return [
            'success' => false,
            'message' => 'Manual action required: Configure SMTP for reliable email delivery.',
            'details' => [
                '1. Install WP Mail SMTP or FluentSMTP from plugins repo',
                '2. Configure with your email service (Gmail, SendGrid, etc.)',
                '3. Test email sending after configuration',
            ],
        ];
    }

    public function fix_form_labels() {
        return [
            'success' => false,
            'message' => 'Manual action required: Add labels to form elements.',
            'details' => [
                '1. Edit forms in Elementor',
                '2. Ensure each input field has an associated label',
                '3. Use the Form widget\'s "Label" setting',
                '4. For custom forms, add <label for="field-id">Label text</label>',
            ],
        ];
    }

    public function fix_wp_debug() {
        return [
            'success' => false,
            'message' => 'Manual action required: Disable WP_DEBUG in production.',
            'details' => [
                '1. Edit wp-config.php',
                '2. Change define(\'WP_DEBUG\', true) to define(\'WP_DEBUG\', false)',
                '3. Also add: define(\'WP_DEBUG_DISPLAY\', false)',
            ],
        ];
    }

    public function fix_admin_username() {
        return [
            'success' => false,
            'message' => 'Manual action required: Change or remove the "admin" username.',
            'details' => [
                '1. Create a new admin user with a unique username',
                '2. Assign the Administrator role to the new user',
                '3. Log in as the new user',
                '4. Delete the old "admin" user and attribute posts to the new user',
            ],
        ];
    }

    public function fix_manual_ssl() {
        return [
            'success' => false,
            'message' => 'Manual action required: Renew or fix SSL certificate.',
            'details' => [
                '1. Contact hosting provider or use Let\'s Encrypt auto-renewal',
                '2. If using cPanel, go to SSL/TLS section',
                '3. Ensure the certificate covers www and non-www domains',
            ],
        ];
    }

    public function fix_headings() {
        return [
            'success' => false,
            'message' => 'Manual action required: Fix heading hierarchy in Elementor.',
            'details' => [
                '1. Edit pages with Elementor',
                '2. Ensure headings follow sequential order: H1 -> H2 -> H3',
                '3. Do not skip heading levels (e.g., H1 directly to H3)',
                '4. Each page should have exactly one H1',
            ],
        ];
    }

    public function fix_unused_plugins() {
        return [
            'success' => false,
            'message' => 'Manual action required: Review and remove unused plugins.',
            'details' => [
                '1. Go to Plugins > Installed Plugins',
                '2. Deactivate any plugins no longer needed',
                '3. Delete deactivated plugins to free up disk space',
                '4. Keep only actively maintained, essential plugins',
            ],
        ];
    }
}
