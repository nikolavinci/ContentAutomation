<?php
class WCA_Engine {
    private $llm_client;
    private $aggregator;
    private $publisher;
    private $telegram;
    
    public function __construct() {
        $this->llm_client = new WCA_LLM_Client();
        $this->aggregator = new WCA_Aggregator();
        $this->publisher = new WCA_Publisher();
        $this->telegram = new WCA_Telegram();
    }

    public function run_automation() {
        $options = get_option( "wca_settings" );
        $niche = isset( $options["niche"] ) && !empty($options["niche"]) ? $options["niche"] : "technology";

        $source_data = $this->aggregator->gather_source_material( $niche );
        
        $prompt = $this->build_prompt( $niche, $source_data["angle"], $source_data["sources_text"] );
        $raw_content = $this->llm_client->generate_article( $prompt, "openai" ); 
        
        if ( ! $raw_content ) {
            $raw_content = $this->llm_client->generate_article( $prompt, "gemini" );
        }

        if ( ! $raw_content ) {
            error_log( "WCA: Content generation failed." );
            return;
        }

        $article = $this->parse_generated_content( $raw_content );

        // Add a free AI-generated Featured Image URL based on the headline
        $image_prompt = urlencode( "high quality editorial photo representing " . $article["title"] . " without text" );
        $article["featured_image_url"] = "https://image.pollinations.ai/prompt/{$image_prompt}?width=1280&height=720&nologo=true";

        $pub_result = $this->publisher->publish( $article, true );
        
        if ( $pub_result["success"] ) {
            $this->telegram->send_draft_alert( $article["title"], $pub_result["post_id"], $pub_result["url"] );
        }
    }

    private function build_prompt( $niche, $keyword_angle, $source_text ) {
        return "You are a professional business journalist writing for an international news platform covering {$niche}.
The specific topic/angle for this article is: {$keyword_angle}
You must synthesize the following source material to write a comprehensive, original news article. 
SOURCE MATERIAL:
{$source_text}

Format the article strictly using the following structure:
---
HEADLINE: <The main headline>
META_DESCRIPTION: <150-160 character SEO meta description>
FOCUS_KEYWORDS: <Comma separated list of focus keywords>
TAGS: <Comma separated list of relevant tags>
---
# <The main headline>
<Start the article here>
Use ## H2 and ### H3 subheadings for proper structure. Make sure the content flows logically with well-spaced paragraphs.";
    }

    private function parse_generated_content( $raw_content ) {
        $lines = explode( "\n", $raw_content );
        $metadata = array();
        $content_start = 0;
        
        $in_metadata = false;
        foreach ( $lines as $i => $line ) {
            if ( strpos( trim( $line ), "---" ) === 0 ) {
                $in_metadata = !$in_metadata;
                if ( !$in_metadata ) {
                    $content_start = $i + 1;
                    break;
                }
            } elseif ( $in_metadata && strpos( $line, ":" ) !== false ) {
                list( $key, $value ) = explode( ":", $line, 2 );
                $metadata[ trim( $key ) ] = trim( $value );
            }
        }
        
        $content = implode( "\n", array_slice( $lines, $content_start ) );
        
        return array(
            "title" => isset( $metadata["HEADLINE"] ) ? $metadata["HEADLINE"] : "Untitled",
            "content" => $content,
            "excerpt" => isset( $metadata["META_DESCRIPTION"] ) ? substr( $metadata["META_DESCRIPTION"], 0, 160 ) : "",
            "tags" => isset( $metadata["TAGS"] ) ? array_map( "trim", explode( ",", $metadata["TAGS"] ) ) : array(),
            "metadata" => $metadata
        );
    }
}

