<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class CA_Image_Engine {
    public function __construct() {
        add_action('ca_process_image_queue', [$this, 'process_images']);
        if (!wp_next_scheduled('ca_process_image_queue')) {
            wp_schedule_event(time(), 'ca_custom_interval', 'ca_process_image_queue');
        }
    }
    
    public function process_images() {
        global $wpdb;
        $wpdb->insert($wpdb->prefix . 'ca_logs', ['action' => 'image', 'level' => 'INFO', 'message' => "Starting Image Generation queue..."]);
        
        $urls = $wpdb->get_results("SELECT DISTINCT post_id, cluster_id FROM {$wpdb->prefix}ca_urls WHERE status = 'draft_created' LIMIT 2");
        
        if (empty($urls)) {
            $wpdb->insert($wpdb->prefix . 'ca_logs', ['action' => 'image', 'level' => 'INFO', 'message' => "No new drafts require featured images."]);
            return;
        }
        
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        
        foreach ($urls as $u) {
            $post_id = $u->post_id;
            
            if (has_post_thumbnail($post_id)) {
                $wpdb->update($wpdb->prefix . 'ca_urls', ['status' => 'completed'], ['cluster_id' => $u->cluster_id]);
                continue;
            }
            
            $image_prompt = get_post_meta($post_id, '_ai_image_prompt', true);
            $focus_keyword = get_post_meta($post_id, '_ai_focus_keyword', true);
            
            if (empty($image_prompt)) {
                $image_prompt = get_the_title($post_id);
            }
            
            // 1. Fetch Image URL
            $image_url = $this->fetch_from_providers($image_prompt);
            
            if (!$image_url) {
                $this->log_error("No image found for post ID {$post_id} using prompt: {$image_prompt}. Marking completed.");
                $wpdb->update($wpdb->prefix . 'ca_urls', ['status' => 'completed'], ['cluster_id' => $u->cluster_id]);
                continue;
            }
            
            // 2. Download Image
            $tmp_file = download_url($image_url);
            if (is_wp_error($tmp_file)) {
                $this->log_error("Failed to download image from API: " . $tmp_file->get_error_message());
                continue;
            }
            
            // 3. Convert to WebP & Rename for SEO
            $seo_slug = sanitize_title(!empty($focus_keyword) ? $focus_keyword : $image_prompt);
            if (empty($seo_slug)) $seo_slug = 'image-' . $post_id;
            
            $webp_file = $this->convert_to_webp($tmp_file, $seo_slug);
            @unlink($tmp_file);
            
            if (!$webp_file || is_wp_error($webp_file)) {
                $this->log_error("Failed to convert image to WebP format for SEO.");
                continue;
            }
            
            // 4. Sideload to WP Media Library
            $file_array = [
                'name' => basename($webp_file),
                'tmp_name' => $webp_file
            ];
            
            $attachment_id = media_handle_sideload($file_array, $post_id);
            
            if (is_wp_error($attachment_id)) {
                $this->log_error("Failed to attach WebP image to post: " . $attachment_id->get_error_message());
                @unlink($webp_file);
                continue;
            }
            
            // 5. Update SEO Meta (Alt Text)
            $alt_text = !empty($focus_keyword) ? $focus_keyword : get_the_title($post_id);
            update_post_meta($attachment_id, '_wp_attachment_image_alt', sanitize_text_field($alt_text));
            
            // Set Featured Image
            set_post_thumbnail($post_id, $attachment_id);
            
            // Update status to completed
            $wpdb->update($wpdb->prefix . 'ca_urls', ['status' => 'completed'], ['cluster_id' => $u->cluster_id]);
            $this->log_success("Successfully downloaded, converted to WebP, optimized Alt Text, and attached featured image to post ID {$post_id}.");
        }
    }
    
    private function fetch_from_providers($query) {
        $pexels_key = get_option('ca_pexels_key');
        if (!empty($pexels_key)) {
            $response = wp_remote_get("https://api.pexels.com/v1/search?query=" . urlencode($query) . "&per_page=1", [
                'headers' => ['Authorization' => $pexels_key],
                'timeout' => 15
            ]);
            if (!is_wp_error($response)) {
                $body = json_decode(wp_remote_retrieve_body($response), true);
                if (!empty($body['photos'][0]['src']['large'])) return $body['photos'][0]['src']['large'];
            }
        }
        
        $pixabay_key = get_option('ca_pixabay_key');
        if (!empty($pixabay_key)) {
            $response = wp_remote_get("https://pixabay.com/api/?key={$pixabay_key}&q=" . urlencode($query) . "&image_type=photo&per_page=3", ['timeout' => 15]);
            if (!is_wp_error($response)) {
                $body = json_decode(wp_remote_retrieve_body($response), true);
                if (!empty($body['hits'][0]['largeImageURL'])) return $body['hits'][0]['largeImageURL'];
            }
        }

        $unsplash_key = get_option('ca_unsplash_key');
        if (!empty($unsplash_key)) {
            $response = wp_remote_get("https://api.unsplash.com/search/photos?query=" . urlencode($query) . "&per_page=1", [
                'headers' => ['Authorization' => 'Client-ID ' . $unsplash_key],
                'timeout' => 15
            ]);
            if (!is_wp_error($response)) {
                $body = json_decode(wp_remote_retrieve_body($response), true);
                if (!empty($body['results'][0]['urls']['regular'])) return $body['results'][0]['urls']['regular'];
            }
        }
        
        return false;
    }
    
    private function convert_to_webp($source_file, $slug) {
        if (!function_exists('imagewebp')) return false; // Server doesn't support WebP
        
        $info = getimagesize($source_file);
        if (!$info) return false;
        
        $mime = $info['mime'];
        $image = null;
        
        if ($mime == 'image/jpeg') {
            $image = @imagecreatefromjpeg($source_file);
        } elseif ($mime == 'image/png') {
            $image = @imagecreatefrompng($source_file);
            if ($image) {
                imagepalettetotruecolor($image);
                imagealphablending($image, true);
                imagesavealpha($image, true);
            }
        } elseif ($mime == 'image/webp') {
            $dest_file = dirname($source_file) . '/' . $slug . '.webp';
            rename($source_file, $dest_file);
            return $dest_file;
        }
        
        if (!$image) return false;
        
        $dest_file = dirname($source_file) . '/' . $slug . '.webp';
        
        if (imagewebp($image, $dest_file, 80)) {
            imagedestroy($image);
            return $dest_file;
        }
        
        imagedestroy($image);
        return false;
    }
    
    private function log_error($message) {
        global $wpdb;
        $wpdb->insert($wpdb->prefix . 'ca_logs', ['action' => 'image', 'level' => 'ERROR', 'message' => $message]);
    }
    private function log_success($message) {
        global $wpdb;
        $wpdb->insert($wpdb->prefix . 'ca_logs', ['action' => 'image', 'level' => 'SUCCESS', 'message' => $message]);
    }
}