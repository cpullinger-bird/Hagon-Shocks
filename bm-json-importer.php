<?php

/*
Plugin Name: Hagon Shocks Json Product Importer
Plugin URI: http://URI_Of_Page_Describing_Plugin_and_Updates
Description: A brief description of the Plugin.
Version: 1.0
Author: Bird Marketing
Author URI: https://birdmarketing.co.uk
License: A "Slug" license name e.g. GPL2
Text Domain: bmhs-json-import
*/


// Define plugin Constants
define("BMHS_VERSION", "1.0.0");
define("BMHS_REQUIRED_WP_VERSION", "5.4");
define("BMHS_TEXT_DOMAIN", "bmhs-json-import");
define("BMHS_PLUGIN", __FILE__);
define("BMHS_PLUGIN_DIR", untrailingslashit( dirname( BMHS_PLUGIN )));
define("BMHS_IMPORT_FOLDER", "/home/hagonshockseagle/public_html/sap/import");
define("BMHS_EXPORT_FOLDER", "/home/hagonshockseagle/public_html/sap/export");
define("BMHS_TABLE_NAME", "bmhs_importer");


// Require Files
require_once('bootstraps.php');


// Pull in classes
use Bird\Tools\Bootstraps;

// Run plugin
function runBMHSPlugin(){
    new Bootstraps();
}

// Activation Hook
function runBMHSActivationHook(){

    // Create the custom table
    global $wpdb;

    $table_name = $wpdb->prefix . BMHS_TABLE_NAME;

    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        import_count int DEFAULT 0,
        update_count int DEFAULT 0,
        failed_count int DEFAULT 0
    ) $charset_collate;";

    require_once( ABSPATH . "wp-admin/includes/upgrade.php" );
    dbDelta( $sql );
}

// Activation Hook
function runBMHSDeactivationHook(){
    global $wpdb;
    $table_name = $wpdb->prefix . BMHS_TABLE_NAME ;
    $sql = "DROP TABLE IF EXISTS $table_name";
    $wpdb->query($sql);
}


// Base actions and hooks
add_action('init', 'runBMHSPlugin');
register_activation_hook( __FILE__, "runBMHSActivationHook" );
register_deactivation_hook( __FILE__, 'runBMHSDeactivationHook' );



//use Bird\Models\Ftp;
//
//$ftp = new Ftp();
//
//var_dump($ftp->ftp_nlist("./images"));
//die;
