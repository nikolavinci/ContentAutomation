<?php
class NCA_Engine {
    private $llm_client;
    private $aggregator;
    private $publisher;
    private $telegram;
    
    public function __construct() {
        $this->llm_client = new NCA_LLM_Client();
        $this->aggregator = new NCA_Aggregator();
        $this->publisher = new NCA_Publisher();
        $this->telegram = new NCA_Telegram();
    }

    public function run_automation() {
        $options = get_option( 'NCA_settings' );
        
        if ( empty($options['auto_fetch_enabled']) || $options['auto_fetch_enabled'] != 1 ) {
            return;
        }

        $niche = isset( $options['niche'] ) && !empty($options['niche']) ? $options['niche'] : 'technology';
        $language = isset( $options['target_language'] ) && !empty($options['target_language']) ? $options['target_language'] : 'English';
        
        $source_data = $this->aggregator->gather_source_material( $niche );
        
        // Abort if no new fresh sources are found in history check
        if ( ! $source_data || empty($source_data['sources_text']) ) {
            error_log('NCA_Engine: No fresh sources found. Aborting generation to prevent duplicates.');
            return;
        }
        
        $recent_posts = get_posts(array(
            'numberposts' => 15,
            'post_status' => 'publish',
            'post_type' => 'post'
        ));
        $internal_links = '';
        if (!empty($recent_posts)) {
            foreach ($recent_posts as $p) {
                $url = get_permalink($p->ID);
                $internal_links .= '- ' . esc_html($p->post_title) . ' : ' . esc_url($url) . "\n";
            }
        }
        
        $prompt = $this->build_prompt( $niche, $source_data['angle'], $source_data['sources_text'], $language, $internal_links );
        $llm_result = $this->llm_client->generate_article( $prompt, 'openai' ); 
        
        if ( ! $llm_result ) {
            $llm_result = $this->llm_client->generate_article( $prompt, 'gemini' );
        }

        if ( ! $llm_result ) {
            return;
        }

        $raw_content = $llm_result['content'];
        $tokens = $llm_result['tokens'];
        $cost = $llm_result['cost'];

        $article = $this->parse_generated_content( $raw_content );

        if ( empty($article['tags']) && !empty($options['default_tags']) ) {
            $article['tags'] = array_map( 'trim', explode( ',', $options['default_tags'] ) );
        }
        $article['category_id'] = isset( $options['category_id'] ) ? intval($options['category_id']) : 1;

        $article['featured_image_url'] = $this->get_featured_image_url( $article['title'], $options );

        $pub_result = $this->publisher->publish( $article, true );
        
        if ( $pub_result['success'] ) {
            $this->telegram->send_draft_alert( $article['title'], $pub_result['post_id'], $pub_result['url'] );
            $this->log_usage($article['title'], $tokens, $cost, $pub_result['url']);
        }
    }

    private function log_usage($title, $tokens, $cost, $url) {
        $logs = get_option('NCA_usage_logs', array());
        array_unshift($logs, array(
            'time' => current_time('mysql'),
            'title' => $title,
            'tokens' => $tokens,
            'cost' => round($cost, 4),
            'url' => $url
        ));
        if (count($logs) > 100) $logs = array_slice($logs, 0, 100);
        update_option('NCA_usage_logs', $logs);
    }

