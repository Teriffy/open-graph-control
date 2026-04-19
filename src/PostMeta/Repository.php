<?php
/**
 * Per-post override repository.
 *
 * @package EvzenLeonenko\OpenGraphControl
 */

declare(strict_types=1);

namespace EvzenLeonenko\OpenGraphControl\PostMeta;

/**
 * Reads and writes the _ogc_meta post meta blob.
 *
 * All per-post overrides live in a single serialized blob rather than many
 * meta rows. This trades meta_query flexibility for fewer DB writes during
 * a typical post save. If a future feature needs to query posts by a specific
 * override field, we can migrate that field to its own meta key.
 */
class Repository {

	public const META_KEY = '_ogc_meta';

	/** @var array<int, string> */
	private const ALLOWED_KEYS = [ 'title', 'description', 'image_id', 'type', 'platforms', 'exclude' ];

	/**
	 * @return array{
	 *   title: string,
	 *   description: string,
	 *   image_id: int,
	 *   type: string,
	 *   platforms: array<string, array<string, mixed>>,
	 *   exclude: array<int, string>
	 * }
	 */
	public function get( int $post_id ): array {
		$raw    = get_post_meta( $post_id, self::META_KEY, true );
		$stored = is_array( $raw ) ? $raw : [];

		return [
			'title'       => (string) ( $stored['title'] ?? '' ),
			'description' => (string) ( $stored['description'] ?? '' ),
			'image_id'    => (int) ( $stored['image_id'] ?? 0 ),
			'type'        => (string) ( $stored['type'] ?? '' ),
			'platforms'   => is_array( $stored['platforms'] ?? null ) ? $stored['platforms'] : [],
			'exclude'     => is_array( $stored['exclude'] ?? null ) ? array_values( $stored['exclude'] ) : [],
		];
	}

	/**
	 * @param array<string, mixed> $data
	 */
	public function save( int $post_id, array $data ): bool {
		$filtered = array_intersect_key( $data, array_flip( self::ALLOWED_KEYS ) );
		return (bool) update_post_meta( $post_id, self::META_KEY, $filtered );
	}

	public function delete( int $post_id ): bool {
		return (bool) delete_post_meta( $post_id, self::META_KEY );
	}
}
