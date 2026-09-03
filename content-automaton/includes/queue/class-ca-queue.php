<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class CA_Queue {
    public function __construct() {
        add_filter('cron_schedules', [$this, 'custom_cron_schedule']);
        add_action('ca_process_discovery_queue', [$this, 'process_discovery']);
        add_action('ca_process_fetch_queue', [$this, 'process_fetch']);
        
        $status = get_option('ca_engine_status', 'running');
        add_action('init', function() {
            if (!wp_next_scheduled('ca_process_discovery_queue')) wp_schedule_event(time(), 'ca_custom_interval', 'ca_process_discovery_queue');
            if (!wp_next_scheduled('ca_process_fetch_queue')) wp_schedule_event(time(), 'ca_custom_interval', 'ca_process_fetch_queue');
            if (!wp_next_scheduled('ca_process_clustering_queue')) wp_schedule_event(time(), 'ca_custom_interval', 'ca_process_clustering_queue');
            if (!wp_next_scheduled('ca_process_generation_queue')) wp_schedule_event(time(), 'ca_custom_interval', 'ca_process_generation_queue');
            // if (!wp_next_scheduled('ca_process_image_queue')) wp_schedule_event(time(), 'ca_custom_interval', 'ca_process_image_queue');
        });
    }

    public function custom_cron_schedule($schedules) {
        $num = intval(get_option('ca_cron_num', 1));
        $unit = get_option('ca_cron_unit', 'hours');
        if ($num <= 0) $num = 1;
        $seconds = 3600;
        if ($unit == 'minutes') $seconds = $num * 60;
        elseif ($unit == 'hours') $seconds = $num * 3600;
        elseif ($unit == 'days') $seconds = $num * 86400;
        
        $schedules['ca_custom_interval'] = array('interval' => $seconds, 'display' => "Custom ($num $unit)");
        return $schedules;
    }

    public static function add_url_to_queue($source_id, $url) {
        global $wpdb;
        $url_hash = md5($url);
        $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}ca_urls WHERE url_hash = %s", $url_hash));
        if ($exists) return false;

        $wpdb->insert($wpdb->prefix . 'ca_urls', ['source_id' => $source_id, 'url' => $url, 'url_hash' => $url_hash, 'status' => 'pending'], ['%d', '%s', '%s', '%s']);
        return $wpdb->insert_id;
    }

    public function process_discovery() {
        global $wpdb;
        $wpdb->insert($wpdb->prefix . 'ca_logs', ['action' => 'discovery', 'level' => 'INFO', 'message' => "Starting discovery queue..."]);
        
        $sources = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}ca_sources");
        if (empty($sources)) {
            $wpdb->insert($wpdb->prefix . 'ca_logs', ['action' => 'discovery', 'level' => 'WARNING', 'message' => "No sources found in database."]);
            return;
        }
        
        include_once( ABSPATH . WPINC . '/feed.php' );
        add_filter('http_headers_useragent', function() { return 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'; }, 999);
        add_filter('http_request_timeout', function() { return 30; }, 999);

        foreach ($sources as $source) {
            if ($source->type == 'rss') {
                $rss = fetch_feed( $source->url );
                if ( is_wp_error( $rss ) ) {
                    $wpdb->insert($wpdb->prefix . 'ca_logs', ['action' => 'discovery', 'level' => 'ERROR', 'message' => "Failed to fetch RSS {$source->url}: " . $rss->get_error_message(), 'source_id' => $source->id]);
                    continue;
                }
                $maxitems = $rss->get_item_quantity( 5 ); 
                $rss_items = $rss->get_items( 0, $maxitems );
                
                $added = 0;
                foreach ( $rss_items as $item ) {
                    if ($item->get_permalink() && self::add_url_to_queue($source->id, $item->get_permalink())) {
                        $added++;
                    }
                }
                if ($added > 0) {
                    $wpdb->insert($wpdb->prefix . 'ca_logs', ['action' => 'discovery', 'level' => 'SUCCESS', 'message' => "Discovered $added new URLs from source {$source->name}", 'source_id' => $source->id]);
                }
            }
        }
    }

    public function process_fetch() {
        global $wpdb;
        $wpdb->insert($wpdb->prefix . 'ca_logs', ['action' => 'fetch', 'level' => 'INFO', 'message' => "Starting fetch queue..."]);
        
        $urls = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}ca_urls WHERE (status = 'pending' OR status = 'fetch_failed') AND retry_count < 3 ORDER BY id ASC LIMIT 10");
        if (empty($urls)) {
            $wpdb->insert($wpdb->prefix . 'ca_logs', ['action' => 'fetch', 'level' => 'INFO', 'message' => "No pending URLs to fetch."]);
            return;
        }
        
        foreach ($urls as $url_row) {
            $wpdb->update($wpdb->prefix . 'ca_urls', ['status' => 'processing'], ['id' => $url_row->id]);
            $response = wp_remote_get($url_row->url, [
                'timeout' => 30,
                'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'sslverify' => false
            ]);
            
            if (is_wp_error($response)) {
                $wpdb->insert($wpdb->prefix . 'ca_logs', ['action' => 'fetch', 'level' => 'ERROR', 'message' => "HTTP Request Failed for {$url_row->url}: " . $response->get_error_message()]);
                $wpdb->update($wpdb->prefix . 'ca_urls', ['status' => 'fetch_failed', 'retry_count' => $url_row->retry_count + 1], ['id' => $url_row->id]);
                continue;
            }
            if (wp_remote_retrieve_response_code($response) != 200) {
                $wpdb->insert($wpdb->prefix . 'ca_logs', ['action' => 'fetch', 'level' => 'ERROR', 'message' => "Received HTTP " . wp_remote_retrieve_response_code($response) . " for {$url_row->url}"]);
                $wpdb->update($wpdb->prefix . 'ca_urls', ['status' => 'dead'], ['id' => $url_row->id]);
                continue;
            }
            
            $content_hash = md5(wp_remote_retrieve_body($response));
            $content_exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}ca_urls WHERE content_hash = %s AND id != %d", $content_hash, $url_row->id));
            
            if ($content_exists) {
                $wpdb->insert($wpdb->prefix . 'ca_logs', ['action' => 'fetch', 'level' => 'WARNING', 'message' => "Duplicate content detected for {$url_row->url}"]);
                $wpdb->update($wpdb->prefix . 'ca_urls', ['status' => 'duplicate', 'content_hash' => $content_hash], ['id' => $url_row->id]);
                continue;
            }
            
            $title = 'Unknown Title';
            if (preg_match('/<title>(.*?)<\/title>/is', wp_remote_retrieve_body($response), $matches)) {
                $title = wp_strip_all_tags($matches[1]);
            }
            
            $wpdb->update($wpdb->prefix . 'ca_urls', [
                'status' => 'ready_for_clustering', 'content_hash' => $content_hash, 'processed_at' => current_time('mysql'), 'retry_count' => 0
            ], ['id' => $url_row->id]);
            
            $wpdb->insert($wpdb->prefix . 'ca_logs', ['action' => 'fetch', 'level' => 'SUCCESS', 'message' => "Successfully fetched and hashed: {$url_row->url}"]);
        }
    }
}