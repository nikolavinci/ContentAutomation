<?php
/**
 * Plugin Name: Content Automaton
 * Plugin URI: https://nikolavinci.com
 * Description: Enterprise SEO/AEO/GEO/AIO Content Optimization pipeline. Synthesizes multi-source content seamlessly.
 * Version: 3.5.1
 * Author: nikolavinci
 * Author URI: https://nikolavinci.com
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'CA_DIR', plugin_dir_path( __FILE__ ) );
define( 'CA_URL', plugin_dir_url( __FILE__ ) );
define( 'CA_VERSION', '3.5.1' );

require_once CA_DIR . 'includes/db/class-ca-db.php';
require_once CA_DIR . 'includes/admin/class-ca-admin.php';
require_once CA_DIR . 'includes/api/class-ca-rest-bridge.php';
require_once CA_DIR . 'includes/queue/class-ca-queue.php';
require_once CA_DIR . 'includes/engine/class-ca-cluster-engine.php';
require_once CA_DIR . 'includes/engine/class-ca-ai-engine.php';
require_once CA_DIR . 'includes/engine/class-ca-image-engine.php';

register_activation_hook( __FILE__, [ 'CA_DB', 'install' ] );

add_action( 'plugins_loaded', function() {
    $db_version = get_option('ca_db_version', '0.0.0');
    if ($db_version !== CA_VERSION) {
        CA_DB::install();
        update_option('ca_db_version', CA_VERSION);
    }

    new CA_Admin();
    new CA_Rest_Bridge();
    new CA_Queue();
    new CA_Cluster_Engine();
    new CA_AI_Engine();
    new CA_Image_Engine();
} );