<?php
class NCA_Admin {
    public function __construct() {
        add_action( "admin_menu", array( $this, "add_plugin_pages" ) );
        add_action( "admin_init", array( $this, "page_init" ) );
        add_action( "admin_head", array( $this, "inject_modern_css_and_js" ) );
    }

    public function inject_modern_css_and_js() {
        if ( isset($_GET["page"]) && strpos($_GET["page"], "nca-") === 0 ) {
            ?>
            <style>
                .wca-admin-wrap { max-width: 900px; margin: 30px auto 20px 20px; font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; color: #0f172a; }
                .wca-header { margin-bottom: 2rem; }
                .wca-header h1 { font-size: 1.875rem; font-weight: 700; letter-spacing: -0.025em; color: #0f172a; margin: 0 0 0.5rem 0; padding: 0; }
                .wca-header p { font-size: 0.875rem; color: #64748b; margin: 0; }

                .wca-admin-wrap .form-table { background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 0.5rem; box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px -1px rgba(0, 0, 0, 0.1); border-collapse: separate; border-spacing: 0; width: 100%; margin-top: 1.5rem; overflow: hidden; }
                .wca-admin-wrap .form-table th { padding: 1.5rem; width: 35%; vertical-align: middle; font-size: 0.875rem; font-weight: 500; color: #0f172a; text-align: left; border-bottom: 1px solid #e2e8f0; }
                .wca-admin-wrap .form-table td { padding: 1.5rem; vertical-align: middle; border-bottom: 1px solid #e2e8f0; }
                .wca-admin-wrap .form-table tr:last-child th, .wca-admin-wrap .form-table tr:last-child td { border-bottom: none; }

                .wca-admin-wrap input[type="text"], .wca-admin-wrap input[type="password"], .wca-admin-wrap input[type="number"], .wca-admin-wrap select { display: block; height: 2.5rem; width: 100%; max-width: 450px; border-radius: 0.375rem; border: 1px solid #e2e8f0; background-color: transparent; padding: 0.5rem 0.75rem; font-size: 0.875rem; color: #0f172a; transition: all 0.15s ease; box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05); box-sizing: border-box; margin: 0; }
                .wca-admin-wrap textarea { display: block; min-height: 80px; width: 100%; max-width: 450px; border-radius: 0.375rem; border: 1px solid #e2e8f0; background-color: transparent; padding: 0.5rem 0.75rem; font-size: 0.875rem; color: #0f172a; transition: all 0.15s ease; box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05); line-height: 1.5; box-sizing: border-box; }
                .wca-admin-wrap input:focus, .wca-admin-wrap textarea:focus, .wca-admin-wrap select:focus { outline: none; border-color: #0f172a; box-shadow: 0 0 0 2px #fff, 0 0 0 4px #0f172a; }

                .wca-admin-wrap .submit { margin-top: 2rem; padding: 0; }
                .wca-admin-wrap .submit .button-primary { display: inline-flex !important; align-items: center !important; justify-content: center !important; border-radius: 0.375rem !important; font-size: 0.875rem !important; font-weight: 500 !important; height: 2.5rem !important; padding: 0 1.5rem !important; line-height: 1 !important; background-color: #0f172a !important; color: #f8fafc !important; border: none !important; box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05) !important; transition: background-color 0.15s ease !important; cursor: pointer !important; text-shadow: none !important; text-align: center !important; vertical-align: middle !important; }
                .wca-admin-wrap .submit .button-primary:hover { background-color: #1e293b !important; }

                .wca-toggle { position: relative; display: inline-flex; align-items: center; width: 44px; height: 24px; cursor: pointer; }
                .wca-toggle input { opacity: 0; width: 0; height: 0; position: absolute; }
                .wca-slider { position: absolute; top: 0; left: 0; right: 0; bottom: 0; background-color: #e2e8f0; border-radius: 9999px; transition: 0.2s; }
                .wca-slider:before { position: absolute; content: ""; height: 20px; width: 20px; left: 2px; bottom: 2px; background-color: white; border-radius: 50%; transition: 0.2s; box-shadow: 0 1px 2px rgba(0,0,0,0.1); }
                .wca-toggle input:checked + .wca-slider { background-color: #0f172a; }
                .wca-toggle input:focus-visible + .wca-slider { box-shadow: 0 0 0 2px #fff, 0 0 0 4px #0f172a; }
                .wca-toggle input:checked + .wca-slider:before { transform: translateX(20px); }

                .wca-description { display: block; margin-top: 0.5rem; color: #64748b; font-size: 0.8rem; line-height: 1.5; max-width: 450px; }
                
                .wca-table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 0.5rem; overflow: hidden; border: 1px solid #e2e8f0; margin-top: 1.5rem; }
                .wca-table th, .wca-table td { padding: 1rem; text-align: left; border-bottom: 1px solid #e2e8f0; font-size: 0.875rem; word-break: break-all; }
                .wca-table th { background-color: #f8fafc; font-weight: 500; color: #475569; }
                .wca-table tr:last-child td { border-bottom: none; }
                .wca-badge { display: inline-block; padding: 0.25rem 0.5rem; background: #e0e7ff; color: #3730a3; border-radius: 9999px; font-size: 0.75rem; font-weight: 500; }
                
                .wca-flex-row { display: flex; gap: 10px; align-items: center; max-width: 450px; }
                .wca-flex-row input, .wca-flex-row select { margin: 0 !important; }

                .wca-password-wrapper { position: relative; display: flex; align-items: center; max-width: 450px; }
                .wca-password-wrapper input { width: 100%; padding-right: 40px; }
                .wca-toggle-password { position: absolute; right: 10px; cursor: pointer; color: #94a3b8; transition: color 0.15s ease; width: 20px; height: 20px; display: flex; align-items: center; justify-content: center; }
                .wca-toggle-password:hover { color: #0f172a; }
            </style>
            <script>
            document.addEventListener("DOMContentLoaded", function() {
                document.querySelectorAll(".wca-toggle-password").forEach(function(icon) {
                    icon.addEventListener("click", function() {
                        const input = this.previousElementSibling;
                        if (input.type === "password") {
                            input.type = "text";
                            this.classList.remove("dashicons-visibility");
                            this.classList.add("dashicons-hidden");
                        } else {
                            input.type = "password";
                            this.classList.remove("dashicons-hidden");
                            this.classList.add("dashicons-visibility");
                        }
                    });
                });
            });
            </script>
            <?php
        }
    }

    public function add_plugin_pages() {
        add_menu_page( "Content Automaton", "Content Automaton", "manage_options", "nca-dashboard", array( $this, "create_admin_page" ), "dashicons-media-document", 30 );
        add_submenu_page( "nca-dashboard", "Dashboard & API", "Dashboard & API", "manage_options", "nca-dashboard", array( $this, "create_admin_page" ) );
        add_submenu_page( "nca-dashboard", "RSS Feeds", "RSS Feeds", "manage_options", "nca-feeds", array( $this, "create_feeds_page" ) );
        add_submenu_page( "nca-dashboard", "Taxonomy & SEO", "Taxonomy & SEO", "manage_options", "nca-taxonomy", array( $this, "create_taxonomy_page" ) );
        add_submenu_page( "nca-dashboard", "Automation Schedule", "Automation Schedule", "manage_options", "nca-schedule", array( $this, "create_schedule_page" ) );
        add_submenu_page( "nca-dashboard", "Media & Images", "Media & Images", "manage_options", "nca-media", array( $this, "create_media_page" ) );
        add_submenu_page( "nca-dashboard", "AI Content Library", "AI Content Library", "manage_options", "nca-content", array( $this, "create_content_page" ) );
        add_submenu_page( "nca-dashboard", "Crawled History", "Crawled History", "manage_options", "nca-history", array( $this, "create_history_page" ) );
        add_submenu_page( "nca-dashboard", "Logs & Usage", "Logs & Usage", "manage_options", "nca-logs", array( $this, "create_logs_page" ) );
    }

    public function create_admin_page() { $this->render_form("nca-dashboard", "Dashboard & Core API", "Configure your AI providers and core niche."); }
    public function create_feeds_page() { $this->render_form("nca-feeds", "Content Sources", "Tell the engine where to find inspiration."); }
    public function create_taxonomy_page() { $this->render_form("nca-taxonomy", "Categories, SEO & Language", "Set up formatting, languages, and category mappings."); }
    public function create_schedule_page() { $this->render_form("nca-schedule", "Automation Settings", "Control when and how often the AI should run."); }

    public function create_media_page() { $this->render_form("nca-media", "Media & Images", "Configure AI image generation or connect stock photo libraries."); }

    public function create_history_page() {
        ?>
        <div class="wrap wca-admin-wrap">
            <div class="wca-header">
                <h1>Crawled Source History</h1>
                <p>A log of all external RSS and sitemap URLs that have been successfully processed (to prevent duplicates).</p>
            </div>
            <table class="wca-table">
                <thead>
                    <tr>
                        <th style="width: 50px;">#</th>
                        <th>Source URL</th>
                        <th style="width: 150px;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $urls = get_option("NCA_processed_urls", array());
                    if (empty($urls)) { 
                        echo "<tr><td colspan=\"3\">No URLs have been crawled yet.</td></tr>"; 
                    } else {
                        $count = 1;
                        foreach ($urls as $url) {
                            echo "<tr>";
                            echo "<td>" . $count . "</td>";
                            echo "<td><a href=\"" . esc_attr($url) . "\" target=\"_blank\">" . esc_html($url) . "</a></td>";
                            echo "<td><span class=\"wca-badge\" style=\"background:#dcfce7;color:#166534;\">Processed</span></td>";
                            echo "</tr>";
                            $count++;
                        }
                    }
                    ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public function create_content_page() {
        ?>
        <div class="wrap wca-admin-wrap">
            <div class="wca-header">
                <h1>AI Content Library</h1>
                <p>View all articles and drafts generated exclusively by Content Automaton.</p>
            </div>
            <table class="wca-table">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Status</th>
                        <th>Date Generated</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $ai_posts = get_posts(array( "meta_key" => "_NCA_generated", "post_status" => "any", "posts_per_page" => 50 ));
                    if (empty($ai_posts)) { echo "<tr><td colspan=\"4\">No AI content generated yet.</td></tr>"; }
                    foreach ($ai_posts as $p) {
                        $edit_url = get_edit_post_link($p->ID);
                        $status = $p->post_status === "publish" ? "<span class=\"wca-badge\" style=\"background:#dcfce7;color:#166534;\">Published</span>" : "<span class=\"wca-badge\">Draft</span>";
                        echo "<tr>";
                        echo "<td><strong>" . esc_html($p->post_title) . "</strong></td>";
                        echo "<td>{$status}</td>";
                        echo "<td>" . get_the_date("Y-m-d H:i", $p->ID) . "</td>";
                        echo "<td><a href=\"{$edit_url}\">Edit Post</a></td>";
                        echo "</tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public function create_logs_page() {
        ?>
        <div class="wrap wca-admin-wrap">
            <div class="wca-header">
                <h1>Logs & Token Usage</h1>
                <p>Track your AI API costs and generation history.</p>
            </div>
            <table class="wca-table">
                <thead>
                    <tr>
                        <th>Timestamp</th>
                        <th>Generated Article</th>
                        <th>Tokens Used</th>
                        <th>Est. Cost</th>
                        <th>Link</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $logs = get_option("NCA_usage_logs", array());
                    if (empty($logs)) { echo "<tr><td colspan=\"5\">No logs recorded yet.</td></tr>"; }
                    foreach ($logs as $log) {
                        echo "<tr>";
                        echo "<td>" . esc_html($log["time"]) . "</td>";
                        echo "<td>" . esc_html($log["title"]) . "</td>";
                        echo "<td>" . esc_html($log["tokens"]) . "</td>";
                        echo "<td>$" . esc_html(number_format($log["cost"], 4)) . "</td>";
                        echo "<td><a href=\"" . esc_attr($log["url"]) . "\">View</a></td>";
                        echo "</tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    private function render_form($page_slug, $title, $subtitle) {
        ?>
        <div class="wrap wca-admin-wrap">
            <div class="wca-header">
                <h1><?php echo esc_html($title); ?></h1>
                <p><?php echo esc_html($subtitle); ?></p>
            </div>
            <form method="post" action="options.php">
            <?php
                settings_fields( "NCA_option_group" );
                do_settings_sections( $page_slug );
                submit_button("Save Changes");
            ?>
            </form>
        </div>
        <?php
    }

    public function page_init() {
        register_setting( "NCA_option_group", "NCA_settings" );

        add_settings_section( "nca_api_section", "", null, "nca-dashboard" );
        $this->add_field("nca-dashboard", "nca_api_section", "niche", "Content Niche", "text", "The overarching topic (e.g. \"Technology Startups\").");
        $this->add_field("nca-dashboard", "nca_api_section", "openai_api_key", "OpenAI API Key", "password", "");
        $this->add_field("nca-dashboard", "nca_api_section", "gemini_api_key", "Gemini API Key", "password", "");
        $this->add_field("nca-dashboard", "nca_api_section", "telegram_bot_token", "Telegram Bot Token", "password", "");
        $this->add_field("nca-dashboard", "nca_api_section", "telegram_chat_id", "Telegram Chat ID", "text", "");

        add_settings_section( "nca_feeds_section", "", null, "nca-feeds" );
        $this->add_field("nca-feeds", "nca_feeds_section", "rss_feeds", "Target RSS Feeds", "textarea", "");
        $this->add_field("nca-feeds", "nca_feeds_section", "competitor_sitemaps", "Competitor Sitemaps", "textarea", "");

        add_settings_section( "nca_taxonomy_section", "", null, "nca-taxonomy" );
        $this->add_field("nca-taxonomy", "nca_taxonomy_section", "target_language", "Target Language", "datalist", "Type to search or select a language.");
        $this->add_field("nca-taxonomy", "nca_taxonomy_section", "category_id", "Default Category ID", "number", "");
        $this->add_field("nca-taxonomy", "nca_taxonomy_section", "default_tags", "Default Tags", "text", "Comma separated tags.");

        
        add_settings_section( "nca_media_section", "", null, "nca-media" );
        $this->add_field("nca-media", "nca_media_section", "image_source", "Featured Image Source", "select", "Where should the plugin get featured images?", array(
            "pollinations" => "AI Generated (Pollinations - Free)",
            "pexels" => "Pexels (Requires API Key)",
            "pixabay" => "Pixabay (Requires API Key)"
        ));
        $this->add_field("nca-media", "nca_media_section", "pexels_api_key", "Pexels API Key", "password", "Required if using Pexels.");
        $this->add_field("nca-media", "nca_media_section", "pixabay_api_key", "Pixabay API Key", "password", "Required if using Pixabay.");

        add_settings_section( "nca_schedule_section", "", null, "nca-schedule" );
        $this->add_field("nca-schedule", "nca_schedule_section", "auto_fetch_enabled", "Master Switch", "checkbox", "Turn this on to enable background automated content generation.");
        $this->add_field("nca-schedule", "nca_schedule_section", "cron_value", "Generate Every...", "flex_number_select", "Specify how often the engine runs.", array(
            "minutes" => "Minutes",
            "hours" => "Hours",
            "days" => "Days"
        ));
    }

    private function add_field($page, $section, $id, $title, $type, $desc = "", $options_array = array()) {
        add_settings_field( $id, $title, array( $this, "render_field_callback" ), $page, $section, array( "id" => $id, "type" => $type, "desc" => $desc, "options" => $options_array ) );
    }

    public function render_field_callback($args) {
        $options = get_option( "NCA_settings" );
        $id = $args["id"];
        $type = $args["type"];
        $desc = $args["desc"];
        $val = isset( $options[$id] ) ? $options[$id] : "";

        if ( $type === "text" || $type === "number" ) {
            printf( "<input type=\"%s\" id=\"%s\" name=\"NCA_settings[%s]\" value=\"%s\" />", $type, $id, $id, esc_attr($val) );
        } elseif ( $type === "password" ) {
            echo "<div class=\"wca-password-wrapper\">";
            printf( "<input type=\"password\" id=\"%s\" name=\"NCA_settings[%s]\" value=\"%s\" />", $id, $id, esc_attr($val) );
            echo "<span class=\"dashicons dashicons-visibility wca-toggle-password\" title=\"Show/Hide\"></span>";
            echo "</div>";
        } elseif ( $type === "datalist" ) {
            printf( "<input type=\"text\" list=\"list_%s\" id=\"%s\" name=\"NCA_settings[%s]\" value=\"%s\" placeholder=\"e.g. English, Nepali, Spanish\" autocomplete=\"off\" />", $id, $id, $id, esc_attr($val) );
            echo "<datalist id=\"list_" . esc_attr($id) . "\">";
            $langs = ["English","Nepali","Spanish","French","German","Chinese","Hindi","Arabic","Portuguese","Russian","Japanese","Korean","Italian","Dutch"];
            foreach($langs as $l) echo "<option value=\"$l\">";
            echo "</datalist>";
        } elseif ( $type === "textarea" ) {
            printf( "<textarea id=\"%s\" name=\"NCA_settings[%s]\">%s</textarea>", $id, $id, esc_textarea($val) );
        } elseif ( $type === "checkbox" ) {
            $checked = checked( 1, $val, false );
            printf( "<label class=\"wca-toggle\"><input type=\"checkbox\" id=\"%s\" name=\"NCA_settings[%s]\" value=\"1\" %s /><span class=\"wca-slider\"></span></label>", $id, $id, $checked );
        } elseif ( $type === "flex_number_select" ) {
            $unit_val = isset( $options["cron_unit"] ) ? $options["cron_unit"] : "hours";
            $num_val = !empty($val) ? $val : "1";
            echo "<div class=\"wca-flex-row\">";
            printf( "<input type=\"number\" min=\"1\" id=\"%s\" name=\"NCA_settings[%s]\" value=\"%s\" style=\"flex: 1; max-width: 150px;\" />", $id, $id, esc_attr($num_val) );
            echo "<select name=\"NCA_settings[cron_unit]\" style=\"flex: 2; max-width: 290px;\">";
            foreach ( $args["options"] as $value => $label ) {
                $selected = selected( $unit_val, $value, false );
                echo "<option value=\"" . esc_attr($value) . "\" $selected>" . esc_html($label) . "</option>";
            }
            echo "</select>";
            echo "</div>";
        } elseif ( $type === "select" ) {
            echo "<select id=\"" . esc_attr($id) . "\" name=\"NCA_settings[" . esc_attr($id) . "]\">";
            foreach ( $args["options"] as $value => $label ) {
                $selected = selected( $val, $value, false );
                echo "<option value=\"" . esc_attr($value) . "\" $selected>" . esc_html($label) . "</option>";
            }
            echo "</select>";
        }
        if ( !empty($desc) ) echo "<span class=\"wca-description\">" . esc_html($desc) . "</span>";
    }
}
new NCA_Admin();
