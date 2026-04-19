<?php
/**
 * Admin settings page (top-level menu).
 *
 * @package EvzenLeonenko\OpenGraphControl
 */

declare(strict_types=1);


namespace EvzenLeonenko\OpenGraphControl\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the top-level "Open Graph Control" admin menu item and renders
 * the React mount point. All interactive behavior lives in the settings
 * JS bundle (assets/admin/settings) enqueued by Admin\Assets.
 */
final class Page {

	public const MENU_SLUG = 'open-graph-control';

	public function register(): void {
		add_action( 'admin_menu', [ $this, 'add_menu' ] );
	}

	public function add_menu(): void {
		add_menu_page(
			__( 'Open Graph Control', 'open-graph-control' ),
			__( 'Open Graph Control', 'open-graph-control' ),
			'manage_options',
			self::MENU_SLUG,
			[ $this, 'render_shell' ],
			'dashicons-share',
			80
		);
	}

	public function render_shell(): void {
		echo '<div class="wrap" id="ogc-settings-root"></div>';
	}
}
