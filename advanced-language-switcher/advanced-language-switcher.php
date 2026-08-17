<?php
/**
 * Plugin Name:       Advanced Language Switcher
 * Plugin URI:        https://example.com/advanced-language-switcher
 * Description:       A complete multilingual translation management system for WordPress with a deeply customizable Elementor language switcher widget, pluggable translation engines, WooCommerce support and multilingual SEO.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.1
 * Author:            Advanced Language Switcher
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       advanced-language-switcher
 * Domain Path:       /languages
 *
 * @package AdvancedLanguageSwitcher
 */

declare( strict_types=1 );

namespace ALS;

defined( 'ABSPATH' ) || exit;

define( 'ALS_VERSION', '1.0.0' );
define( 'ALS_PLUGIN_FILE', __FILE__ );
define( 'ALS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ALS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'ALS_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'ALS_TEXT_DOMAIN', 'advanced-language-switcher' );

/**
 * PSR-4 style autoloader for the ALS namespace.
 *
 * Maps `ALS\Sub\Namespace\Class_Name` onto WordPress style file names, e.g.
 * `ALS\Providers\Google_Provider` => includes/providers/class-google-provider.php
 *
 * @param string $class_name Fully qualified class name.
 * @return void
 */
function autoload( string $class_name ): void {
	if ( ! str_starts_with( $class_name, __NAMESPACE__ . '\\' ) ) {
		return;
	}

	$relative = substr( $class_name, strlen( __NAMESPACE__ ) + 1 );
	$parts    = explode( '\\', $relative );
	$short    = array_pop( $parts );
	$slug     = strtolower( str_replace( '_', '-', $short ) );

	// Interfaces and abstract classes follow the WordPress file naming
	// convention rather than the class- prefix.
	$candidates = array( 'class-' . $slug . '.php' );

	if ( str_ends_with( $slug, '-interface' ) ) {
		$candidates[] = 'interface-' . substr( $slug, 0, -10 ) . '.php';
	}

	if ( str_starts_with( $slug, 'abstract-' ) ) {
		$candidates[] = $slug . '.php';
	}

	$map = array(
		''            => 'includes/',
		'Providers'   => 'includes/providers/',
		'Admin'       => 'admin/',
		'Integration' => 'includes/',
		'Elementor'   => 'elementor/',
	);

	$group = implode( '\\', $parts );
	$dir   = $map[ $group ] ?? 'includes/' . strtolower( str_replace( '\\', '/', $group ) ) . '/';

	foreach ( $candidates as $candidate ) {
		$path = ALS_PLUGIN_DIR . $dir . $candidate;

		if ( is_readable( $path ) ) {
			require_once $path;

			return;
		}
	}
}

spl_autoload_register( __NAMESPACE__ . '\\autoload' );

require_once ALS_PLUGIN_DIR . 'includes/functions.php';

/**
 * Boots the plugin container once WordPress has loaded all plugins.
 *
 * @return Plugin
 */
function plugin(): Plugin {
	return Plugin::instance();
}

// Activation / deactivation hooks must be registered on the main file.
register_activation_hook( __FILE__, array( Installer::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( Installer::class, 'deactivate' ) );

// Multisite: provision tables when a new site is created.
add_action( 'wp_initialize_site', array( Installer::class, 'on_new_site' ), 20 );

plugin()->boot();
