(function($) {
    'use strict';

    $(document).ready(function() {

        /* Tab Switching */
        $('.az-tab').on('click', function() {
            var tab = $(this).data('tab');
            $('.az-tab').removeClass('active');
            $(this).addClass('active');
            $('.az-tab-content').removeClass('active');
            $('#az-tab-' + tab).addClass('active');
        });

        /* Helpers */
        function showAlert(message, type) {
            var alertBox = $('#az-alert');
            type = type || 'info';
            alertBox.removeClass('az-alert-success az-alert-error az-alert-info')
                .addClass('az-alert-' + type)
                .html(message)
                .fadeIn(200);
            setTimeout(function() { alertBox.fadeOut(400); }, 5000);
        }

        function setProgress(percent, text) {
            $('#az-progress').show();
            $('.az-progress-fill').css('width', percent + '%');
            $('.az-progress-text').text(text || 'Processing...');
            if (percent > 0 && percent < 100) {
                showLoader(text || 'Processing, please wait...');
            }
            if (percent >= 100) {
                hideLoader();
            }
        }

        function hideProgress() {
            $('#az-progress').hide();
            $('.az-progress-fill').css('width', '0%');
            $('.az-progress-text').text('Processing...');
            hideLoader();
        }

        function escapeHtml(str) {
            if (!str) return '';
            var div = document.createElement('div');
            div.appendChild(document.createTextNode(String(str)));
            return div.innerHTML;
        }

        function escapeAttr(str) {
            return String(str).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
        }

        /* Loader */
        var azBusy = false;

        function showLoader(text) {
            azBusy = true;
            $('#az-loader-text').text(text || 'Processing, please wait...');
            $('#az-loader-overlay').addClass('active');
        }

        function hideLoader() {
            azBusy = false;
            $('#az-loader-overlay').removeClass('active');
        }

        $(window).on('beforeunload', function() {
            if (azBusy) {
                return 'A process is still running. Are you sure you want to leave?';
            }
        });

        /* Gauge */
        function updateGauge(score) {
            var arc = document.getElementById('az-gauge-arc');
            var text = document.getElementById('az-gauge-text');
            var grade = document.getElementById('az-health-grade');
            if (!arc) return;

            var circumference = 251.2;
            arc.style.strokeDasharray = circumference;
            arc.style.strokeDashoffset = circumference - (score / 100) * circumference;

            if (score >= 80) arc.style.stroke = '#198754';
            else if (score >= 60) arc.style.stroke = '#0d6efd';
            else if (score >= 40) arc.style.stroke = '#fd7e14';
            else arc.style.stroke = '#dc3545';

            text.textContent = score;

            var letter = score >= 90 ? 'A+' : score >= 80 ? 'A' : score >= 70 ? 'B+' : score >= 60 ? 'B' : score >= 50 ? 'C+' : score >= 40 ? 'C' : score >= 30 ? 'D' : 'F';
            grade.textContent = 'Grade: ' + letter;
            grade.style.color = score >= 60 ? '#198754' : score >= 40 ? '#fd7e14' : '#dc3545';
        }

        /* Pie Chart */
        function drawPieChart(critical, high, medium, low) {
            var canvas = document.getElementById('az-breakdown-chart');
            if (!canvas) return;
            var ctx = canvas.getContext('2d');
            var w = canvas.width, h = canvas.height;
            var cx = w / 2, cy = h / 2, r = Math.min(cx, cy) - 10;

            ctx.clearRect(0, 0, w, h);
            var total = critical + high + medium + low;

            if (total === 0) {
                ctx.beginPath(); ctx.arc(cx, cy, r, 0, Math.PI * 2);
                ctx.fillStyle = '#e9ecef'; ctx.fill();
                ctx.fillStyle = '#999'; ctx.font = '12px sans-serif'; ctx.textAlign = 'center';
                ctx.fillText('No issues', cx, cy + 4);
                return;
            }

            var data = [
                { value: critical, color: '#dc3545', label: 'Critical' },
                { value: high, color: '#fd7e14', label: 'High' },
                { value: medium, color: '#0d6efd', label: 'Medium' },
                { value: low, color: '#198754', label: 'Low' },
            ];

            var startAngle = -Math.PI / 2;
            data.forEach(function(d) {
                if (d.value === 0) return;
                var slice = (d.value / total) * Math.PI * 2;
                ctx.beginPath(); ctx.moveTo(cx, cy);
                ctx.arc(cx, cy, r, startAngle, startAngle + slice);
                ctx.closePath(); ctx.fillStyle = d.color; ctx.fill();
                startAngle += slice;
            });

            ctx.beginPath(); ctx.arc(cx, cy, r * 0.5, 0, Math.PI * 2);
            ctx.fillStyle = '#fff'; ctx.fill();
            ctx.fillStyle = '#333'; ctx.font = 'bold 16px sans-serif';
            ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
            ctx.fillText(total, cx, cy);

            var legend = document.getElementById('az-breakdown-legend');
            if (legend) {
                legend.innerHTML = data.map(function(d) {
                    return d.value > 0 ? '<span><span style="display:inline-block;width:10px;height:10px;border-radius:2px;background:' + d.color + '"></span> ' + d.label + ': ' + d.value + '</span>' : '';
                }).join('');
            }
        }

        /* Trend Chart */
        function drawTrendChart(history) {
            var canvas = document.getElementById('az-trend-chart');
            if (!canvas) return;
            var ctx = canvas.getContext('2d');
            var w = canvas.width, h = canvas.height;
            ctx.clearRect(0, 0, w, h);

            var rect = canvas.parentElement.getBoundingClientRect();
            canvas.style.width = rect.width + 'px';
            canvas.style.height = '180px';

            if (!history || history.length < 2) {
                ctx.fillStyle = '#999'; ctx.font = '12px sans-serif'; ctx.textAlign = 'center';
                ctx.fillText('Run multiple scans to see trend', w / 2, h / 2);
                return;
            }

            var scores = history.map(function(h) { return h.score; });
            var labels = history.map(function(h) { var d = new Date(h.date); return (d.getMonth() + 1) + '/' + d.getDate(); });
            var minScore = Math.max(0, Math.min.apply(null, scores) - 5);
            var maxScore = Math.min(100, Math.max.apply(null, scores) + 5);
            var pad = { top: 20, bottom: 30, left: 40, right: 20 };
            var chartW = w - pad.left - pad.right;
            var chartH = h - pad.top - pad.bottom;

            ctx.strokeStyle = '#f0f0f0'; ctx.lineWidth = 1;
            for (var i = 0; i <= 4; i++) {
                var y = pad.top + (chartH / 4) * i;
                ctx.beginPath(); ctx.moveTo(pad.left, y); ctx.lineTo(w - pad.right, y); ctx.stroke();
                ctx.fillStyle = '#aaa'; ctx.font = '10px sans-serif'; ctx.textAlign = 'right';
                ctx.fillText(Math.round(maxScore - (maxScore - minScore) / 4 * i), pad.left - 5, y + 3);
            }

            var points = scores.map(function(s, i) {
                return {
                    x: pad.left + (i / (scores.length - 1)) * chartW,
                    y: pad.top + chartH - ((s - minScore) / (maxScore - minScore)) * chartH
                };
            });

            ctx.beginPath(); ctx.moveTo(points[0].x, pad.top + chartH);
            points.forEach(function(p) { ctx.lineTo(p.x, p.y); });
            ctx.lineTo(points[points.length - 1].x, pad.top + chartH);
            ctx.closePath();
            var grad = ctx.createLinearGradient(0, pad.top, 0, pad.top + chartH);
            grad.addColorStop(0, 'rgba(13,110,253,0.2)'); grad.addColorStop(1, 'rgba(13,110,253,0.02)');
            ctx.fillStyle = grad; ctx.fill();

            ctx.beginPath(); ctx.moveTo(points[0].x, points[0].y);
            for (var j = 1; j < points.length; j++) ctx.lineTo(points[j].x, points[j].y);
            ctx.strokeStyle = '#0d6efd'; ctx.lineWidth = 2.5; ctx.stroke();

            points.forEach(function(p, idx) {
                ctx.beginPath(); ctx.arc(p.x, p.y, 4, 0, Math.PI * 2);
                ctx.fillStyle = '#0d6efd'; ctx.fill();
                ctx.strokeStyle = '#fff'; ctx.lineWidth = 2; ctx.stroke();
                ctx.fillStyle = '#888'; ctx.font = '9px sans-serif'; ctx.textAlign = 'center';
                ctx.fillText(labels[idx], p.x, pad.top + chartH + 16);
            });
        }

        /* Load Dashboard */
        function loadDashboard() {
            setProgress(20, 'Loading dashboard...');

            $.post(az_ajax.ajax_url, {
                action: 'az_get_dashboard_summary',
                nonce: az_ajax.nonce
            }, function(response) {
                if (response.success) {
                    var d = response.data;
                    $('#az-last-scan').text('Last scan: ' + (d.last_scan === 'Never' ? 'Never' : d.last_scan));
                    $('#az-scan-count').text('Scans: ' + d.scan_count);
                    updateGauge(d.health_score);

                    if (d.category_scores) {
                        $('#az-cat-security').css('width', d.category_scores.security + '%');
                        $('#az-cat-security-val').text(d.category_scores.security);
                        $('#az-cat-seo').css('width', d.category_scores.seo + '%');
                        $('#az-cat-seo-val').text(d.category_scores.seo);
                        $('#az-cat-performance').css('width', d.category_scores.performance + '%');
                        $('#az-cat-performance-val').text(d.category_scores.performance);
                        $('#az-cat-accessibility').css('width', d.category_scores.accessibility + '%');
                        $('#az-cat-accessibility-val').text(d.category_scores.accessibility);
                    }

                    $('#az-count-critical').text(d.critical);
                    $('#az-count-high').text(d.high);
                    $('#az-count-medium').text(d.medium);
                    $('#az-count-low').text(d.low);
                    $('#az-count-total').text(d.total);

                    drawPieChart(d.critical, d.high, d.medium, d.low);
                    drawTrendChart(d.history);

                    var si = d.server_info;
                    if (si) {
                        $('#az-server-info').html(
                            '<div class="az-server-row"><span>PHP</span><span class="az-server-val">' + escapeHtml(si.php_version) + '</span></div>' +
                            '<div class="az-server-row"><span>MySQL</span><span class="az-server-val">' + escapeHtml(si.mysql_version) + '</span></div>' +
                            '<div class="az-server-row"><span>WordPress</span><span class="az-server-val">' + escapeHtml(si.wp_version) + '</span></div>' +
                            '<div class="az-server-row"><span>Memory</span><span class="az-server-val">' + escapeHtml(si.wp_memory_limit) + '</span></div>' +
                            '<div class="az-server-row"><span>SSL</span><span class="az-server-val">' + (si.ssl_enabled ? 'Active' : 'Not Active') + '</span></div>' +
                            '<div class="az-server-row"><span>Server</span><span class="az-server-val">' + escapeHtml(si.server_software || 'N/A') + '</span></div>' +
                            '<div class="az-server-row"><span>Theme</span><span class="az-server-val">' + escapeHtml(si.theme) + '</span></div>' +
                            '<div class="az-server-row"><span>Cache</span><span class="az-server-val">' + (si.object_cache ? 'Enabled' : 'Disabled') + '</span></div>'
                        );
                    }

                    var recs = document.getElementById('az-recommendations');
                    var issues = d.issues || [];
                    if (issues.length === 0) {
                        recs.innerHTML = '<p class="az-empty-state">No issues found. Your site is in great shape!</p>';
                    } else {
                        recs.innerHTML = issues.slice(0, 5).map(function(issue) {
                            var sev = issue.severity || 'medium';
                            var icon = sev === 'critical' ? '!' : sev === 'high' ? '!' : sev === 'medium' ? 'i' : '\u2713';
                            return '<div class="az-rec-item">' +
                                '<span class="az-rec-icon ' + sev + '">' + icon + '</span>' +
                                '<div class="az-rec-text"><div class="az-rec-title">' + escapeHtml(issue.title) + '</div>' +
                                '<div class="az-rec-desc">' + escapeHtml(issue.description.substring(0, 100)) + '</div></div></div>';
                        }).join('');
                        if (issues.length > 5) {
                            recs.innerHTML += '<div class="az-rec-item" style="justify-content:center;color:#0d6efd;font-weight:600;">+' + (issues.length - 5) + ' more in Scanner tab</div>';
                        }
                    }

                    setProgress(100, 'Dashboard loaded');
                    setTimeout(hideProgress, 600);
                } else {
                    showAlert('Failed to load dashboard.', 'error');
                    hideProgress();
                }
            }, 'json').fail(function() {
                showAlert('Connection error loading dashboard. Check that the plugin is properly installed.', 'error');
                hideProgress();
            });
        }

        /* Full Scan */
        $('#az-scan-btn, #az-scan-btn-2').on('click', function() {
            var btn = $(this);
            btn.prop('disabled', true).text('Scanning...');
            setProgress(30, 'Running comprehensive scan...');

            $.post(az_ajax.ajax_url, {
                action: 'az_full_scan',
                nonce: az_ajax.nonce
            }, function(response) {
                if (response.success) {
                    var d = response.data;
                    setProgress(100, 'Scan complete!');
                    updateGauge(d.health_score);
                    $('#az-count-critical').text(d.critical);
                    $('#az-count-high').text(d.high);
                    $('#az-count-medium').text(d.medium);
                    $('#az-count-low').text(d.low);
                    $('#az-count-total').text(d.total);

                    if (d.category_scores) {
                        $('#az-cat-security').css('width', d.category_scores.security + '%');
                        $('#az-cat-security-val').text(d.category_scores.security);
                        $('#az-cat-seo').css('width', d.category_scores.seo + '%');
                        $('#az-cat-seo-val').text(d.category_scores.seo);
                        $('#az-cat-performance').css('width', d.category_scores.performance + '%');
                        $('#az-cat-performance-val').text(d.category_scores.performance);
                        $('#az-cat-accessibility').css('width', d.category_scores.accessibility + '%');
                        $('#az-cat-accessibility-val').text(d.category_scores.accessibility);
                    }

                    drawPieChart(d.critical, d.high, d.medium, d.low);
                    populateIssuesTable(d.issues);
                    showAlert('Scan complete: ' + d.total + ' issues. Health: ' + d.health_score + '/100', d.critical > 0 ? 'error' : 'success');
                } else {
                    showAlert('Scan failed.', 'error');
                }
                setTimeout(hideProgress, 800);
                btn.prop('disabled', false).text('Run Full Scan');
            }, 'json').fail(function() {
                showAlert('Connection error.', 'error');
                hideProgress();
                btn.prop('disabled', false).text('Run Full Scan');
            });
        });

        function populateIssuesTable(issues) {
            if (!issues || issues.length === 0) {
                $('#az-issues-table').hide();
                $('#az-scanner-results').html('<p class="az-empty-state">No issues found! Your site is well-optimized.</p>');
                return;
            }

            $('#az-scanner-results').empty();
            var tbody = $('#az-issues-body').empty();
            var typeColors = { security: 'security', seo: 'seo', performance: 'performance', accessibility: 'accessibility' };
            var severityColors = { critical: 'critical', high: 'high', medium: 'medium', low: 'low' };

            issues.forEach(function(issue) {
                var typeClass = typeColors[issue.type] || 'security';
                var severityClass = severityColors[issue.severity] || 'medium';
                var btnClass = issue.auto_fix ? 'az-btn-primary az-fix-btn auto' : 'az-btn-gray';
                var btnText = issue.auto_fix ? 'Fix Now' : 'Manual';
                var dataFix = issue.auto_fix ? ' data-fix="' + escapeAttr(issue.fix) + '"' : '';

                tbody.append('<tr><td><span class="az-badge az-badge-' + typeClass + '">' + escapeHtml(issue.type) + '</span></td>' +
                    '<td><span class="az-badge az-badge-' + severityClass + '">' + escapeHtml(issue.severity) + '</span></td>' +
                    '<td><strong>' + escapeHtml(issue.title) + '</strong><br><small>' + escapeHtml(issue.description) + '</small></td>' +
                    '<td><button class="az-btn ' + btnClass + '"' + dataFix + '>' + btnText + '</button></td></tr>');
            });

            $('#az-issues-table').show();
        }

        /* Fix Individual */
        $(document).on('click', '.az-fix-btn.auto', function() {
            var btn = $(this);
            var fixId = btn.data('fix');
            btn.prop('disabled', true).text('Fixing...');

            $.post(az_ajax.ajax_url, {
                action: 'az_fix_issue',
                nonce: az_ajax.nonce,
                fix_id: fixId
            }, function(response) {
                if (response.success) {
                    btn.text('Fixed').removeClass('az-btn-primary').addClass('az-btn-success');
                    btn.closest('tr').addClass('az-row-fixed');
                    showAlert(response.data.message, 'success');
                } else {
                    btn.text('Failed').prop('disabled', false);
                    showAlert(response.data.message || 'Fix failed.', 'error');
                }
            }, 'json').fail(function() {
                btn.text('Fix Now').prop('disabled', false);
                showAlert('Connection error.', 'error');
            });
        });

        /* Fix All Auto */
        $('#az-fix-all-btn, #az-fix-all-btn-2').on('click', function() {
            if (!confirm('Fix all auto-fixable issues? This may modify .htaccess, robots.txt, and database.')) return;
            var btn = $(this);
            btn.prop('disabled', true).text('Fixing all...');
            setProgress(10, 'Starting auto-fixes...');

            $.post(az_ajax.ajax_url, {
                action: 'az_fix_all_auto',
                nonce: az_ajax.nonce
            }, function(response) {
                if (response.success) {
                    var data = response.data;
                    setProgress(100, data.fixed + ' fixed, ' + data.failed + ' failed');
                    showAlert(data.fixed + ' fixed. ' + data.failed + ' failed.', data.failed > 0 ? 'error' : 'success');

                    if (data.health_score !== undefined) {
                        updateGauge(data.health_score);
                        if (data.category_scores) {
                            $('#az-cat-security').css('width', data.category_scores.security + '%');
                            $('#az-cat-security-val').text(data.category_scores.security);
                            $('#az-cat-seo').css('width', data.category_scores.seo + '%');
                            $('#az-cat-seo-val').text(data.category_scores.seo);
                            $('#az-cat-performance').css('width', data.category_scores.performance + '%');
                            $('#az-cat-performance-val').text(data.category_scores.performance);
                            $('#az-cat-accessibility').css('width', data.category_scores.accessibility + '%');
                            $('#az-cat-accessibility-val').text(data.category_scores.accessibility);
                        }
                        $('#az-count-critical').text(data.critical);
                        $('#az-count-high').text(data.high);
                        $('#az-count-medium').text(data.medium);
                        $('#az-count-low').text(data.low);
                        $('#az-count-total').text(data.total);
                        drawPieChart(data.critical, data.high, data.medium, data.low);
                        populateIssuesTable(data.issues);
                    } else {
                        setTimeout(function() { $('#az-scan-btn').click(); }, 1200);
                    }
                } else {
                    showAlert('Fix all failed.', 'error');
                    hideProgress();
                }
                setTimeout(hideProgress, 2000);
                btn.prop('disabled', false).text('Fix All Auto');
            }, 'json').fail(function() {
                showAlert('Connection error.', 'error');
                hideProgress();
                btn.prop('disabled', false).text('Fix All Auto');
            });
        });

        /* Export CSV */
        $('#az-export-btn, #az-export-btn-2').on('click', function() {
            var btn = $(this);
            btn.prop('disabled', true).text('Exporting...');
            var form = $('<form method="POST" action="' + az_ajax.ajax_url + '" style="display:none;">' +
                '<input type="hidden" name="action" value="az_export_report">' +
                '<input type="hidden" name="nonce" value="' + az_ajax.nonce + '">' +
                '<input type="hidden" name="format" value="csv"></form>');
            $('body').append(form);
            form.submit();
            form.remove();
            setTimeout(function() { btn.prop('disabled', false).text('Export CSV'); }, 1000);
            showAlert('Report downloaded.', 'success');
        });

        /* Email Report */
        $('#az-email-report-btn').on('click', function() {
            var btn = $(this);
            var email = $('#az-report-email').val();
            if (!email || !email.includes('@')) { showAlert('Enter a valid email.', 'error'); return; }
            btn.prop('disabled', true).text('Sending...');

            $.post(az_ajax.ajax_url, { action: 'az_send_report_email', nonce: az_ajax.nonce, email: email },
                function(r) {
                    if (r.success) { showAlert(r.data.message, 'success'); $('#az-email-result').html('<p style="color:#198754;">' + escapeHtml(r.data.message) + '</p>'); }
                    else { showAlert(r.data.message || 'Failed.', 'error'); }
                    btn.prop('disabled', false).text('Send');
                });
        });

        /* Schedule Form */
        $('#az-schedule-form').on('submit', function(e) {
            e.preventDefault();
            var btn = $(this).find('button[type="submit"]');
            btn.prop('disabled', true).text('Saving...');
            $.post(az_ajax.ajax_url, {
                action: 'az_save_schedule',
                nonce: az_ajax.nonce,
                enabled: $('#az-schedule-enabled').is(':checked'),
                frequency: $('#az-schedule-frequency').val(),
                email_alerts: $('#az-email-alerts').is(':checked'),
                alert_email: $('#az-alert-email').val()
            }, function(r) {
                if (r.success) { showAlert(r.data.message, 'success'); $('#az-schedule-result').html('<p style="color:#198754;">Saved.</p>'); }
                else { showAlert('Failed.', 'error'); }
                btn.prop('disabled', false).text('Save Schedule');
            });
        });

        /* Backup */
        $('#az-backup-btn, #az-backup-btn-2').on('click', function() {
            var btn = $(this);
            btn.prop('disabled', true).text('Backing up...');
            setProgress(30, 'Creating backup...');
            $.post(az_ajax.ajax_url, { action: 'az_backup_site', nonce: az_ajax.nonce },
                function(r) {
                    if (r.success) { setProgress(100, 'Backup created!'); showAlert('Backup "' + r.data.backup_name + '" created.', 'success'); loadBackups(); }
                    else { showAlert('Backup failed.', 'error'); }
                    setTimeout(hideProgress, 1200);
                    btn.prop('disabled', false).text('Create Backup');
                }).fail(function() { showAlert('Connection error.', 'error'); hideProgress(); btn.prop('disabled', false).text('Create Backup'); });
        });

        $(document).on('click', '.az-restore-btn', function() {
            var name = $(this).data('backup');
            if (!confirm('Restore "' + name + '"? Overwrites .htaccess, robots.txt, wp-config.php.')) return;
            var btn = $(this);
            btn.prop('disabled', true).text('Restoring...');
            $.post(az_ajax.ajax_url, { action: 'az_restore_backup', nonce: az_ajax.nonce, backup_name: name },
                function(r) { if (r.success) showAlert(r.data.message, 'success'); else showAlert(r.data.message || 'Failed.', 'error'); btn.prop('disabled', false).text('Restore'); });
        });

        $(document).on('click', '.az-delete-btn', function() {
            var name = $(this).data('backup');
            if (!confirm('Delete "' + name + '"? Cannot be undone.')) return;
            var btn = $(this);
            btn.prop('disabled', true).text('Deleting...');
            $.post(az_ajax.ajax_url, { action: 'az_delete_backup', nonce: az_ajax.nonce, backup_name: name },
                function(r) { if (r.success) { showAlert('Deleted.', 'info'); loadBackups(); } else showAlert('Failed.', 'error'); btn.prop('disabled', false).text('Delete'); });
        });

        /* AI */
        $('#az-ai-btn').on('click', function() {
            var btn = $(this);
            btn.prop('disabled', true).text('Analyzing...');
            $('#az-ai-result').hide().empty();
            $.post(az_ajax.ajax_url, { action: 'az_ai_analyze', nonce: az_ajax.nonce },
                function(r) {
                    if (r.success) {
                        var a = r.data.analysis;
                        if (a.error) $('#az-ai-result').html('<div class="az-alert az-alert-error">' + escapeHtml(a.error) + '</div>');
                        else $('#az-ai-result').html('<h3 style="margin-top:0;">AI Analysis</h3><pre style="white-space:pre-wrap;">' + escapeHtml(a.content) + '</pre>');
                        $('#az-ai-result').show();
                    } else showAlert('AI analysis failed.', 'error');
                    btn.prop('disabled', false).text('Run AI Analysis');
                });
        });

        $('#az-ai-form').on('submit', function(e) {
            e.preventDefault();
            var btn = $('#az-save-ai-btn');
            btn.prop('disabled', true).text('Saving...');
            $.post(az_ajax.ajax_url, {
                action: 'az_save_ai_settings',
                nonce: az_ajax.nonce,
                api_key: $('#az-api-key').val(),
                base_url: $('#az-base-url').val(),
                model: $('#az-model').val() === 'custom' ? $('#az-custom-model').val() : $('#az-model').val(),
                custom_model: $('#az-custom-model').val()
            }, function(r) { if (r.success) showAlert('AI settings saved.', 'success'); else showAlert('Failed.', 'error'); btn.prop('disabled', false).text('Save Settings'); });
        });

        /* Install Plugins */
        $('#az-install-plugins-btn').on('click', function() {
            var btn = $(this);
            btn.prop('disabled', true).text('Installing...');
            setProgress(20, 'Installing plugins...');
            $.post(az_ajax.ajax_url, { action: 'az_install_plugins', nonce: az_ajax.nonce },
                function(r) {
                    if (r.success) {
                        var html = '<ul style="margin:0;padding:0;list-style:none;">';
                        r.data.results.forEach(function(pl) {
                            var icon = pl.status === 'already_active' || pl.status === 'installed_and_activated' ? '&#9989;' : '&#10060;';
                            html += '<li style="padding:4px 0;">' + icon + ' ' + escapeHtml(pl.name) + ': ' + escapeHtml(pl.message) + '</li>';
                        });
                        html += '</ul>';
                        $('#az-plugins-result').html(html);
                        setProgress(100, 'Done!');
                        showAlert('Plugin installation complete.', 'success');
                    } else showAlert('Installation failed.', 'error');
                    setTimeout(hideProgress, 1200);
                    btn.prop('disabled', false).text('Install Plugins');
                });
        });

        /* Clean Junk */
        $('#az-clean-btn').on('click', function() {
            var btn = $(this);
            btn.prop('disabled', true).text('Cleaning...');
            $.post(az_ajax.ajax_url, { action: 'az_clean_junk', nonce: az_ajax.nonce },
                function(r) { if (r.success) { $('#az-clean-result').html('<p>' + escapeHtml(r.data.message) + '</p>'); showAlert(r.data.message, 'success'); } else showAlert('Clean failed.', 'error'); btn.prop('disabled', false).text('Clean Files'); });
        });

        /* Optimize DB */
        $('#az-db-btn').on('click', function() {
            var btn = $(this);
            btn.prop('disabled', true).text('Optimizing...');
            setProgress(30, 'Optimizing database...');
            $.post(az_ajax.ajax_url, { action: 'az_optimize_database', nonce: az_ajax.nonce },
                function(r) {
                    if (r.success) {
                        var html = '<ul style="margin:0;padding:0 0 0 16px;">';
                        r.data.details.forEach(function(d) { html += '<li>' + escapeHtml(d) + '</li>'; });
                        html += '</ul>';
                        $('#az-db-result').html(html);
                        setProgress(100, 'Database optimized!');
                        showAlert(r.data.message, 'success');
                    } else showAlert('DB optimization failed.', 'error');
                    setTimeout(hideProgress, 1200);
                    btn.prop('disabled', false).text('Optimize DB');
                });
        });

        /* Logs */
        $('#az-logs-btn').on('click', function() {
            var btn = $(this);
            btn.prop('disabled', true).text('Loading...');
            $.post(az_ajax.ajax_url, { action: 'az_get_logs', nonce: az_ajax.nonce },
                function(r) { if (r.success) $('#az-logs-content').html('<pre>' + escapeHtml(r.data.logs || 'No logs.') + '</pre>'); else $('#az-logs-content').html('<pre>Error.</pre>'); btn.prop('disabled', false).text('Refresh Logs'); });
        });

        /* Load Backups */
        function loadBackups() {
            $.post(az_ajax.ajax_url, { action: 'az_get_backups', nonce: az_ajax.nonce },
                function(r) {
                    if (r.success) {
                        var list = $('#az-backup-list');
                        var backups = r.data.backups;
                        if (!backups || backups.length === 0) { list.html('<p class="az-empty-state">No backups available.</p>'); return; }
                        list.html(backups.map(function(b) {
                            return '<div class="az-backup-item">' +
                                '<div class="az-backup-info"><h4>' + escapeHtml(b.name) + '</h4><span>' + escapeHtml(b.date) + ' &middot; ' + escapeHtml(b.size) + '</span></div>' +
                                '<div class="az-backup-actions">' +
                                '<button class="az-btn az-btn-primary az-restore-btn" data-backup="' + escapeAttr(b.name) + '">Restore</button>' +
                                '<button class="az-btn az-btn-danger az-delete-btn" data-backup="' + escapeAttr(b.name) + '">Delete</button></div></div>';
                        }).join(''));
                    }
                }, 'json');
        }

        /* Fetch AI Models */
        $('#az-fetch-models-btn').on('click', function() {
            var btn = $(this);
            btn.prop('disabled', true).text('Fetching...');
            $.post(az_ajax.ajax_url, { action: 'az_fetch_ai_models', nonce: az_ajax.nonce },
                function(r) {
                    if (r.success && r.data.models) {
                        var select = $('#az-model');
                        var currentVal = select.val();
                        select.find('option[value!="custom"]').remove();
                        r.data.models.forEach(function(m) {
                            select.append('<option value="' + escapeAttr(m) + '"' + (m === currentVal ? ' selected' : '') + '>' + escapeHtml(m) + '</option>');
                        });
                        if (!r.data.models.includes(currentVal) && currentVal !== 'custom') {
                            select.val(r.data.models[0]);
                        }
                        select.append('<option value="custom">Custom Model</option>');
                        showAlert('Found ' + r.data.models.length + ' models.', 'success');
                    } else {
                        showAlert(r.data.error || 'Failed to fetch models.', 'error');
                    }
                    btn.prop('disabled', false).text('Fetch Models');
                }).fail(function() { showAlert('Connection error.', 'error'); btn.prop('disabled', false).text('Fetch Models'); });
        });

        /* Custom model toggle */
        $('#az-model').on('change', function() {
            if ($(this).val() === 'custom') {
                $('#az-custom-model').show().focus();
            } else {
                $('#az-custom-model').hide();
            }
        });

        /* Auto Optimize */
        $('#az-auto-optimize-btn').on('click', function() {
            if (!confirm('Run full site optimization? This will:\n- Install Rank Math, Smush, Asset CleanUp\n- Configure SEO settings\n- Run all security/performance fixes\n- Generate sitemap\n- Clean junk files\n- Optimize database')) return;

            var btn = $(this);
            btn.prop('disabled', true).text('Optimizing...');
            setProgress(5, 'Starting full optimization...');
            $('#az-auto-optimize-result').html('');

            $.post(az_ajax.ajax_url, { action: 'az_auto_optimize', nonce: az_ajax.nonce },
                function(r) {
                    if (r.success) {
                        var html = '<ul style="margin:0;padding:0;list-style:none;">';
                        r.data.steps.forEach(function(s) {
                            var icon = s.indexOf('done') > -1 || s.indexOf('configured') > -1 || s.indexOf('applied') > -1 || s.indexOf('generated') > -1 || s.indexOf('cleaned') > -1 ? '&#9989;' : '&#9888;';
                            if (s.indexOf('Failed') > -1) icon = '&#10060;';
                            html += '<li style="padding:3px 0;font-size:13px;">' + icon + ' ' + escapeHtml(s) + '</li>';
                        });
                        html += '</ul>';
                        html += '<p style="color:#198754;font-weight:600;margin-top:10px;">' + escapeHtml(r.data.message) + '</p>';
                        $('#az-auto-optimize-result').html(html);
                        setProgress(100, 'Optimization complete!');
                        showAlert(r.data.message, 'success');
                        setTimeout(function() { $('#az-scan-btn').click(); }, 1500);
                    } else {
                        showAlert('Optimization failed.', 'error');
                        setProgress(0, 'Failed');
                    }
                    setTimeout(hideProgress, 2000);
                    btn.prop('disabled', false).text('Run Full Site Optimization');
                }).fail(function() { showAlert('Connection error.', 'error'); hideProgress(); btn.prop('disabled', false).text('Run Full Site Optimization'); });
        });

        /* Init */
        loadDashboard();
        loadBackups();

        /* Filters */
        $('#az-filter-type, #az-filter-severity').on('change', function() {
            var rows = $('#az-issues-body tr');
            var typeFilter = $('#az-filter-type').val();
            var sevFilter = $('#az-filter-severity').val();
            rows.each(function() {
                var row = $(this);
                var type = row.find('.az-badge').first().text().toLowerCase();
                var sev = row.find('.az-badge').eq(1).text().toLowerCase();
                row.toggle((typeFilter === 'all' || type === typeFilter) && (sevFilter === 'all' || sev === sevFilter));
            });
        });

    });
})(jQuery);
