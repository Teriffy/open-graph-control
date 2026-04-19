<?php
/**
 * Archive override REST endpoints.
 *
 * @package EvzenLeonenko\OpenGraphControl
 */

declare(strict_types=1);


namespace EvzenLeonenko\OpenGraphControl\Admin\Rest;

defined( 'ABSPATH' ) || exit;

use EvzenLeonenko\OpenGraphControl\ArchiveMeta\Repository;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Endpoints powering the per-archive override UI:
 *
 * - GET  /archive-meta/term/{tax}/{id}  → read term override
 * - POST /archive-meta/term/{tax}/{id}  → save term override
 * - GET  /archive-meta/user/{id}        → read user override
 * - POST /archive-meta/user/{id}        → save user override
 * - GET  /archive-overrides             → audit list of all configured archives
 *
 * The writable endpoints accept a 4-key payload (title, description,
 * image_id, exclude) — anything else is dropped by Repository::save().
 * Permission checks mirror the native term/user capabilities so editors
 * can only edit archives they own.
 */
final class ArchiveMetaController extends AbstractController {

	/** @var array<int, string> */
	private const ALLOWED_KEYS = array( 'title', 'description', 'image_id', 'exclude' );

	/** @var array<int, string> */
	private const EXCLUDE_VALUES = array( 'all' );

