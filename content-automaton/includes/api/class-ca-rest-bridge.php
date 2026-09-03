<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class CA_Rest_Bridge {
    public function __construct() {
        add_action('wp_ajax_ca_manual_run', [$this, 'ajax_manual_run']);
    }
    
    public function ajax_manual_run() {
        if (!current_user_can('publish_posts')) wp_send_json_error("Unauthorized");
        
        do_action('ca_process_discovery_queue');
        do_action('ca_process_fetch_queue');
        do_action('ca_process_clustering_queue');
        do_action('ca_process_generation_queue');
        // do_action('ca_process_image_queue');
        
        wp_send_json_success("Manual Execution Completed: Discovered, Fetched, Clustered, and Generated!");
    }
}