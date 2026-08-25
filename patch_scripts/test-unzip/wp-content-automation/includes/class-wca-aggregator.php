<?php
class WCA_Aggregator {
    public function gather_source_material( $niche ) {
        $rss_url = "https://news.google.com/rss/search?q=" . urlencode($niche);
        include_once( ABSPATH . WPINC . "/feed.php" );
        
        $rss = fetch_feed( $rss_url );
        if ( is_wp_error( $rss ) ) {
            return array( "angle" => $niche, "sources_text" => "" );
        }
        
        $maxitems = $rss->get_item_quantity( 3 ); 
        $rss_items = $rss->get_items( 0, $maxitems );
        
        $sources_text = "";
        $angle = $niche;
        
        if ( $maxitems > 0 ) {
            $angle = $rss_items[0]->get_title();
            
            foreach ( $rss_items as $item ) {
                $title = $item->get_title();
                $link = $item->get_permalink();
                $desc = strip_tags( $item->get_description() );
                
                $sources_text .= "\n--- Primary Source: $title ($link) ---\n$desc\n";
            }
        }
        
        return array( "angle" => $angle, "sources_text" => $sources_text );
    }
}

