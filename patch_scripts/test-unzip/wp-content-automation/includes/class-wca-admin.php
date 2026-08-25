<?php
class WCA_Admin {
    public function __construct() {
        add_action( "admin_menu", array( $this, "add_plugin_page" ) );
        add_action( "admin_init", array( $this, "page_init" ) );
    }

    public function add_plugin_page() {
        add_options_page(
            "WP Content Automation", 
            "Content Automation", 
            "manage_options", 
            "wp-content-automation", 
            array( $this, "create_admin_page" )
        );
    }

    public function create_admin_page() {
        ?>
        <div class="wrap">
            <h1>WP Content Automation</h1>
            <form method="post" action="options.php">
            <?php
                settings_fields( "wca_option_group" );
                do_settings_sections( "wp-content-automation" );
                submit_button();
            ?>
            </form>
        </div>
        <?php
    }

    public function page_init() {
        register_setting( "wca_option_group", "wca_settings" );

        add_settings_section(
            "wca_setting_section",
            "API Settings",
            null,
            "wp-content-automation"
        );

        add_settings_field(
            "openai_api_key",
            "OpenAI API Key",
            array( $this, "openai_api_key_callback" ),
            "wp-content-automation",
            "wca_setting_section"
        );
        
        add_settings_field(
            "gemini_api_key",
            "Gemini API Key",
            array( $this, "gemini_api_key_callback" ),
            "wp-content-automation",
            "wca_setting_section"
        );
        
        add_settings_field(
            "telegram_bot_token",
            "Telegram Bot Token",
            array( $this, "telegram_bot_token_callback" ),
            "wp-content-automation",
            "wca_setting_section"
        );
        
        add_settings_field(
            "telegram_chat_id",
            "Telegram Chat ID",
            array( $this, "telegram_chat_id_callback" ),
            "wp-content-automation",
            "wca_setting_section"
        );
        
        add_settings_field(
            "niche",
            "Content Niche",
            array( $this, "niche_callback" ),
            "wp-content-automation",
            "wca_setting_section"
        );
    }

    public function openai_api_key_callback() {
        $options = get_option( "wca_settings" );
        printf(
            "<input type=\"password\" id=\"openai_api_key\" name=\"wca_settings[openai_api_key]\" value=\"%s\" />",
            isset( $options["openai_api_key"] ) ? esc_attr( $options["openai_api_key"] ) : ""
        );
    }

    public function gemini_api_key_callback() {
        $options = get_option( "wca_settings" );
        printf(
            "<input type=\"password\" id=\"gemini_api_key\" name=\"wca_settings[gemini_api_key]\" value=\"%s\" />",
            isset( $options["gemini_api_key"] ) ? esc_attr( $options["gemini_api_key"] ) : ""
        );
    }

    public function telegram_bot_token_callback() {
        $options = get_option( "wca_settings" );
        printf(
            "<input type=\"password\" id=\"telegram_bot_token\" name=\"wca_settings[telegram_bot_token]\" value=\"%s\" />",
            isset( $options["telegram_bot_token"] ) ? esc_attr( $options["telegram_bot_token"] ) : ""
        );
    }
    
    public function telegram_chat_id_callback() {
        $options = get_option( "wca_settings" );
        printf(
            "<input type=\"text\" id=\"telegram_chat_id\" name=\"wca_settings[telegram_chat_id]\" value=\"%s\" />",
            isset( $options["telegram_chat_id"] ) ? esc_attr( $options["telegram_chat_id"] ) : ""
        );
    }
    
    public function niche_callback() {
        $options = get_option( "wca_settings" );
        printf(
            "<input type=\"text\" id=\"niche\" name=\"wca_settings[niche]\" value=\"%s\" />",
            isset( $options["niche"] ) ? esc_attr( $options["niche"] ) : ""
        );
    }
}
new WCA_Admin();

