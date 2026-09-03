<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class CA_Admin {
    public function __construct() {
        add_action('admin_menu', [ $this, 'add_menu' ]);
    }

    public function add_menu() {
        add_menu_page('AI Content Automaton', 'AI Automaton', 'publish_posts', 'content-automaton', [ $this, 'render_dashboard' ], 'dashicons-superhero', 6);
    }

    public function render_dashboard() {
        $tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'dashboard';
        
        if (isset($_POST['ca_save_api_settings'])) {
            update_option('ca_engine_status', sanitize_text_field($_POST['ca_engine_status']));
            update_option('ca_cron_num', intval($_POST['ca_cron_num']));
            update_option('ca_cron_unit', sanitize_text_field($_POST['ca_cron_unit']));
            update_option('ca_timezone', sanitize_text_field($_POST['ca_timezone']));
            
            update_option('ca_ai_provider', sanitize_text_field($_POST['ca_ai_provider']));
            
            update_option('ca_openai_key', sanitize_text_field($_POST['ca_openai_key']));
            update_option('ca_openai_model', sanitize_text_field($_POST['ca_openai_model']));
            
            update_option('ca_gemini_key', sanitize_text_field($_POST['ca_gemini_key']));
            update_option('ca_gemini_model', sanitize_text_field($_POST['ca_gemini_model']));
            
            update_option('ca_groq_key', sanitize_text_field($_POST['ca_groq_key']));
            update_option('ca_groq_model', sanitize_text_field($_POST['ca_groq_model']));
            
            update_option('ca_deepseek_key', sanitize_text_field($_POST['ca_deepseek_key']));
            update_option('ca_deepseek_model', sanitize_text_field($_POST['ca_deepseek_model']));
            
            update_option('ca_qwen_key', sanitize_text_field($_POST['ca_qwen_key']));
            update_option('ca_qwen_model', sanitize_text_field($_POST['ca_qwen_model']));
            
            update_option('ca_unsplash_key', sanitize_text_field($_POST['ca_unsplash_key']));
            update_option('ca_pexels_key', sanitize_text_field($_POST['ca_pexels_key']));
            update_option('ca_pixabay_key', sanitize_text_field($_POST['ca_pixabay_key']));
            
            update_option('ca_custom_prompt', stripslashes($_POST['ca_custom_prompt']));
            update_option('ca_lang_slug', sanitize_text_field($_POST['ca_lang_slug']));
            update_option('ca_lang_meta', sanitize_text_field($_POST['ca_lang_meta']));
            update_option('ca_lang_tags', sanitize_text_field($_POST['ca_lang_tags']));
            
            wp_clear_scheduled_hook('ca_process_discovery_queue');
            wp_clear_scheduled_hook('ca_process_fetch_queue');
            wp_clear_scheduled_hook('ca_process_clustering_queue');
            wp_clear_scheduled_hook('ca_process_generation_queue');
            wp_clear_scheduled_hook('ca_process_image_queue');
            
            if ($_POST['ca_engine_status'] == 'running') {
                wp_schedule_event(time(), 'ca_custom_interval', 'ca_process_discovery_queue');
                wp_schedule_event(time(), 'ca_custom_interval', 'ca_process_fetch_queue');
                wp_schedule_event(time(), 'ca_custom_interval', 'ca_process_clustering_queue');
                wp_schedule_event(time(), 'ca_custom_interval', 'ca_process_generation_queue');
                // Image generation disabled per user request
                // wp_schedule_event(time(), 'ca_custom_interval', 'ca_process_image_queue');
            }
            
            echo '<div class="notice notice-success"><p>Settings Saved & Engine Updated!</p></div>';
        }
        
        if (isset($_POST['ca_add_source'])) {
            global $wpdb;
            $wpdb->insert($wpdb->prefix . 'ca_sources', [
                'name' => sanitize_text_field($_POST['source_name']),
                'url' => esc_url_raw($_POST['source_url']),
                'type' => 'rss',
                'default_category' => intval($_POST['source_category']),
                'auto_publish' => isset($_POST['auto_publish']) ? 1 : 0
            ]);
            echo '<div class="notice notice-success"><p>New Source Added Successfully!</p></div>';
        }
        
        if (isset($_GET['delete_source'])) {
            global $wpdb;
            $wpdb->delete($wpdb->prefix . 'ca_sources', ['id' => intval($_GET['delete_source'])]);
            echo '<div class="notice notice-success"><p>Source Deleted.</p></div>';
        }

        echo '<div class="wrap">';
        echo '<h1 style="margin-bottom:20px; font-weight:800; color:#2563eb;">AI Content Automaton</h1>';
        echo '<h2 class="nav-tab-wrapper" style="border-bottom: 2px solid #e5e7eb;">';
        echo '<a href="?page=content-automaton&tab=dashboard" class="nav-tab ' . ($tab == 'dashboard' ? 'nav-tab-active' : '') . '">Overview & Manual Run</a>';
        echo '<a href="?page=content-automaton&tab=usage" class="nav-tab ' . ($tab == 'usage' ? 'nav-tab-active' : '') . '">API Cost & Usage</a>';
        echo '<a href="?page=content-automaton&tab=sources" class="nav-tab ' . ($tab == 'sources' ? 'nav-tab-active' : '') . '">News Sources</a>';
        echo '<a href="?page=content-automaton&tab=archive" class="nav-tab ' . ($tab == 'archive' ? 'nav-tab-active' : '') . '">Archive & Drafts</a>';
        echo '<a href="?page=content-automaton&tab=logs" class="nav-tab ' . ($tab == 'logs' ? 'nav-tab-active' : '') . '">System Logs</a>';
        echo '<a href="?page=content-automaton&tab=settings" class="nav-tab ' . ($tab == 'settings' ? 'nav-tab-active' : '') . '">Engine Settings</a>';
        echo '</h2>';
        
        echo '<div style="background:#fff; padding:30px; border:1px solid #e5e7eb; border-top:none; border-radius: 0 0 8px 8px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);">';
        
        if ($tab == 'dashboard') $this->render_overview();
        elseif ($tab == 'usage') $this->render_usage();
        elseif ($tab == 'sources') $this->render_sources();
        elseif ($tab == 'archive') $this->render_archive();
        elseif ($tab == 'logs') $this->render_logs();
        elseif ($tab == 'settings') $this->render_settings();
        
        echo '</div></div>';
    }

    private function render_overview() {
        global $wpdb;
        $total_sources = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ca_sources");
        $pending_urls = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ca_urls WHERE status IN ('pending', 'fetch_failed', 'ready_for_clustering', 'clustered', 'ai_failed') AND retry_count < 3");
        $generated = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ca_urls WHERE status IN ('draft_created', 'completed')");
        $status = get_option('ca_engine_status', 'running');
        $color = $status == 'running' ? '#10b981' : '#ef4444';
        
        echo '<div style="display:grid; grid-template-columns: repeat(4, 1fr); gap:20px; margin-bottom:30px;">';
        $this->stat_card("Engine Status", strtoupper($status), $color);
        $this->stat_card("Active Sources", $total_sources ? $total_sources : 0, "#3b82f6");
        $this->stat_card("Articles in Queue", $pending_urls ? $pending_urls : 0, "#f59e0b");
        $this->stat_card("AI Articles Generated", $generated ? $generated : 0, "#8b5cf6");
        echo '</div>';
        
        echo '<h2>Manual Execution</h2>';
        echo '<div style="display:flex; gap:10px; margin-top:20px;">';
        echo '<button id="ca-btn-run" class="button button-primary button-large" style="background:#10b981; border-color:#059669;">▶ Force Run Queues Now</button>';
        echo '</div>';
        echo '<div id="ca-run-log" style="margin-top:15px; font-weight:bold; padding:15px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; display:none;"></div>';
        
        ?>
        <script>
        jQuery(document).ready(function($) {
            $('#ca-btn-run').click(function() {
                var log = $('#ca-run-log').show().html('<span style="color:#f59e0b;">Starting Queue Executions... (Check System Logs tab for errors if drafts do not appear)</span>');
                $.post(ajaxurl, {action: 'ca_manual_run'}, function(res) {
                    log.html('<span style="color:#10b981;">' + res.data + '</span>');
                }).fail(function() {
                    log.html('<span style="color:#ef4444;">Request timed out. The engine is running in the background!</span>');
                });
            });
        });
        </script>
        <?php
    }
    
    private function render_usage() {
        $total_tokens = get_option('ca_total_tokens', 0);
        $total_cost = get_option('ca_total_cost', 0);
        
        echo '<h2>API Cost & Usage Dashboard</h2>';
        echo '<p>Track your AI API expenses automatically. Estimates are based on standard provider pricing.</p>';
        
        echo '<div style="display:grid; grid-template-columns: repeat(2, 1fr); gap:20px; max-width:600px; margin-top:30px;">';
        $this->stat_card("Total Tokens Consumed", number_format($total_tokens), "#3b82f6");
        $this->stat_card("Total Est. Cost", "$" . number_format($total_cost, 4), "#10b981");
        echo '</div>';
        
        echo '<form method="post" style="margin-top:40px;">';
        echo '<input type="hidden" name="ca_reset_usage" value="1">';
        echo '<button type="submit" class="button button-secondary" onclick="return confirm(\'Reset usage stats?\');">Reset Statistics to Zero</button>';
        echo '</form>';
        
        if (isset($_POST['ca_reset_usage'])) {
            update_option('ca_total_tokens', 0);
            update_option('ca_total_cost', 0);
            echo '<script>window.location.href="?page=content-automaton&tab=usage";</script>';
        }
    }
    
    private function stat_card($title, $value, $color) {
        echo "<div style='background:#f8fafc; border:1px solid #e2e8f0; border-left:4px solid {$color}; padding:20px; border-radius:6px;'>";
        echo "<h3 style='margin:0 0 10px 0; color:#64748b; font-size:14px; text-transform:uppercase;'>{$title}</h3>";
        echo "<div style='font-size:24px; font-weight:800; color:#0f172a;'>{$value}</div>";
        echo "</div>";
    }

    private function render_sources() {
        global $wpdb;
        $sources = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}ca_sources ORDER BY id DESC");
        
        echo '<h2>Manage News Sources</h2>';
        echo '<table class="wp-list-table widefat fixed striped" style="margin-bottom:40px;">';
        echo '<thead><tr><th>Source Name</th><th>RSS URL</th><th>Category</th><th>Auto-Publish</th><th>Action</th></tr></thead><tbody>';
        if (empty($sources)) {
            echo '<tr><td colspan="5">No sources added yet. Add one below!</td></tr>';
        } else {
            foreach($sources as $s) {
                $cat = get_the_category_by_ID($s->default_category);
                $auto = $s->auto_publish ? '<span style="color:green;font-weight:bold;">Yes</span>' : 'No (Draft)';
                echo "<tr>
                    <td><strong>" . esc_html($s->name) . "</strong></td>
                    <td><a href='" . esc_url($s->url) . "' target='_blank'>" . esc_html($s->url) . "</a></td>
                    <td>{$cat}</td>
                    <td>{$auto}</td>
                    <td><a href='?page=content-automaton&tab=sources&delete_source={$s->id}' style='color:red;' onclick='return confirm(\"Delete this source?\");'>Delete</a></td>
                </tr>";
            }
        }
        echo '</tbody></table>';
        
        echo '<div style="background:#f8fafc; padding:20px; border:1px solid #e2e8f0; border-radius:6px; max-width:600px;">';
        echo '<h3 style="margin-top:0;">Add New RSS Source</h3>';
        echo '<form method="post">';
        echo '<p><label style="font-weight:bold;">Publisher Name:</label><br><input type="text" name="source_name" required style="width:100%;" placeholder="e.g. TechCrunch"></p>';
        echo '<p><label style="font-weight:bold;">RSS Feed URL:</label><br><input type="url" name="source_url" required style="width:100%;" placeholder="e.g. https://techcrunch.com/feed/"></p>';
        
        $categories = get_categories(['hide_empty' => 0]);
        echo '<p><label style="font-weight:bold;">Assign to Category:</label><br><select name="source_category" style="width:100%;">';
        foreach($categories as $cat) { echo "<option value='{$cat->term_id}'>{$cat->name}</option>"; }
        echo '</select></p>';
        
        echo '<p><label><input type="checkbox" name="auto_publish" value="1"> Automatically Publish (Skip Draft status)</label></p>';
        
        echo '<input type="hidden" name="ca_add_source" value="1">';
        echo '<p><button type="submit" class="button button-primary button-large">Add Source</button></p>';
        echo '</form></div>';
    }

    private function get_formatted_time($mysql_time) {
        if (!$mysql_time) return '-';
        $timezone = get_option('ca_timezone', 'Asia/Kathmandu');
        try {
            $dt = new DateTime($mysql_time, new DateTimeZone('UTC'));
            $dt->setTimezone(new DateTimeZone($timezone));
            return $dt->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            return $mysql_time;
        }
    }

    private function render_archive() {
        global $wpdb;
        
        $where = ["1=1"];
        if (!empty($_GET['archive_status'])) $where[] = $wpdb->prepare("status = %s", sanitize_text_field($_GET['archive_status']));
        
        $where_clause = implode(' AND ', $where);
        $drafts = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}ca_urls WHERE {$where_clause} ORDER BY id DESC LIMIT 500");
        
        echo '<h2>URL History & Archive</h2>';
        echo '<p>This is a complete record of all discovered URLs. The engine permanently remembers these to prevent duplicate articles from ever being generated again.</p>';
        
        echo '<form method="get" style="margin-bottom: 20px;">';
        echo '<input type="hidden" name="page" value="content-automaton">';
        echo '<input type="hidden" name="tab" value="archive">';
        echo '<select name="archive_status" style="margin-right:10px;"><option value="">All Statuses</option><option value="completed" ' . (isset($_GET['archive_status']) && $_GET['archive_status'] == 'completed' ? 'selected' : '') . '>Completed</option><option value="draft_created" ' . (isset($_GET['archive_status']) && $_GET['archive_status'] == 'draft_created' ? 'selected' : '') . '>Draft Created</option><option value="pending" ' . (isset($_GET['archive_status']) && $_GET['archive_status'] == 'pending' ? 'selected' : '') . '>Pending</option><option value="dead" ' . (isset($_GET['archive_status']) && $_GET['archive_status'] == 'dead' ? 'selected' : '') . '>Dead</option><option value="duplicate" ' . (isset($_GET['archive_status']) && $_GET['archive_status'] == 'duplicate' ? 'selected' : '') . '>Duplicate</option></select>';
        echo '<button type="submit" class="button">Filter</button>';
        echo ' <a href="?page=content-automaton&tab=archive" class="button">Clear</a>';
        echo '</form>';
        
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr><th>Discovered Date/Time</th><th>Article URL</th><th>Cluster ID</th><th>Processing Status</th><th>Retries</th><th>Generated WP Post</th></tr></thead><tbody>';
        
        if (empty($drafts)) {
            echo '<tr><td colspan="6">No articles processed yet.</td></tr>';
        } else {
            foreach($drafts as $d) {
                $status = strtoupper($d->status);
                $post_link = '-';
                if ($d->post_id) {
                    $edit_url = get_edit_post_link($d->post_id);
                    $title = get_the_title($d->post_id);
                    
                    // Live WP Post Status Badge
                    $wp_status = get_post_status($d->post_id);
                    $badge_color = '#64748b'; // default grey
                    if ($wp_status == 'publish') $badge_color = '#10b981'; // green
                    elseif ($wp_status == 'draft') $badge_color = '#f59e0b'; // orange
                    
                    $badge = '';
                    if ($wp_status) {
                        $badge = "<span style='background:{$badge_color}; color:#fff; padding:2px 6px; border-radius:4px; font-size:10px; font-weight:bold; margin-left:8px; display:inline-block; vertical-align:middle;'>" . strtoupper($wp_status) . "</span>";
                    }
                    
                    $post_link = "<a href='{$edit_url}' style='vertical-align:middle;'>Edit " . esc_html($title ? $title : '(No Title)') . "</a>" . $badge;
                }
                
                $formatted_date = $this->get_formatted_time($d->discovered_at);
                
                echo "<tr>
                    <td><strong>{$formatted_date}</strong></td>
                    <td><a href='" . esc_url($d->url) . "' target='_blank'>" . esc_html($d->url) . "</a></td>
                    <td>" . esc_html($d->cluster_id) . "</td>
                    <td><strong>{$status}</strong></td>
                    <td>{$d->retry_count}</td>
                    <td>{$post_link}</td>
                </tr>";
            }
        }
        echo '</tbody></table>';
    }
    
    private function render_logs() {
        global $wpdb;
        
        $where = ["1=1"];
        if (!empty($_GET['log_level'])) $where[] = $wpdb->prepare("level = %s", sanitize_text_field($_GET['log_level']));
        if (!empty($_GET['log_action'])) $where[] = $wpdb->prepare("action = %s", sanitize_text_field($_GET['log_action']));
        if (!empty($_GET['log_date'])) $where[] = $wpdb->prepare("DATE(time) = %s", sanitize_text_field($_GET['log_date']));
        
        $where_clause = implode(' AND ', $where);
        $logs = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}ca_logs WHERE {$where_clause} ORDER BY id DESC LIMIT 500");
        
        echo '<h2>System & Error Logs</h2>';
        
        echo '<form method="get" style="margin-bottom: 20px;">';
        echo '<input type="hidden" name="page" value="content-automaton">';
        echo '<input type="hidden" name="tab" value="logs">';
        echo '<select name="log_level" style="margin-right:10px;"><option value="">All Levels</option><option value="INFO" ' . (isset($_GET['log_level']) && $_GET['log_level'] == 'INFO' ? 'selected' : '') . '>INFO</option><option value="SUCCESS" ' . (isset($_GET['log_level']) && $_GET['log_level'] == 'SUCCESS' ? 'selected' : '') . '>SUCCESS</option><option value="ERROR" ' . (isset($_GET['log_level']) && $_GET['log_level'] == 'ERROR' ? 'selected' : '') . '>ERROR</option><option value="WARNING" ' . (isset($_GET['log_level']) && $_GET['log_level'] == 'WARNING' ? 'selected' : '') . '>WARNING</option></select>';
        echo '<select name="log_action" style="margin-right:10px;"><option value="">All Actions</option><option value="DISCOVERY" ' . (isset($_GET['log_action']) && $_GET['log_action'] == 'DISCOVERY' ? 'selected' : '') . '>DISCOVERY</option><option value="FETCH" ' . (isset($_GET['log_action']) && $_GET['log_action'] == 'FETCH' ? 'selected' : '') . '>FETCH</option><option value="CLUSTERING" ' . (isset($_GET['log_action']) && $_GET['log_action'] == 'CLUSTERING' ? 'selected' : '') . '>CLUSTERING</option><option value="GENERATION" ' . (isset($_GET['log_action']) && $_GET['log_action'] == 'GENERATION' ? 'selected' : '') . '>GENERATION</option></select>';
        echo '<input type="date" name="log_date" value="' . (isset($_GET['log_date']) ? esc_attr($_GET['log_date']) : '') . '" style="margin-right:10px;">';
        echo '<button type="submit" class="button">Filter Logs</button>';
        echo ' <a href="?page=content-automaton&tab=logs" class="button">Clear</a>';
        echo '</form>';
        
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr><th>Time</th><th>Level</th><th>Action</th><th>Message</th></tr></thead><tbody>';
        
        if (empty($logs)) {
            echo '<tr><td colspan="4">No logs generated yet.</td></tr>';
        } else {
            foreach($logs as $l) {
                $color = '';
                if ($l->level == 'ERROR') $color = 'color:red; font-weight:bold;';
                if ($l->level == 'SUCCESS') $color = 'color:green; font-weight:bold;';
                
                $formatted_time = $this->get_formatted_time($l->time);
                
                echo "<tr>
                    <td>" . esc_html($formatted_time) . "</td>
                    <td style='{$color}'>" . esc_html($l->level) . "</td>
                    <td>" . esc_html(strtoupper($l->action)) . "</td>
                    <td>" . esc_html($l->message) . "</td>
                </tr>";
            }
        }
        echo '</tbody></table>';
    }

    private function render_settings() {
        echo '<h2>Engine Configuration</h2>';
        echo '<form method="post" style="max-width:800px;">';
        
        echo '<h3 style="border-bottom:1px solid #ccc; padding-bottom:10px; margin-top:30px;">1. Execution & Scheduling</h3>';
        echo '<table class="form-table">';
        $status = get_option('ca_engine_status', 'running');
        echo '<tr><th><label style="font-weight:bold;">Engine Status</label></th><td>';
        echo '<select name="ca_engine_status" style="width:100%;"><option value="running" ' . selected($status, 'running', false) . '>Running (Cron Active)</option><option value="paused" ' . selected($status, 'paused', false) . '>Paused (Cron Disabled)</option></select></td></tr>';
        
        $cron_num = get_option('ca_cron_num', '1');
        $cron_unit = get_option('ca_cron_unit', 'hours');
        echo '<tr><th><label style="font-weight:bold;">Cron Schedule</label></th><td>';
        echo 'Run every <input type="number" name="ca_cron_num" value="' . esc_attr($cron_num) . '" min="1" style="width:80px;"> ';
        echo '<select name="ca_cron_unit"><option value="minutes" ' . selected($cron_unit, 'minutes', false) . '>Minutes</option><option value="hours" ' . selected($cron_unit, 'hours', false) . '>Hours</option><option value="days" ' . selected($cron_unit, 'days', false) . '>Days</option></select></td></tr>';
        
        $timezone = get_option('ca_timezone', 'Asia/Kathmandu');
        $timezones = [
            'UTC' => 'UTC',
            'Asia/Kathmandu' => 'Kathmandu, Nepal (NPT)',
            'Asia/Kolkata' => 'Kolkata, India (IST)',
            'America/New_York' => 'New York (EST)',
            'Europe/London' => 'London (GMT)'
        ];
        echo '<tr><th><label style="font-weight:bold;">Log & Archive Timezone</label></th><td>';
        echo '<select name="ca_timezone" style="width:100%;">';
        foreach ($timezones as $tz_val => $tz_label) {
            echo '<option value="' . esc_attr($tz_val) . '" ' . selected($timezone, $tz_val, false) . '>' . esc_html($tz_label) . '</option>';
        }
        echo '</select></td></tr>';
        
        echo '</table>';
        
        echo '<h3 style="border-bottom:1px solid #ccc; padding-bottom:10px; margin-top:30px;">2. AI Prompt & SEO Languages</h3>';
        echo '<table class="form-table">';
        
        $default_prompt = "Reword this article including the title and write it ENTIRELY in Nepali (every single paragraph, heading, and text must be in Nepali) to avoid plagiarism and suggest prompt for AI image generation, slug, tags, category (english) and nepali, meta description, and a short excerpt in Nepali.";
        $prompt = get_option('ca_custom_prompt', $default_prompt);
        echo '<tr><th><label style="font-weight:bold;">Custom System Prompt</label></th><td>';
        echo '<textarea name="ca_custom_prompt" rows="5" style="width:100%;">' . esc_textarea($prompt) . '</textarea></td></tr>';
        
        $lang_slug = get_option('ca_lang_slug', 'english');
        echo '<tr><th><label style="font-weight:bold;">Slug Language</label></th><td>';
        echo '<select name="ca_lang_slug" style="width:100%;"><option value="english" ' . selected($lang_slug, 'english', false) . '>English (SEO Friendly)</option><option value="nepali" ' . selected($lang_slug, 'nepali', false) . '>Nepali (Unicode)</option><option value="romanized" ' . selected($lang_slug, 'romanized', false) . '>Romanized Nepali</option></select></td></tr>';
        
        $lang_meta = get_option('ca_lang_meta', 'english');
        echo '<tr><th><label style="font-weight:bold;">Meta Description Language</label></th><td>';
        echo '<select name="ca_lang_meta" style="width:100%;"><option value="english" ' . selected($lang_meta, 'english', false) . '>English</option><option value="nepali" ' . selected($lang_meta, 'nepali', false) . '>Nepali</option></select></td></tr>';
        
        $lang_tags = get_option('ca_lang_tags', 'bilingual');
        echo '<tr><th><label style="font-weight:bold;">Tags & Keywords Language</label></th><td>';
        echo '<select name="ca_lang_tags" style="width:100%;"><option value="english" ' . selected($lang_tags, 'english', false) . '>English</option><option value="nepali" ' . selected($lang_tags, 'nepali', false) . '>Nepali</option><option value="bilingual" ' . selected($lang_tags, 'bilingual', false) . '>Bilingual (Both)</option></select></td></tr>';
        
        echo '</table>';

        echo '<h3 style="border-bottom:1px solid #ccc; padding-bottom:10px; margin-top:30px;">3. AI Text Generation Providers</h3>';
        echo '<table class="form-table">';
        
        $provider = get_option('ca_ai_provider', 'gemini');
        echo '<tr><th><label style="font-weight:bold;">Active AI Provider</label></th><td>';
        echo '<select name="ca_ai_provider" style="width:100%;">';
        echo '<option value="openai" ' . selected($provider, 'openai', false) . '>OpenAI</option>';
        echo '<option value="gemini" ' . selected($provider, 'gemini', false) . '>Google Gemini</option>';
        echo '<option value="groq" ' . selected($provider, 'groq', false) . '>Groq (Fast Open-Source)</option>';
        echo '<option value="deepseek" ' . selected($provider, 'deepseek', false) . '>DeepSeek</option>';
        echo '<option value="qwen" ' . selected($provider, 'qwen', false) . '>Alibaba Qwen</option>';
        echo '</select></td></tr>';
        
        // Gemini
        echo '<tr><th><label style="font-weight:bold;">Gemini Model & API Key</label></th><td>';
        echo '<input type="text" name="ca_gemini_model" value="' . esc_attr(get_option('ca_gemini_model', 'gemini-3.6-flash')) . '" style="width:30%; margin-right:2%;" placeholder="e.g. gemini-3.6-flash">';
        echo '<input type="password" name="ca_gemini_key" value="' . esc_attr(get_option('ca_gemini_key')) . '" style="width:68%;" placeholder="Gemini API Key"></td></tr>';
        
        // OpenAI
        echo '<tr><th><label style="font-weight:bold;">OpenAI Model & API Key</label></th><td>';
        echo '<input type="text" name="ca_openai_model" value="' . esc_attr(get_option('ca_openai_model', 'gpt-4o-mini')) . '" style="width:30%; margin-right:2%;" placeholder="e.g. gpt-4o-mini">';
        echo '<input type="password" name="ca_openai_key" value="' . esc_attr(get_option('ca_openai_key')) . '" style="width:68%;" placeholder="OpenAI API Key"></td></tr>';
        
        // Groq
        echo '<tr><th><label style="font-weight:bold;">Groq Model & API Key</label></th><td>';
        echo '<input type="text" name="ca_groq_model" value="' . esc_attr(get_option('ca_groq_model', 'llama3-70b-8192')) . '" style="width:30%; margin-right:2%;" placeholder="e.g. llama3-70b-8192">';
        echo '<input type="password" name="ca_groq_key" value="' . esc_attr(get_option('ca_groq_key')) . '" style="width:68%;" placeholder="Groq API Key"></td></tr>';
        
        // DeepSeek
        echo '<tr><th><label style="font-weight:bold;">DeepSeek Model & API Key</label></th><td>';
        echo '<input type="text" name="ca_deepseek_model" value="' . esc_attr(get_option('ca_deepseek_model', 'deepseek-chat')) . '" style="width:30%; margin-right:2%;" placeholder="e.g. deepseek-chat">';
        echo '<input type="password" name="ca_deepseek_key" value="' . esc_attr(get_option('ca_deepseek_key')) . '" style="width:68%;" placeholder="DeepSeek API Key"></td></tr>';
        
        // Qwen
        echo '<tr><th><label style="font-weight:bold;">Qwen Model & API Key</label></th><td>';
        echo '<input type="text" name="ca_qwen_model" value="' . esc_attr(get_option('ca_qwen_model', 'qwen-turbo')) . '" style="width:30%; margin-right:2%;" placeholder="e.g. qwen-turbo">';
        echo '<input type="password" name="ca_qwen_key" value="' . esc_attr(get_option('ca_qwen_key')) . '" style="width:68%;" placeholder="Qwen API Key"></td></tr>';
        
        echo '</table>';

        echo '<h3 style="border-bottom:1px solid #ccc; padding-bottom:10px; margin-top:30px;">4. Image Generation API Keys</h3>';
        echo '<table class="form-table">';
        echo '<tr><th><label style="font-weight:bold;">Unsplash API Key</label></th><td>';
        echo '<input type="password" name="ca_unsplash_key" value="' . esc_attr(get_option('ca_unsplash_key')) . '" style="width:100%;"></td></tr>';
        
        echo '<tr><th><label style="font-weight:bold;">Pexels API Key</label></th><td>';
        echo '<input type="password" name="ca_pexels_key" value="' . esc_attr(get_option('ca_pexels_key')) . '" style="width:100%;"></td></tr>';
        
        echo '<tr><th><label style="font-weight:bold;">Pixabay API Key</label></th><td>';
        echo '<input type="password" name="ca_pixabay_key" value="' . esc_attr(get_option('ca_pixabay_key')) . '" style="width:100%;"></td></tr>';
        
        echo '</table>';
        
        echo '<input type="hidden" name="ca_save_api_settings" value="1">';
        echo '<p style="margin-top:20px;"><button type="submit" class="button button-primary button-large">Save Settings & Restart Engine</button></p>';
        echo '</form>';
    }
}