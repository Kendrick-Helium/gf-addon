<?php
// Empêcher l'accès direct
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! defined( 'EXPORT_PLUGIN_URL' ) ) {
	define( 'EXPORT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

/**
 * Plugin Name: Gravity Forms Advanced Export Add-On
 * Plugin URI: https://gravityforms.com
 * Description: Exporter automatiquement vos entrées Gravity Form.
 * Version: 1.0.0
 * Author: Helium - Kendrick
 */

define( 'GF_ADVANCED_EXPORT_VERSION', '1.0' );

add_action( 'gform_loaded', array( 'GF_Advanced_Export_Bootstrap', 'load' ), 5 );

class GF_Advanced_Export_Bootstrap {
	public static function load() {
		if ( ! method_exists( 'GFForms', 'include_addon_framework' ) ) {
			return;
		}

		// Inclusion de la classe principale
		require_once( 'class-gf-advanced-export.php' );

		GFAddOn::register( 'GFAdvancedExport' );
	}
}

function gf_advanced_export() {
	return GFAdvancedExport::get_instance();
}