	public function __construct( private Repository $archive ) {}

	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE_BASE,
			'/archive-meta/term/(?P<tax>[a-z0-9_-]+)/(?P<id>\d+)',
			[
				[
					'methods'             => 'GET',
					'callback'            => [ $this, 'get_term' ],
					'permission_callback' => [ $this, 'can_manage_term_from_request' ],
					'args'                => [
						'tax' => [
							'type'     => 'string',
							'required' => true,
						],
						'id'  => [
							'type'              => 'integer',
							'required'          => true,
							'sanitize_callback' => 'absint',
						],
					],
				],
				[
					'methods'             => 'POST',
					'callback'            => [ $this, 'save_term' ],
					'permission_callback' => [ $this, 'can_manage_term_from_request' ],
					'args'                => [
						'tax' => [
							'type'     => 'string',
							'required' => true,
						],
						'id'  => [
							'type'              => 'integer',
							'required'          => true,
							'sanitize_callback' => 'absint',
						],
					],
				],
			]
		);

		register_rest_route(
			self::NAMESPACE_BASE,
			'/archive-meta/user/(?P<id>\d+)',
			[
				[
					'methods'             => 'GET',
					'callback'            => [ $this, 'get_user' ],
					'permission_callback' => [ $this, 'can_edit_user_from_request' ],
					'args'                => [
						'id' => [
							'type'              => 'integer',
							'required'          => true,
							'sanitize_callback' => 'absint',
						],
					],
				],
				[
					'methods'             => 'POST',
					'callback'            => [ $this, 'save_user' ],
					'permission_callback' => [ $this, 'can_edit_user_from_request' ],
					'args'                => [
						'id' => [
							'type'              => 'integer',
							'required'          => true,
							'sanitize_callback' => 'absint',
						],
					],
				],
			]
		);

		register_rest_route(
			self::NAMESPACE_BASE,
			'/archive-overrides',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'list_overrides' ],
				'permission_callback' => [ $this, 'require_manage_options' ],
			]
		);
	}

	public function get_term( WP_REST_Request $request ): WP_REST_Response {
		$problem = $this->validate_term_request( $request );
		if ( null !== $problem ) {
			return $problem;
		}
		$id = (int) $request->get_param( 'id' );
		return new WP_REST_Response( $this->archive->get_for_term( $id ), 200 );
	}

	public function save_term( WP_REST_Request $request ): WP_REST_Response {
		$problem = $this->validate_term_request( $request );
		if ( null !== $problem ) {
			return $problem;
		}

		$raw = $request->get_json_params();
		if ( ! is_array( $raw ) ) {
			$raw = array();
		}

		$sanitized = $this->sanitize( $raw );
		$image_err = $this->validate_image_id( $sanitized );
		if ( null !== $image_err ) {
			return $image_err;
		}

		$id = (int) $request->get_param( 'id' );
		$this->archive->save( 'term', $id, $sanitized );

		return new WP_REST_Response( $this->archive->get_for_term( $id ), 200 );
	}

	public function get_user( WP_REST_Request $request ): WP_REST_Response {
		$problem = $this->validate_user_request( $request );
		if ( null !== $problem ) {
			return $problem;
		}
		$id = (int) $request->get_param( 'id' );
		return new WP_REST_Response( $this->archive->get_for_user( $id ), 200 );
	}

	public function save_user( WP_REST_Request $request ): WP_REST_Response {
		$problem = $this->validate_user_request( $request );
		if ( null !== $problem ) {
			return $problem;
		}

		$raw = $request->get_json_params();
		if ( ! is_array( $raw ) ) {
			$raw = array();
		}

		$sanitized = $this->sanitize( $raw );
		$image_err = $this->validate_image_id( $sanitized );
		if ( null !== $image_err ) {
			return $image_err;
		}

		$id = (int) $request->get_param( 'id' );
		$this->archive->save( 'user', $id, $sanitized );

		return new WP_REST_Response( $this->archive->get_for_user( $id ), 200 );
	}

	public function list_overrides( WP_REST_Request $request ): WP_REST_Response {
		unset( $request );

		/** @var array<string, string> $taxonomies */
		$taxonomies = get_taxonomies( [ 'public' => true ], 'names' );
		$tax_slugs  = [];
		foreach ( $taxonomies as $slug ) {
			$slug = (string) $slug;
			if ( 'attachment' === $slug ) {
				continue;
			}
			$tax_slugs[] = $slug;
		}

		$terms_out = [];
		if ( ! empty( $tax_slugs ) ) {
			/** @var mixed $terms */
			$terms = get_terms(
				[
					// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Audit endpoint intentionally filters by _ogc_meta; manage_options-gated, infrequent.
					'meta_key'   => Repository::META_KEY,
					'hide_empty' => false,
					'taxonomy'   => $tax_slugs,
					'fields'     => 'all',
				]
			);
			if ( is_array( $terms ) ) {
				foreach ( $terms as $term ) {
					if ( ! is_object( $term ) || ! isset( $term->term_id ) ) {
						continue;
					}
					/** @phpstan-var \WP_Term $term */
					$term_id    = (int) $term->term_id;
					$tax_slug   = (string) $term->taxonomy;
					$term_name  = (string) $term->name;
					$raw_meta   = get_term_meta( $term_id, Repository::META_KEY, true );
					$fields_set = $this->fields_set_from( is_array( $raw_meta ) ? $raw_meta : array() );
					if ( empty( $fields_set ) ) {
						continue;
					}
					$terms_out[] = [
						'tax'        => $tax_slug,
						'term_id'    => $term_id,
						'name'       => $term_name,
						'fields_set' => $fields_set,
					];
				}
			}//end if
		}//end if

		$users_out = [];
		/** @var mixed $users */
		$users = get_users(
			[
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Audit endpoint intentionally filters by _ogc_meta; manage_options-gated, infrequent.
				'meta_key' => Repository::META_KEY,
				'fields'   => 'all_with_meta',
			]
		);
		if ( is_array( $users ) ) {
			foreach ( $users as $user ) {
				if ( ! is_object( $user ) || ! isset( $user->ID ) ) {
					continue;
				}
				/** @phpstan-var \WP_User $user */
				$user_id    = (int) $user->ID;
				$name       = isset( $user->display_name ) ? (string) $user->display_name : '';
				$raw_meta   = get_user_meta( $user_id, Repository::META_KEY, true );
				$fields_set = $this->fields_set_from( is_array( $raw_meta ) ? $raw_meta : array() );
				if ( empty( $fields_set ) ) {
					continue;
				}
				$users_out[] = [
					'user_id'    => $user_id,
					'name'       => $name,
					'fields_set' => $fields_set,
				];
			}
		}

		return new WP_REST_Response(
			[
				'terms' => $terms_out,
				'users' => $users_out,
			],
			200
		);
	}

	public function can_manage_term_from_request( WP_REST_Request $request ): bool {
		$tax = (string) $request->get_param( 'tax' );
		$id  = (int) $request->get_param( 'id' );
		if ( '' === $tax || $id <= 0 ) {
			return false;
		}
		if ( ! taxonomy_exists( $tax ) ) {
			return false;
		}
		$tax_object = get_taxonomy( $tax );
		$cap        = is_object( $tax_object ) && isset( $tax_object->cap->manage_terms )
			? (string) $tax_object->cap->manage_terms
			: 'manage_categories';
		return current_user_can( $cap, $id );
	}

	public function can_edit_user_from_request( WP_REST_Request $request ): bool {
		$id = (int) $request->get_param( 'id' );
		if ( $id <= 0 ) {
			return false;
		}
		return current_user_can( 'edit_user', $id );
	}

	private function validate_term_request( WP_REST_Request $request ): ?WP_REST_Response {
		$tax = (string) $request->get_param( 'tax' );
		$id  = (int) $request->get_param( 'id' );
		if ( '' === $tax || ! taxonomy_exists( $tax ) ) {
			return new WP_REST_Response(
				[
					'code'    => 'invalid_taxonomy',
					'message' => 'Unknown taxonomy.',
				],
				400
			);
		}
		$term = get_term( $id, $tax );
		if ( ! is_object( $term ) || ! isset( $term->term_id ) ) {
			return new WP_REST_Response(
				[
					'code'    => 'invalid_term',
					'message' => 'Unknown term.',
				],
				400
			);
		}
		return null;
	}

	private function validate_user_request( WP_REST_Request $request ): ?WP_REST_Response {
		$id = (int) $request->get_param( 'id' );
		if ( $id <= 0 || false === get_userdata( $id ) ) {
			return new WP_REST_Response(
				[
					'code'    => 'invalid_user',
					'message' => 'Unknown user.',
				],
				400
			);
		}
		return null;
	}

	/**
	 * @param array<string, mixed> $data
	 */
	private function validate_image_id( array $data ): ?WP_REST_Response {
		$image_id = isset( $data['image_id'] ) ? (int) $data['image_id'] : 0;
		if ( $image_id > 0 && 'attachment' !== get_post_type( $image_id ) ) {
			return new WP_REST_Response(
				[
					'code'    => 'invalid_image',
					'message' => 'image_id must reference an attachment.',
				],
				400
			);
		}
		return null;
	}

	/**
	 * Narrow the payload to the 4-key allowlist and coerce values. Unknown
	 * keys are dropped here (and again in the repository) so the stored
	 * blob stays predictable.
	 *
	 * @param array<mixed, mixed> $data
	 * @return array<string, mixed>
	 */
	private function sanitize( array $data ): array {
		$out = array();
		foreach ( self::ALLOWED_KEYS as $key ) {
			if ( ! array_key_exists( $key, $data ) ) {
				continue;
			}
			$value = $data[ $key ];
			switch ( $key ) {
				case 'title':
				case 'description':
					$out[ $key ] = sanitize_text_field( (string) $value );
					break;
				case 'image_id':
					$out[ $key ] = absint( $value );
					break;
				case 'exclude':
					$list = is_array( $value ) ? $value : array();
					$strs = array();
					foreach ( $list as $entry ) {
						if ( is_string( $entry ) ) {
							$strs[] = $entry;
						}
					}
					$out[ $key ] = array_values( array_intersect( $strs, self::EXCLUDE_VALUES ) );
					break;
			}
		}//end foreach
		return $out;
	}

	/**
	 * @param array<mixed, mixed> $stored
	 * @return array<int, string>
	 */
	private function fields_set_from( array $stored ): array {
		$fields = array();
		foreach ( self::ALLOWED_KEYS as $key ) {
			if ( ! array_key_exists( $key, $stored ) ) {
				continue;
			}
			$value = $stored[ $key ];
			if ( 'image_id' === $key ) {
				if ( (int) $value > 0 ) {
					$fields[] = $key;
				}
				continue;
			}
			if ( 'exclude' === $key ) {
				if ( is_array( $value ) && count( $value ) > 0 ) {
					$fields[] = $key;
				}
				continue;
			}
			if ( is_string( $value ) && '' !== $value ) {
				$fields[] = $key;
			}
		}//end foreach
		return $fields;
	}
}
