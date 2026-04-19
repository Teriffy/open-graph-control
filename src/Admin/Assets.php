<?php
/**
 * Admin asset registration.
 *
 * @package EvzenLeonenko\OpenGraphControl
 */

declare(strict_types=1);


namespace EvzenLeonenko\OpenGraphControl\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Enqueues the compiled React bundles on the plugin's settings page and on
 * every post-edit screen. Hands a small boot payload (REST root, nonce,
 * plugin version, enabled platforms) to each bundle via wp_localize_script.
 */
final class Assets {

	public function register(): void {
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
	}

	public function enqueue( string $hook ): void {
		if ( str_contains( $hook, Page::MENU_SLUG ) ) {
			$this->enqueue_bundle( 'settings' );
		}
		if ( 'post.php' === $hook || 'post-new.php' === $hook ) {
			$this->enqueue_bundle( 'metabox' );
		}
	}

	private function enqueue_bundle( string $name ): void {
		$asset_file = OGC_DIR . 'build/admin/' . $name . '.asset.php';
		if ( ! file_exists( $asset_file ) ) {
			return;
		}
		/** @var array{dependencies?: array<int, string>, version?: string} $asset */
		$asset = include $asset_file;

		wp_enqueue_script(
			'ogc-' . $name,
			OGC_URL . 'build/admin/' . $name . '.js',
			$asset['dependencies'] ?? [],
			$asset['version'] ?? OGC_VERSION,
			true
		);

		wp_set_script_translations( 'ogc-' . $name, 'open-graph-control', OGC_DIR . 'languages' );

		wp_localize_script( 'ogc-' . $name, 'OGC_BOOT', $this->boot_payload() );

		$css_path = OGC_DIR . 'build/admin/' . $name . '.css';
		if ( file_exists( $css_path ) ) {
			wp_enqueue_style(
				'ogc-' . $name,
				OGC_URL . 'build/admin/' . $name . '.css',
				[],
				$asset['version'] ?? OGC_VERSION
			);
		}
	}

	/**
	 * @return array<string, mixed>
	 */
	private function boot_payload(): array {
		return [
			'apiUrl'    => esc_url_raw( rest_url( 'open-graph-control/v1/' ) ),
			'nonce'     => wp_create_nonce( 'wp_rest' ),
			'version'   => OGC_VERSION,
			'platforms' => [
				'facebook',
				'twitter',
				'linkedin',
				'imessage',
				'threads',
				'mastodon',
				'bluesky',
				'whatsapp',
				'discord',
				'pinterest',
				'telegram',
				'slack',
			],
		];
	}
}
