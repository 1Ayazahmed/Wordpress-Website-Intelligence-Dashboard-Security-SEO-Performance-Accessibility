<?php if (!defined('ABSPATH')) exit; ?>
<div class="az-wrap">
    <div class="az-header">
        <div class="az-header-content">
            <div>
                <h1>AZ Optimizer</h1>
                <p class="az-subtitle">Enterprise Intelligence Dashboard &bull; Security, SEO, Performance &amp; Accessibility</p>
            </div>
            <div class="az-header-meta">
                <span class="az-header-badge" id="az-last-scan">Last scan: --</span>
                <span class="az-header-badge" id="az-scan-count">Scans: --</span>
            </div>
        </div>
    </div>

    <div id="az-alert" class="az-alert" style="display:none;"></div>
    <div id="az-progress" class="az-progress" style="display:none;">
        <div class="az-progress-bar"><div class="az-progress-fill" style="width:0%;"></div></div>
        <span class="az-progress-text">Processing...</span>
    </div>

    <div class="az-tabs">
        <span class="az-tab active" data-tab="dashboard">Dashboard</span>
        <span class="az-tab" data-tab="scanner">Scanner</span>
        <span class="az-tab" data-tab="backup">Backup/Restore</span>
        <span class="az-tab" data-tab="ai">AI Settings</span>
        <span class="az-tab" data-tab="tools">Tools</span>
        <span class="az-tab" data-tab="reports">Reports</span>
        <span class="az-tab" data-tab="logs">Logs</span>
    </div>

    <div id="az-tab-dashboard" class="az-tab-content active">
        <div class="az-dashboard-grid">
            <div class="az-card az-card-health">
                <div class="az-card-header">Site Health Score</div>
                <div class="az-gauge-container">
                    <svg class="az-gauge" viewBox="0 0 200 110">
                        <path d="M 20 100 A 80 80 0 0 1 180 100" fill="none" stroke="#e0e0e0" stroke-width="14" stroke-linecap="round"/>
                        <path id="az-gauge-arc" d="M 20 100 A 80 80 0 0 1 180 100" fill="none" stroke="#0d6efd" stroke-width="14" stroke-linecap="round" stroke-dasharray="251.2" stroke-dashoffset="251.2"/>
                        <text x="100" y="75" text-anchor="middle" id="az-gauge-text" font-size="42" font-weight="700" fill="#1a1a1a">--</text>
                        <text x="100" y="95" text-anchor="middle" font-size="12" fill="#888">out of 100</text>
                    </svg>
                </div>
                <div class="az-health-grade" id="az-health-grade">Grade: --</div>
            </div>

            <div class="az-card az-card-categories">
                <div class="az-card-header">Category Scores</div>
                <div class="az-category-scores">
                    <div class="az-category-row">
                        <span class="az-category-label"><span class="az-dot az-dot-security"></span> Security</span>
                        <div class="az-category-bar"><div class="az-category-fill" id="az-cat-security" style="width:0%"></div></div>
                        <span class="az-category-value" id="az-cat-security-val">--</span>
                    </div>
                    <div class="az-category-row">
                        <span class="az-category-label"><span class="az-dot az-dot-seo"></span> SEO</span>
                        <div class="az-category-bar"><div class="az-category-fill" id="az-cat-seo" style="width:0%"></div></div>
                        <span class="az-category-value" id="az-cat-seo-val">--</span>
                    </div>
                    <div class="az-category-row">
                        <span class="az-category-label"><span class="az-dot az-dot-performance"></span> Performance</span>
                        <div class="az-category-bar"><div class="az-category-fill" id="az-cat-performance" style="width:0%"></div></div>
                        <span class="az-category-value" id="az-cat-performance-val">--</span>
                    </div>
                    <div class="az-category-row">
                        <span class="az-category-label"><span class="az-dot az-dot-accessibility"></span> Accessibility</span>
                        <div class="az-category-bar"><div class="az-category-fill" id="az-cat-accessibility" style="width:0%"></div></div>
                        <span class="az-category-value" id="az-cat-accessibility-val">--</span>
                    </div>
                </div>
            </div>

            <div class="az-card az-card-stats">
                <div class="az-card-header">Issue Breakdown</div>
                <div class="az-breakdown-chart">
                    <canvas id="az-breakdown-chart" width="200" height="200"></canvas>
                </div>
                <div class="az-breakdown-legend" id="az-breakdown-legend"></div>
            </div>

            <div class="az-card az-card-trend">
                <div class="az-card-header">Health Trend <span class="az-trend-period" id="az-trend-period">Last 10 scans</span></div>
                <div class="az-trend-chart">
                    <canvas id="az-trend-chart" width="600" height="200"></canvas>
                </div>
            </div>

            <div class="az-card az-card-issues az-card-full">
                <div class="az-card-header">
                    <span>Issue Summary</span>
                    <div class="az-stats-grid">
                        <span class="az-mini-stat az-mini-critical"><strong id="az-count-critical">0</strong> Critical</span>
                        <span class="az-mini-stat az-mini-high"><strong id="az-count-high">0</strong> High</span>
                        <span class="az-mini-stat az-mini-medium"><strong id="az-count-medium">0</strong> Medium</span>
                        <span class="az-mini-stat az-mini-low"><strong id="az-count-low">0</strong> Low</span>
                        <span class="az-mini-stat az-mini-total"><strong id="az-count-total">0</strong> Total</span>
                    </div>
                </div>
                <div class="az-card-actions">
                    <button class="az-btn az-btn-primary" id="az-scan-btn">Run Full Scan</button>
                    <button class="az-btn az-btn-success" id="az-fix-all-btn">Fix All Auto</button>
                    <button class="az-btn az-btn-warning" id="az-backup-btn">Create Backup</button>
                    <button class="az-btn az-btn-gray" id="az-export-btn">Export CSV</button>
                </div>
            </div>

            <div class="az-card az-card-server az-card-half">
                <div class="az-card-header">Server Environment</div>
                <div class="az-server-info" id="az-server-info">
                    <div class="az-server-row"><span>PHP</span><span class="az-server-val">--</span></div>
                    <div class="az-server-row"><span>MySQL</span><span class="az-server-val">--</span></div>
                    <div class="az-server-row"><span>WordPress</span><span class="az-server-val">--</span></div>
                    <div class="az-server-row"><span>Memory Limit</span><span class="az-server-val">--</span></div>
                    <div class="az-server-row"><span>SSL</span><span class="az-server-val">--</span></div>
                    <div class="az-server-row"><span>Server</span><span class="az-server-val">--</span></div>
                </div>
            </div>

            <div class="az-card az-card-recommend az-card-half">
                <div class="az-card-header">Quick Recommendations</div>
                <div class="az-recommendations" id="az-recommendations">
                    <p class="az-empty-state">Run a scan to see recommendations.</p>
                </div>
            </div>
        </div>
    </div>

    <div id="az-tab-scanner" class="az-tab-content">
        <div class="az-tab-toolbar">
            <button class="az-btn az-btn-primary" id="az-scan-btn-2">Run Scan</button>
            <button class="az-btn az-btn-success" id="az-fix-all-btn-2">Fix All Auto-Fixable</button>
        </div>
        <div class="az-scanner-filters">
            <select id="az-filter-type" class="az-input az-input-sm">
                <option value="all">All Types</option>
                <option value="security">Security</option>
                <option value="seo">SEO</option>
                <option value="performance">Performance</option>
                <option value="accessibility">Accessibility</option>
            </select>
            <select id="az-filter-severity" class="az-input az-input-sm">
                <option value="all">All Severities</option>
                <option value="critical">Critical</option>
                <option value="high">High</option>
                <option value="medium">Medium</option>
                <option value="low">Low</option>
            </select>
        </div>
        <div id="az-scanner-results"><p class="az-empty-state">Run a scan to detect issues on your site.</p></div>
        <table class="az-issues-table" id="az-issues-table" style="display:none;">
            <thead>
                <tr><th>Type</th><th>Severity</th><th>Issue</th><th>Action</th></tr>
            </thead>
            <tbody id="az-issues-body"></tbody>
        </table>
    </div>

    <div id="az-tab-backup" class="az-tab-content">
        <div class="az-tab-toolbar">
            <button class="az-btn az-btn-warning" id="az-backup-btn-2">Create Backup</button>
        </div>
        <div id="az-backup-list" class="az-backup-list"><p class="az-empty-state">No backups available.</p></div>
    </div>

    <div id="az-tab-ai" class="az-tab-content">
        <form class="az-settings-form" id="az-ai-form">
            <div class="az-form-group">
                <label for="az-api-key">AI API Key</label>
                <input type="password" id="az-api-key" name="api_key" class="az-input" placeholder="sk-..." value="<?php echo esc_attr(get_option('az_openai_api_key', '')); ?>" />
                <p class="az-hint">Your API key is stored securely in the database. Supports OpenAI, Azure, and any OpenAI-compatible provider.</p>
            </div>
            <div class="az-form-group">
                <label for="az-base-url">API Base URL</label>
                <input type="text" id="az-base-url" name="base_url" class="az-input" value="<?php echo esc_attr(get_option('az_openai_base_url', 'https://api.openai.com/v1')); ?>" />
                <p class="az-hint">Custom base URL for any OpenAI-compatible API (OpenAI, Azure, Groq, Together, etc.)</p>
            </div>
            <div class="az-form-group">
                <label for="az-model">Model</label>
                <div class="az-model-row">
                    <select id="az-model" name="model" class="az-select" style="flex:1;">
                        <option value="gpt-4o-mini" <?php selected(get_option('az_openai_model', 'gpt-4o-mini'), 'gpt-4o-mini'); ?>>GPT-4o Mini</option>
                        <option value="gpt-4o" <?php selected(get_option('az_openai_model', ''), 'gpt-4o'); ?>>GPT-4o</option>
                        <option value="gpt-3.5-turbo" <?php selected(get_option('az_openai_model', ''), 'gpt-3.5-turbo'); ?>>GPT-3.5 Turbo</option>
                        <option value="custom" <?php selected(get_option('az_openai_model', ''), 'custom'); ?>>Custom Model</option>
                    </select>
                    <button type="button" class="az-btn az-btn-gray" id="az-fetch-models-btn" title="Fetch models from API">Fetch Models</button>
                </div>
                <input type="text" id="az-custom-model" name="custom_model" class="az-input" placeholder="Enter custom model name..." value="<?php echo esc_attr(get_option('az_custom_model', '')); ?>" style="margin-top:8px;<?php echo get_option('az_openai_model', '') === 'custom' ? '' : 'display:none;'; ?>" />
            </div>
            <div class="az-form-actions">
                <button type="submit" class="az-btn az-btn-primary" id="az-save-ai-btn">Save Settings</button>
                <button type="button" class="az-btn az-btn-success" id="az-ai-btn">Run AI Analysis</button>
            </div>
        </form>
        <div id="az-ai-result" class="az-ai-result" style="display:none;"></div>
    </div>

    <div id="az-tab-tools" class="az-tab-content">
        <div class="az-tools-grid">
            <div class="az-tool-card">
                <h3>One-Click Full Optimize</h3>
                <p>Installs Rank Math + Smush + Asset CleanUp, configures them, runs all fixes, generates sitemap, cleans junk, and optimizes DB.</p>
                <button class="az-btn az-btn-success" id="az-auto-optimize-btn" style="width:100%;justify-content:center;">Run Full Site Optimization</button>
                <div id="az-auto-optimize-result" class="az-tool-result"></div>
            </div>
            <div class="az-tool-card">
                <h3>Install Required Plugins</h3>
                <p>Install Rank Math SEO, Smush (image optimization), and Asset CleanUp.</p>
                <button class="az-btn az-btn-primary" id="az-install-plugins-btn">Install Plugins</button>
                <div id="az-plugins-result" class="az-tool-result"></div>
            </div>
            <div class="az-tool-card">
                <h3>Clean Junk Files</h3>
                <p>Remove readme.html and license.txt from the WordPress root directory.</p>
                <button class="az-btn az-btn-danger" id="az-clean-btn">Clean Files</button>
                <div id="az-clean-result" class="az-tool-result"></div>
            </div>
            <div class="az-tool-card">
                <h3>Optimize Database</h3>
                <p>Clean revisions, spam, transients, auto-drafts, trash, and optimize all tables.</p>
                <button class="az-btn az-btn-warning" id="az-db-btn">Optimize DB</button>
                <div id="az-db-result" class="az-tool-result"></div>
            </div>
        </div>
    </div>

    <div id="az-tab-reports" class="az-tab-content">
        <div class="az-reports-grid">
            <div class="az-tool-card">
                <h3>Export Scan Report</h3>
                <p>Download a CSV report of all issues found during the last scan.</p>
                <button class="az-btn az-btn-primary" id="az-export-btn-2">Export as CSV</button>
            </div>
            <div class="az-tool-card">
                <h3>Email Report</h3>
                <p>Send a summary report to any email address.</p>
                <div class="az-inline-form">
                    <input type="email" id="az-report-email" class="az-input" placeholder="email@example.com" value="<?php echo esc_attr(get_option('admin_email')); ?>" />
                    <button class="az-btn az-btn-success" id="az-email-report-btn">Send</button>
                </div>
                <div id="az-email-result" class="az-tool-result"></div>
            </div>
            <div class="az-tool-card">
                <h3>Scheduled Scans</h3>
                <p>Automatically scan your site on a schedule.</p>
                <form id="az-schedule-form">
                    <label class="az-toggle">
                        <input type="checkbox" id="az-schedule-enabled" <?php checked(get_option('az_scheduled_scan_enabled', false)); ?> />
                        <span class="az-toggle-slider"></span>
                        Enable scheduled scans
                    </label>
                    <select id="az-schedule-frequency" class="az-input az-input-sm" style="margin-top:10px;">
                        <option value="daily" <?php selected(get_option('az_scheduled_scan_frequency', 'daily'), 'daily'); ?>>Daily</option>
                        <option value="weekly" <?php selected(get_option('az_scheduled_scan_frequency', 'weekly'), 'weekly'); ?>>Weekly</option>
                    </select>
                    <label class="az-toggle" style="margin-top:10px;">
                        <input type="checkbox" id="az-email-alerts" <?php checked(get_option('az_email_alerts', false)); ?> />
                        <span class="az-toggle-slider"></span>
                        Email alerts on critical issues
                    </label>
                    <input type="email" id="az-alert-email" class="az-input" placeholder="Alert email" value="<?php echo esc_attr(get_option('az_alert_email', get_option('admin_email'))); ?>" style="margin-top:10px;" />
                    <button type="submit" class="az-btn az-btn-primary" style="margin-top:10px;">Save Schedule</button>
                </form>
                <div id="az-schedule-result" class="az-tool-result"></div>
            </div>
        </div>
    </div>

    <div id="az-tab-logs" class="az-tab-content">
        <div class="az-tab-toolbar">
            <button class="az-btn az-btn-primary" id="az-logs-btn">Refresh Logs</button>
            <span class="az-logs-info">Activity log: az-optimization-log.txt</span>
        </div>
        <div class="az-logs" id="az-logs-content"><pre>Click "Refresh Logs" to load activity log.</pre></div>
    </div>

    <!-- Loader Overlay -->
    <div class="az-loader-overlay" id="az-loader-overlay">
        <div class="az-loader-modal">
            <div class="az-loader-spinner">
                <div class="az-spinner-ring"></div>
            </div>
            <div class="az-loader-text" id="az-loader-text">Processing, please wait...</div>
            <div class="az-loader-warning">Do not close or refresh this tab while processing.</div>
        </div>
    </div>

    <!-- Floating GitHub Profile Card -->
    <div class="az-github-float" id="az-github-float">
        <a href="https://github.com/1Ayazahmed" target="_blank" rel="noopener noreferrer" class="az-github-link">
            <img src="https://avatars.githubusercontent.com/u/93036472?v=4" alt="Ayaz Ahmed" class="az-github-avatar" />
            <div class="az-github-info">
                <span class="az-github-name">Ayaz Ahmed</span>
                <span class="az-github-action">★ Star on GitHub</span>
            </div>
        </a>
    </div>
</div>

<script>
(function() {
    var floatCard = document.getElementById('az-github-float');
    if (floatCard) {
        setTimeout(function() { floatCard.classList.add('az-github-visible'); }, 2000);
    }
})();
</script>
