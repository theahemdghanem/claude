<?php
/**
 * Plugin Name: Eye Comfort Dark Mode
 * Description: A real dark mode for the WordPress dashboard. Recolours every admin screen — core, the block editor and other plugins' pages — with no white flash. Per-user: pick always on, follow your system, palette and text brightness from the toolbar. Toggle anywhere with Alt+Shift+D.
 * Version:     1.0.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author:      iLike Agency
 * Text Domain: eye-comfort-dark-mode
 * License:     GPL-2.0-or-later
 *
 * @package EyeComfortDarkMode
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ECDM_VERSION', '1.0.0' );
define( 'ECDM_FILE', __FILE__ );
define( 'ECDM_URL', plugin_dir_url( __FILE__ ) );

require_once __DIR__ . '/includes/class-ecdm-plugin.php';

ECDM_Plugin::init();
