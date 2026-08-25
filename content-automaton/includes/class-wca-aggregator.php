<?php
class NCA_Aggregator {
    public function gather_source_material( $niche ) {
        $options = get_option( 'NCA_settings' );
        $rss_feeds_text = isset($options['rss_feeds']) ? trim($options['rss_feeds']) : '';
        
        $urls = array();
        if ( !empty($rss_feeds_text) ) {
            $urls = array_filter(array_map('trim', explode("\n", $rss_feeds_text)));
        }
        
        // Fallback to Google News if no feeds defined
        if ( empty($urls) ) {
            $urls[] = 'https://news.google.com/rss/search?q=' . urlencode($niche) . '&hl=en-US&gl=US&ceid=US:en';
        }
        
        include_once( ABSPATH . WPINC . '/feed.php' );
        
        $processed_urls = get_option('NCA_processed_urls', array());
        
        $sources_text = '';
        $angle = $niche;
        $items_collected = 0;
        $new_processed = array();

        shuffle($urls);
        
        foreach ( $urls as $feed_url ) {
            if ($items_collected >= 3) break;

            $rss = fetch_feed( $feed_url );
            if ( is_wp_error( $rss ) ) continue;
            
            $maxitems = $rss->get_item_quantity( 10 ); 
            $rss_items = $rss->get_items( 0, $maxitems );
            
            foreach ( $rss_items as $item ) {
                $link = $item->get_permalink();
                
                // History Check
                if ( in_array($link, $processed_urls) || in_array($link, $new_processed) ) {
                    continue;
                }
                
                $title = $item->get_title();
                $desc = strip_tags( $item->get_description() );
                
                if ( $items_collected === 0 ) {
                    $angle = $title;
                }
                
                $sources_text .= "\n--- Source: $title ($link) ---\n$desc\n";
                $new_processed[] = $link;
                $items_collected++;
                
                if ($items_collected >= 3) break;
            }
        }

        if ( $items_collected === 0 ) {
            return false;
        }
        
        $updated_history = array_merge($new_processed, $processed_urls);
        if ( count($updated_history) > 1000 ) {
            $updated_history = array_slice($updated_history, 0, 1000);
        }
        update_option('NCA_processed_urls', $updated_history);
        
        return array( 'angle' => $angle, 'sources_text' => $sources_text );
    }
}
