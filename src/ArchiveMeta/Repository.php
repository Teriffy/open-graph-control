<?php
/**
 * Per-archive override repository.
 *
 * @package EvzenLeonenko\OpenGraphControl
 */

declare(strict_types=1);

namespace EvzenLeonenko\OpenGraphControl\ArchiveMeta;

defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes the _ogc_meta blob on terms + users.
 *
 * Storage uses native term_meta / user_meta — each override row lives on the
 * object it belongs to. A 4-field allowlist (title, description, image_id,
 * exclude) is applied on every write; extra keys submitted via the REST API
 * or a misconfigured filter are dropped.
 */
class Repository {

	public const META_KEY = '_ogc_meta';

	/** @var array<int, string> */
	private const ALLOWED_KEYS = array( 'title', 'description', 'image_id', 'exclude' );

	/**
	 * @return array{title: string, description: string, image_id: int, exclude: array<int, string>}
	 */
	public function get_for_term( int $term_id ): array {
		$raw = get_term_meta( $term_id, self::META_KEY, true );
		return $this->normalize( is_array( $raw ) ? $raw : array() );
	}

	/**
	 * @return array{title: string, description: string, image_id: int, exclude: array<int, string>}
	 */
	public function get_for_user( int $user_id ): array {
		$raw = get_user_meta( $user_id, self::META_KEY, true );
		return $this->normalize( is_array( $raw ) ? $raw : array() );
	}

	/**
	 * @param array<string, mixed> $data
	 */
	public function save( string $kind, int $id, array $data ): bool {
		$filtered = array_intersect_key( $data, array_flip( self::ALLOWED_KEYS ) );
		if ( 'user' === $kind ) {
			return (bool) update_user_meta( $id, self::META_KEY, $filtered );
		}
		return (bool) update_term_meta( $id, self::META_KEY, $filtered );
	}

	/**
	 * @param array<string, mixed> $stored
	 * @return array{title: string, description: string, image_id: int, exclude: array<int, string>}
	 */
	private function normalize( array $stored ): array {
		return array(
			'title'       => (string) ( $stored['title'] ?? '' ),
			'description' => (string) ( $stored['description'] ?? '' ),
			'image_id'    => (int) ( $stored['image_id'] ?? 0 ),
			'exclude'     => is_array( $stored['exclude'] ?? null )
				? array_values( $stored['exclude'] )
				: array(),
		);
	}
}
