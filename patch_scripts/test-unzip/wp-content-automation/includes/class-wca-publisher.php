<?php
class WCA_Publisher {
    public function publish( $article, $draft_only = true ) {
        $post_data = array(
            "post_title"    => $article["title"],
            "post_content"  => $article["content"],
            "post_excerpt"  => $article["excerpt"],
            "post_status"   => $draft_only ? "draft" : "publish",
            "post_author"   => 1,
            "tags_input"    => $article["tags"],
        );

        $post_id = wp_insert_post( $post_data );

        if ( is_wp_error( $post_id ) ) {
            return array( "success" => false, "error" => $post_id->get_error_message() );
        }

        // Apply SEO Meta Elements natively so user does not have to edit them
        if ( ! empty( $article["metadata"]["META_DESCRIPTION"] ) ) {
            $desc = $article["metadata"]["META_DESCRIPTION"];
            
            // Native plugin SEO
            update_post_meta( $post_id, "_wca_meta_description", $desc );
            
            // 3rd party SEO Plugins
            update_post_meta( $post_id, "_yoast_wpseo_metadesc", $desc ); // Yoast
            update_post_meta( $post_id, "rank_math_description", $desc ); // RankMath
            update_post_meta( $post_id, "_aioseop_description", $desc );  // AIOSEO
        }
        
        if ( ! empty( $article["metadata"]["FOCUS_KEYWORDS"] ) ) {
            $kw = $article["metadata"]["FOCUS_KEYWORDS"];
            
            // Native plugin SEO
            update_post_meta( $post_id, "_wca_focus_keywords", $kw );
            
            // 3rd party SEO Plugins
            update_post_meta( $post_id, "_yoast_wpseo_focuskw", $kw ); // Yoast
            update_post_meta( $post_id, "rank_math_focus_keyword", $kw ); // RankMath
        }
        
        if ( ! empty( $article["title"] ) ) {
            $seo_title_format = $article["title"] . " - " . get_bloginfo( "name" );
            
            // Native plugin SEO
            update_post_meta( $post_id, "_wca_seo_title", $seo_title_format );
            
            // 3rd party SEO Plugins
            update_post_meta( $post_id, "_yoast_wpseo_title", $article["title"] . " %%page%% %%sep%% %%sitename%%" );
            update_post_meta( $post_id, "rank_math_title", $article["title"] . " %page% %sep% %sitename%" );
        }

        // Attach Featured Image if URL is provided
        if ( ! empty( $article["featured_image_url"] ) ) {
            require_once( ABSPATH . "wp-admin/includes/media.php" );
            require_once( ABSPATH . "wp-admin/includes/file.php" );
            require_once( ABSPATH . "wp-admin/includes/image.php" );
            
            $image_id = media_sideload_image( $article["featured_image_url"], $post_id, $article["title"], "id" );
            
            if ( ! is_wp_error( $image_id ) ) {
                set_post_thumbnail( $post_id, $image_id );
            }
        }
        
        return array( 
            "success" => true, 
            "post_id" => $post_id, 
            "url" => get_permalink( $post_id ) 
        );
    }
}

