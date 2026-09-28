<?php
/**
 * Uninstall cleanup: remove every user's saved dark mode preferences.
 *
 * @package EyeComfortDarkMode
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_metadata( 'user', 0, 'ecdm_prefs', '', true );