    private function build_prompt( $niche, $keyword_angle, $source_text, $language, $internal_links ) {
        $links_instruction = '';
        if (!empty($internal_links)) {
            $links_instruction = "\nINTERNAL LINKS (SEO):\nHere is a list of existing published articles on our site:\n" . $internal_links . "\nCRITICAL SEO REQUIREMENT: You MUST naturally weave 2 or 3 of these internal links into the body text where contextually relevant. Use standard HTML tags for the links: <a href=\"URL\">relevant anchor text</a>.\n";
        }

        return "You are a professional business journalist writing for an international news platform covering {$niche}.
The specific topic/angle for this article is: {$keyword_angle}
You must synthesize the following source material to write a comprehensive, original news article. 
SOURCE MATERIAL:
{$source_text}
{$links_instruction}
Format the article strictly using the following structure:
---
HEADLINE: <The main headline>
META_DESCRIPTION: <150-160 character SEO meta description>
FOCUS_KEYWORDS: <Comma separated list of focus keywords>
TAGS: <Comma separated list of relevant tags>
---
<Start the article body here>
Use standard HTML tags (<h2>, <h3>, <p>, <strong>, etc.) for proper formatting. Do NOT use markdown. Make sure the content flows logically with well-spaced paragraphs.

CRITICAL INSTRUCTION: You must write the entire article, including the headline and metadata, exclusively in {$language}.";
    }

    private function get_featured_image_url( $title, $options ) {
        $source = isset($options['image_source']) ? $options['image_source'] : 'pollinations';
        
        $search_query = urlencode(implode(' ', array_slice(str_word_count($title, 1), 0, 4)));

        if ( $source === 'pexels' && !empty($options['pexels_api_key']) ) {
            $url = "https://api.pexels.com/v1/search?query={$search_query}&per_page=1&orientation=landscape";
            $response = wp_remote_get( $url, array(
                "headers" => array( "Authorization" => $options['pexels_api_key'] ),
                "timeout" => 15
            ));
            if ( ! is_wp_error( $response ) ) {
                $body = json_decode( wp_remote_retrieve_body( $response ), true );
                if ( !empty($body['photos'][0]['src']['large']) ) {
                    return $body['photos'][0]['src']['large'];
                }
            }
        } elseif ( $source === 'pixabay' && !empty($options['pixabay_api_key']) ) {
            $url = "https://pixabay.com/api/?key=" . $options['pixabay_api_key'] . "&q={$search_query}&image_type=photo&orientation=horizontal&per_page=3";
            $response = wp_remote_get( $url, array("timeout" => 15) );
            if ( ! is_wp_error( $response ) ) {
                $body = json_decode( wp_remote_retrieve_body( $response ), true );
                if ( !empty($body['hits'][0]['largeImageURL']) ) {
                    return $body['hits'][0]['largeImageURL'];
                }
            }
        }
        
        $image_prompt = urlencode( 'high quality editorial photo representing ' . $title . ' without text' );
        return 'https://image.pollinations.ai/prompt/' . $image_prompt . '?width=1280&height=720&nologo=true';
    }

    private function parse_generated_content( $raw_content ) {
        $raw_content = preg_replace('/^```html\n/i', '', trim($raw_content));
        $raw_content = preg_replace('/\n```$/', '', $raw_content);
        
        $lines = explode( "\n", $raw_content );
        $metadata = array();
        $content_start = 0;
        
        $in_metadata = false;
        foreach ( $lines as $i => $line ) {
            if ( strpos( trim( $line ), '---' ) === 0 ) {
                $in_metadata = !$in_metadata;
                if ( !$in_metadata ) {
                    $content_start = $i + 1;
                    break;
                }
            } elseif ( $in_metadata && strpos( $line, ':' ) !== false ) {
                list( $key, $value ) = explode( ':', $line, 2 );
                $metadata[ trim( $key ) ] = trim( $value );
            }
        }
        
        $content = implode( "\n", array_slice( $lines, $content_start ) );
        $content = preg_replace('/<h1>.*?<\/h1>/i', '', $content, 1);
        
        return array(
            'title' => isset( $metadata['HEADLINE'] ) ? $metadata['HEADLINE'] : 'Untitled',
            'content' => trim($content),
            'excerpt' => isset( $metadata['META_DESCRIPTION'] ) ? substr( $metadata['META_DESCRIPTION'], 0, 160 ) : '',
            'tags' => isset( $metadata['TAGS'] ) ? array_map( 'trim', explode( ',', $metadata['TAGS'] ) ) : array(),
            'metadata' => $metadata
        );
    }
}
