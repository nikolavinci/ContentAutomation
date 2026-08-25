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

define( "NCA_VERSION", "1.0.0" );
define( "NCA_PLUGIN_DIR", plugin_dir_path( __FILE__ ) );
define( "NCA_PLUGIN_URL", plugin_dir_url( __FILE__ ) );

// Include core classes
require_once NCA_PLUGIN_DIR . "includes/class-wca-llm-client.php";
require_once NCA_PLUGIN_DIR . "includes/class-wca-aggregator.php";
require_once NCA_PLUGIN_DIR . "includes/class-wca-publisher.php";
require_once NCA_PLUGIN_DIR . "includes/class-wca-telegram.php";
require_once NCA_PLUGIN_DIR . "includes/class-wca-engine.php";

if ( is_admin() ) {
    require_once NCA_PLUGIN_DIR . "includes/class-wca-admin.php";
}

/**
 * Initialize the plugin.
 */
function NCA_init() {
    $engine = new NCA_Engine();
    add_action( "NCA_automation_cron", array( $engine, "run_automation" ) );
}
add_action( "plugins_loaded", "NCA_init" );

/**
 * Register custom cron intervals dynamically based on settings.
 */
function NCA_custom_cron_schedule( $schedules ) {
    $options = get_option( "NCA_settings" );
    $val = isset($options["cron_value"]) && !empty($options["cron_value"]) ? intval($options["cron_value"]) : 1;
    $unit = isset($options["cron_unit"]) ? $options["cron_unit"] : "hours";
    
    $sec = 3600;
    if ($unit == "minutes") $sec = $val * 60;
    if ($unit == "hours") $sec = $val * 3600;
    if ($unit == "days") $sec = $val * 86400;
    
    if ($sec < 300) $sec = 300; // Minimum 5 mins safety

    $schedules["nca_custom"] = array(
        "interval" => $sec,
        "display"  => "NCA Custom: Every $val $unit"
    );
    return $schedules;
}
add_filter( "cron_schedules", "NCA_custom_cron_schedule" );

/**
 * Sync the cron job with the master switch.
 */
function NCA_sync_cron() {
    $options = get_option("NCA_settings");
    $enabled = isset($options["auto_fetch_enabled"]) ? $options["auto_fetch_enabled"] : 0;
    $timestamp = wp_next_scheduled( "NCA_automation_cron" );
    
    // Also clear the old hourly one if it exists from previous version
    $old_timestamp = wp_next_scheduled( "NCA_hourly_cron" );
    if ( $old_timestamp ) wp_unschedule_event( $old_timestamp, "NCA_hourly_cron" );
    
    if ($enabled) {
        if ( ! $timestamp ) {
            wp_schedule_event( time(), "nca_custom", "NCA_automation_cron" );
        }
    } else {
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, "NCA_automation_cron" );
        }
    }
}
add_action("admin_init", "NCA_sync_cron");

/**
 * Native SEO Output: Injects meta description & keywords if no SEO plugin is used.
 */
function NCA_native_seo_meta() {
    if ( is_single() ) {
        global $post;
        $desc = get_post_meta( $post->ID, "_NCA_meta_description", true );
        $keywords = get_post_meta( $post->ID, "_NCA_focus_keywords", true );
        if ( ! empty( $desc ) ) echo "<meta name=\"description\" content=\"" . esc_attr( $desc ) . "\" />\n";
        if ( ! empty( $keywords ) ) echo "<meta name=\"keywords\" content=\"" . esc_attr( $keywords ) . "\" />\n";
    }
}
add_action( "wp_head", "NCA_native_seo_meta", 1 );

/**
 * Native SEO Title Output: Overrides document title if native SEO title is set.
 */
function NCA_native_seo_title( $title ) {
    if ( is_single() ) {
        global $post;
        $seo_title = get_post_meta( $post->ID, "_NCA_seo_title", true );
        if ( ! empty( $seo_title ) ) return esc_html( $seo_title );
    }
    return $title;
}
add_filter( "pre_get_document_title", "NCA_native_seo_title", 999 );

/**
 * Deactivation hook to clear cron.
 */
function NCA_deactivate() {
    $timestamp = wp_next_scheduled( "NCA_automation_cron" );
    if ( $timestamp ) {
        wp_unschedule_event( $timestamp, "NCA_automation_cron" );
    }
}
register_deactivation_hook( __FILE__, "NCA_deactivate" );

