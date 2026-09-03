<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class CA_DB {
    public static function install() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        
        $sql_sources = "CREATE TABLE {$wpdb->prefix}ca_sources (
            id int(11) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            url varchar(500) NOT NULL,
            type varchar(50) DEFAULT 'rss' NOT NULL,
            default_category int(11) DEFAULT 0 NOT NULL,
            enabled tinyint(1) DEFAULT 1 NOT NULL,
            auto_publish tinyint(1) DEFAULT 0 NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";
        dbDelta( $sql_sources );
        
        $sql_urls = "CREATE TABLE {$wpdb->prefix}ca_urls (
            id int(11) NOT NULL AUTO_INCREMENT,
            source_id int(11) NOT NULL,
            url varchar(500) NOT NULL,
            url_hash varchar(32) NOT NULL,
            content_hash varchar(32) DEFAULT '' NOT NULL,
            status varchar(30) DEFAULT 'pending' NOT NULL,
            cluster_id int(11) DEFAULT NULL,
            retry_count int(11) DEFAULT 0 NOT NULL,
            post_id int(11) DEFAULT NULL,
            discovered_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            processed_at datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY url_hash (url_hash)
        ) $charset_collate;";
        dbDelta( $sql_urls );
        
        $sql_logs = "CREATE TABLE {$wpdb->prefix}ca_logs (
            id int(11) NOT NULL AUTO_INCREMENT,
            time datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            action varchar(50) NOT NULL,
            level varchar(20) NOT NULL,
            message text NOT NULL,
            source_id int(11) DEFAULT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";
        dbDelta( $sql_logs );
    }
}