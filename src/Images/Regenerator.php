<?php
/**
 * Regenerates OG image size variants for existing attachments.
 *
 * @package EvzenLeonenko\OpenGraphControl
 */

declare(strict_types=1);

namespace EvzenLeonenko\OpenGraphControl\Images;

/**
 * Walks the media library in batches and calls wp_generate_attachment_metadata
 * on each image, which populates the OG size variants registered by
 * SizeRegistry. Driven by a WP-Cron event so it survives across requests.
 */
final class Regenerator {

	public const CRON_HOOK    = 'ogc_regenerate_images_batch';
	public const OPTION_STATE = 'ogc_regen_state';
	private const BATCH_SIZE  = 10;

	public function register(): void {
		add_action( self::CRON_HOOK, [ $this, 'run_batch' ] );
	}

	public function start(): void {
		update_option(
			self::OPTION_STATE,
			[
				'status'    => 'running',
				'processed' => 0,
				'offset'    => 0,
				'started'   => time(),
			]
		);
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_single_event( time() + 1, self::CRON_HOOK );
		}
	}

	public function run_batch(): void {
		$state = get_option( self::OPTION_STATE, [] );
		if ( ! is_array( $state ) || 'running' !== ( $state['status'] ?? '' ) ) {
			return;
		}
		$offset = (int) ( $state['offset'] ?? 0 );

		$attachments = get_posts(
			[
				'post_type'      => 'attachment',
				'post_mime_type' => 'image',
				'post_status'    => 'inherit',
				'posts_per_page' => self::BATCH_SIZE,
				'offset'         => $offset,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			]
		);

		if ( empty( $attachments ) ) {
			update_option(
				self::OPTION_STATE,
				array_merge( $state, [ 'status' => 'done' ] )
			);
			return;
		}

		if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		foreach ( $attachments as $attachment_id ) {
			$file = get_attached_file( (int) $attachment_id );
			if ( ! is_string( $file ) || '' === $file || ! file_exists( $file ) ) {
				continue;
			}
			$meta = wp_generate_attachment_metadata( (int) $attachment_id, $file );
			if ( is_array( $meta ) ) {
				wp_update_attachment_metadata( (int) $attachment_id, $meta );
			}
		}

		$processed = (int) ( $state['processed'] ?? 0 ) + count( $attachments );
		update_option(
			self::OPTION_STATE,
			array_merge(
				$state,
				[
					'offset'    => $offset + self::BATCH_SIZE,
					'processed' => $processed,
				]
			)
		);

		// Schedule next batch.
		wp_schedule_single_event( time() + 1, self::CRON_HOOK );
	}

	/**
	 * @return array{status: string, processed: int}
	 */
	public function status(): array {
		$state = get_option( self::OPTION_STATE, [] );
		if ( ! is_array( $state ) ) {
			$state = [];
		}
		return [
			'status'    => (string) ( $state['status'] ?? 'idle' ),
			'processed' => (int) ( $state['processed'] ?? 0 ),
		];
	}
}
