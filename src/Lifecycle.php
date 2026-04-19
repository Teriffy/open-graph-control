<?php
/**
 * Activation, deactivation and uninstall hooks.
 *
 * @package EvzenLeonenko\OpenGraphControl
 */

declare(strict_types=1);

namespace EvzenLeonenko\OpenGraphControl;

use EvzenLeonenko\OpenGraphControl\Options\DefaultSettings;

/**
 * Lifecycle handles plugin-level side effects around activation, deactivation
 * and uninstall. Kept as static methods so WP's register_*_hook functions can
 * reference them directly without a container lookup.
 */
final class Lifecycle {

	public const OPTION_KEY   = 'ogc_settings';
	public const POSTMETA_KEY = '_ogc_meta';

	public static function activate(): void {
		if ( false === get_option( self::OPTION_KEY ) ) {
			add_option( self::OPTION_KEY, DefaultSettings::all() );
		}
		flush_rewrite_rules();
	}

	public static function deactivate(): void {
		flush_rewrite_rules();
	}

	/**
	 * Hard uninstall. Deletes the plugin option and all per-post overrides.
	 * Invoked only from uninstall.php.
	 */
	public static function uninstall(): void {
		delete_option( self::OPTION_KEY );
		// Delete all postmeta rows with the plugin's meta key across all posts.
		delete_metadata( 'post', 0, self::POSTMETA_KEY, '', true );
	}
}
