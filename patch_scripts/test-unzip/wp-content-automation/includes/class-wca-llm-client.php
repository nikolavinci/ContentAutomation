<?php
class WCA_LLM_Client {
    private $openai_key;
    private $gemini_key;

    public function __construct() {
        $options = get_option( "wca_settings" );
        $this->openai_key = isset( $options["openai_api_key"] ) ? $options["openai_api_key"] : "";
        $this->gemini_key = isset( $options["gemini_api_key"] ) ? $options["gemini_api_key"] : "";
    }

    public function generate_article( $prompt, $provider = "openai" ) {
        if ( $provider === "openai" && ! empty( $this->openai_key ) ) {
            return $this->call_openai( $prompt );
        } elseif ( $provider === "gemini" && ! empty( $this->gemini_key ) ) {
            return $this->call_gemini( $prompt );
        }
        return false;
    }

    private function call_openai( $prompt ) {
        $url = "https://api.openai.com/v1/chat/completions";
        $body = array(
            "model" => "gpt-4o",
            "messages" => array(
                array( "role" => "user", "content" => $prompt )
            )
        );

        $args = array(
            "body"        => wp_json_encode( $body ),
            "timeout"     => 120,
            "headers"     => array(
                "Content-Type"  => "application/json",
                "Authorization" => "Bearer " . $this->openai_key
            )
        );

        $response = wp_remote_post( $url, $args );
        if ( is_wp_error( $response ) ) {
            return false;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        return isset( $body["choices"][0]["message"]["content"] ) ? $body["choices"][0]["message"]["content"] : false;
    }

    private function call_gemini( $prompt ) {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-pro-latest:generateContent?key=" . $this->gemini_key;
        $body = array(
            "contents" => array(
                array(
                    "parts" => array(
                        array( "text" => $prompt )
                    )
                )
            )
        );

        $args = array(
            "body"        => wp_json_encode( $body ),
            "timeout"     => 120,
            "headers"     => array(
                "Content-Type" => "application/json"
            )
        );

        $response = wp_remote_post( $url, $args );
        if ( is_wp_error( $response ) ) {
            return false;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        return isset( $body["candidates"][0]["content"]["parts"][0]["text"] ) ? $body["candidates"][0]["content"]["parts"][0]["text"] : false;
    }
}

