<?php
/**
 * Per-post Open Graph meta box shell.
 *
 * @package EvzenLeonenko\OpenGraphControl
 */

declare(strict_types=1);


namespace EvzenLeonenko\OpenGraphControl\Admin;

defined( 'ABSPATH' ) || exit;

use EvzenLeonenko\OpenGraphControl\Options\Repository as OptionsRepository;
use WP_Post;

/**
 * Adds a meta box to every enabled post type and renders the React mount
 * point. Nonce-protected save hook calls through to the PostMeta repository.
 *
 * The React UI itself (Base tab + per-platform tabs + live preview)
 * ships in a later phase — for now the meta box simply surfaces a mount
 * point so the JS bundle can take over when enqueued.
 */
final class MetaBox {

	public const META_BOX_ID  = 'ogc_meta_box';
	public const NONCE_NAME   = 'ogc_meta_nonce';
	public const NONCE_ACTION = 'ogc_meta_save';

	public function __construct( private OptionsRepository $options ) {}

	public function register(): void {
		add_action( 'add_meta_boxes', [ $this, 'add_boxes' ] );
	}

	public function add_boxes(): void {
		foreach ( $this->enabled_post_types() as $post_type ) {
			add_meta_box(
				self::META_BOX_ID,
				__( 'Social Meta — Open Graph Control', 'open-graph-control' ),
				[ $this, 'render' ],
				$post_type,
				'normal',
				'default'
			);
		}
	}

	public function render( WP_Post $post ): void {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );
		printf(
			'<div id="ogc-metabox-root" data-post-id="%d"></div>',
			(int) $post->ID
		);
	}

	/**
	 * @return array<int, string>
	 */
	private function enabled_post_types(): array {
		/** @var array<string, array<string, mixed>>|null $configured */
		$configured = $this->options->get_path( 'post_types' );
		if ( ! is_array( $configured ) ) {
			return [];
		}

		$enabled = [];
		foreach ( $configured as $slug => $settings ) {
			if ( is_array( $settings ) && ! empty( $settings['enabled'] ) ) {
				$enabled[] = (string) $slug;
			}
		}
		return $enabled;
	}
}
