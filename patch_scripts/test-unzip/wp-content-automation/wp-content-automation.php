<?php
/**
 * Plugin Name: Content Automaton
 * Plugin URI:  https://nikolavinci.com
 * Description: Generates, validates, and publishes content to WordPress automatically. Includes native SEO fallback for sites without dedicated SEO plugins.
 * Version:     1.0.0
 * Author:      nikolavinci
 * Author URI:  https://nikolavinci.com
 * License:     GPL-2.0+
 */

// If this file is called directly, abort.
if ( ! defined( "WPINC" ) ) {
    die;
}

define( "WCA_VERSION", "1.0.0" );
define( "WCA_PLUGIN_DIR", plugin_dir_path( __FILE__ ) );
define( "WCA_PLUGIN_URL", plugin_dir_url( __FILE__ ) );

// Include core classes
require_once WCA_PLUGIN_DIR . "includes/class-wca-llm-client.php";
require_once WCA_PLUGIN_DIR . "includes/class-wca-aggregator.php";
require_once WCA_PLUGIN_DIR . "includes/class-wca-publisher.php";
require_once WCA_PLUGIN_DIR . "includes/class-wca-telegram.php";
require_once WCA_PLUGIN_DIR . "includes/class-wca-engine.php";

if ( is_admin() ) {
    require_once WCA_PLUGIN_DIR . "includes/class-wca-admin.php";
}

/**
 * Initialize the plugin.
 */
function wca_init() {
    $engine = new WCA_Engine();
    
    // Register WP Cron hooks
    add_action( "wca_hourly_cron", array( $engine, "run_automation" ) );
}
add_action( "plugins_loaded", "wca_init" );

/**
 * Native SEO Output: Injects meta description & keywords if no SEO plugin is used.
 */
function wca_native_seo_meta() {
    if ( is_single() ) {
        global $post;
        
        $desc = get_post_meta( $post->ID, "_wca_meta_description", true );
        $keywords = get_post_meta( $post->ID, "_wca_focus_keywords", true );

        if ( ! empty( $desc ) ) {
            echo "<meta name=\"description\" content=\"" . esc_attr( $desc ) . "\" />\n";
        }
        if ( ! empty( $keywords ) ) {
            echo "<meta name=\"keywords\" content=\"" . esc_attr( $keywords ) . "\" />\n";
        }
    }
}
// Hook in early to wp_head
add_action( "wp_head", "wca_native_seo_meta", 1 );

/**
 * Native SEO Title Output: Overrides document title if native SEO title is set.
 */
function wca_native_seo_title( $title ) {
    if ( is_single() ) {
        global $post;
        $seo_title = get_post_meta( $post->ID, "_wca_seo_title", true );
        if ( ! empty( $seo_title ) ) {
            return esc_html( $seo_title );
        }
    }
    return $title;
}
add_filter( "pre_get_document_title", "wca_native_seo_title", 999 );

/**
 * Activation hook to schedule cron.
 */
function wca_activate() {
    if ( ! wp_next_scheduled( "wca_hourly_cron" ) ) {
        wp_schedule_event( time(), "hourly", "wca_hourly_cron" );
    }
}
register_activation_hook( __FILE__, "wca_activate" );

/**
 * Deactivation hook to clear cron.
 */
function wca_deactivate() {
    $timestamp = wp_next_scheduled( "wca_hourly_cron" );
    if ( $timestamp ) {
        wp_unschedule_event( $timestamp, "wca_hourly_cron" );
    }
}
register_deactivation_hook( __FILE__, "wca_deactivate" );

