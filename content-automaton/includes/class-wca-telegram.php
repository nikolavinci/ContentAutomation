<?php
class NCA_Telegram {
    private $bot_token;
    private $chat_id;

    public function __construct() {
        $options = get_option( "NCA_settings" );
        $this->bot_token = isset( $options["telegram_bot_token"] ) ? $options["telegram_bot_token"] : "";
        $this->chat_id = isset( $options["telegram_chat_id"] ) ? $options["telegram_chat_id"] : "";
    }

    public function send_draft_alert( $title, $post_id, $url ) {
        if ( empty( $this->bot_token ) || empty( $this->chat_id ) ) {
            return;
        }

        $text = "?? *New Draft Ready*\n\n*Title:* $title\n*Link:* $url\n\nReview in WP Admin to publish it live.";
        $endpoint = "https://api.telegram.org/bot{$this->bot_token}/sendMessage";

        wp_remote_post( $endpoint, array(
            "body" => array(
                "chat_id" => $this->chat_id,
                "text" => $text,
                "parse_mode" => "Markdown"
            )
        ));
    }
}

