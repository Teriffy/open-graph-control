<?php
/**
 * ACF + JetEngine field discovery with transient caching.
 *
 * @package EvzenLeonenko\OpenGraphControl
 */

declare(strict_types=1);


namespace EvzenLeonenko\OpenGraphControl\Integrations;

defined( 'ABSPATH' ) || exit;

/**
 * Discovers text/textarea fields from ACF and JetEngine with transient caching.
 *
 * Results are keyed by post type and cached for 5 minutes to avoid repeated
 * reflection over field groups on every request.
 */
final class FieldDiscovery {

	public const CACHE_KEY  = 'ogc_field_discovery';
	private const CACHE_TTL = 5 * 60;
	// 5 minutes

	/**
	 * ACF text/textarea fields for a given post type (or all).
	 *
	 * @param string|null $post_type Post type slug, or null for a flat list of all.
	 * @return array<int, string> Field name keys suitable for get_field().
	 */
	public function acf_fields( ?string $post_type = null ): array {
		if ( ! function_exists( 'acf_get_field_groups' ) ) {
			return [];
		}
		$cached = get_transient( self::CACHE_KEY );
		if ( is_array( $cached ) && isset( $cached['acf'] ) ) {
			$all = (array) $cached['acf'];
		} else {
			$all = $this->collect_acf();
			$this->store_cache( 'acf', $all );
		}
		if ( null === $post_type ) {
			// Return a flat list of all fields across post types.
			$out = [];
			foreach ( $all as $fields ) {
				foreach ( (array) $fields as $name ) {
					$out[ $name ] = true;
				}
			}
			return array_keys( $out );
		}
		return isset( $all[ $post_type ] ) ? (array) $all[ $post_type ] : [];
	}

	/**
	 * JetEngine meta fields (text-like) for a given post type (or all).
	 *
	 * @param string|null $post_type Post type slug, or null for a flat list of all.
	 * @return array<int, string>
	 */
	public function jetengine_fields( ?string $post_type = null ): array {
		if ( ! function_exists( 'jet_engine' ) && ! class_exists( 'Jet_Engine' ) ) {
			return [];
		}
		$cached = get_transient( self::CACHE_KEY );
		if ( is_array( $cached ) && isset( $cached['jet'] ) ) {
			$all = (array) $cached['jet'];
		} else {
			$all = $this->collect_jetengine();
			$this->store_cache( 'jet', $all );
		}
		if ( null === $post_type ) {
			$out = [];
			foreach ( $all as $fields ) {
				foreach ( (array) $fields as $name ) {
					$out[ $name ] = true;
				}
			}
			return array_keys( $out );
		}
		return isset( $all[ $post_type ] ) ? (array) $all[ $post_type ] : [];
	}

	/**
	 * Clear the shared discovery transient.
	 *
	 * @return void
	 */
	public function flush(): void {
		delete_transient( self::CACHE_KEY );
	}

	/**
	 * Collect all ACF text/textarea fields grouped by post type.
	 *
	 * @return array<string, array<int, string>>
	 */
	private function collect_acf(): array {
		if ( ! function_exists( 'acf_get_field_groups' ) || ! function_exists( 'acf_get_fields' ) ) {
			return [];
		}
		$groups = acf_get_field_groups();
		$out    = [];
		foreach ( (array) $groups as $group ) {
			if ( ! is_array( $group ) || ! isset( $group['key'] ) ) {
				continue;
			}
			$post_types = $this->extract_acf_post_types( $group );
			$fields     = (array) acf_get_fields( $group['key'] );
			foreach ( $fields as $field ) {
				if ( ! is_array( $field ) ) {
					continue;
				}
				$type = isset( $field['type'] ) ? (string) $field['type'] : '';
				$name = isset( $field['name'] ) ? (string) $field['name'] : '';
				if ( '' === $name ) {
					continue;
				}
				if ( ! in_array( $type, [ 'text', 'textarea' ], true ) ) {
					continue;
				}
				foreach ( $post_types as $pt ) {
					$out[ $pt ][] = $name;
				}
			}
		}//end foreach
		return $out;
	}

	/**
	 * Extract post type slugs from ACF location rules.
	 *
	 * @param array<string, mixed> $group ACF field group array.
	 * @return array<int, string>
	 */
	private function extract_acf_post_types( array $group ): array {
		$types    = [];
		$location = isset( $group['location'] ) && is_array( $group['location'] ) ? $group['location'] : [];
		foreach ( $location as $rule_group ) {
			foreach ( (array) $rule_group as $rule ) {
				if ( is_array( $rule )
					&& ( $rule['param'] ?? '' ) === 'post_type'
					&& ( $rule['operator'] ?? '' ) === '=='
					&& isset( $rule['value'] )
				) {
					$types[] = (string) $rule['value'];
				}
			}
		}
		return array_unique( $types );
	}

	/**
	 * Collect all JetEngine text/textarea meta fields grouped by post type.
	 *
	 * @return array<string, array<int, string>>
	 */
	private function collect_jetengine(): array {
		if ( ! function_exists( 'jet_engine' ) ) {
			return [];
		}
		$jet = jet_engine();
		if ( ! is_object( $jet ) || ! isset( $jet->meta_boxes ) || ! is_object( $jet->meta_boxes ) ) {
			return [];
		}
		if ( ! method_exists( $jet->meta_boxes, 'get_meta_boxes' ) ) {
			return [];
		}
		$boxes = (array) $jet->meta_boxes->get_meta_boxes();
		$out   = [];
		foreach ( $boxes as $box ) {
			if ( ! is_array( $box ) ) {
				continue;
			}
			$post_types = isset( $box['args']['allowed_post_type'] ) ? (array) $box['args']['allowed_post_type'] : [];
			$fields     = isset( $box['meta_fields'] ) ? (array) $box['meta_fields'] : [];
			foreach ( $fields as $field ) {
				if ( ! is_array( $field ) ) {
					continue;
				}
				$type = isset( $field['type'] ) ? (string) $field['type'] : '';
				$name = isset( $field['name'] ) ? (string) $field['name'] : '';
				if ( '' === $name || ! in_array( $type, [ 'text', 'textarea' ], true ) ) {
					continue;
				}
				foreach ( $post_types as $pt ) {
					$out[ (string) $pt ][] = $name;
				}
			}
		}
		return $out;
	}

	/**
	 * Merge a plugin's field map into the shared transient.
	 *
	 * @param string                            $plugin Cache slot key ('acf' or 'jet').
	 * @param array<string, array<int, string>> $value  Fields grouped by post type.
	 * @return void
	 */
	private function store_cache( string $plugin, array $value ): void {
		$cached            = (array) get_transient( self::CACHE_KEY );
		$cached[ $plugin ] = $value;
		set_transient( self::CACHE_KEY, $cached, self::CACHE_TTL );
	}
}
