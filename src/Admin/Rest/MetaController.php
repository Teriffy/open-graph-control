<?php
/**
 * Meta / environment REST endpoints.
 *
 * @package EvzenLeonenko\OpenGraphControl
 */

declare(strict_types=1);

namespace EvzenLeonenko\OpenGraphControl\Admin\Rest;

use EvzenLeonenko\OpenGraphControl\PostMeta\Repository as PostMetaRepository;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Endpoints the admin UI needs that aren't covered by Settings/Preview/Conflicts:
 *
 * - GET  /post-types       → public post types with labels for the Post Types UI
 * - GET  /meta/{post_id}   → per-post _ogc_meta blob for the meta box
 * - POST /meta/{post_id}   → save _ogc_meta (allowlist-filtered)
 * - POST /settings/import  → replace ogc_settings with a JSON export (schema-version gated)
 */
final class MetaController extends AbstractController {

	public function __construct( private PostMetaRepository $postmeta ) {}

	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE_BASE,
			'/post-types',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'post_types' ],
				'permission_callback' => [ $this, 'require_manage_options' ],
			]
		);

		register_rest_route(
			self::NAMESPACE_BASE,
			'/meta/(?P<post_id>\d+)',
			[
				[
					'methods'             => 'GET',
					'callback'            => [ $this, 'get_meta' ],
					'permission_callback' => [ $this, 'can_edit_post_from_request' ],
					'args'                => [
						'post_id' => [
							'type'              => 'integer',
							'required'          => true,
							'sanitize_callback' => 'absint',
						],
					],
				],
				[
					'methods'             => 'POST',
					'callback'            => [ $this, 'save_meta' ],
					'permission_callback' => [ $this, 'can_edit_post_from_request' ],
					'args'                => [
						'post_id' => [
							'type'              => 'integer',
							'required'          => true,
							'sanitize_callback' => 'absint',
						],
					],
				],
			]
		);
	}

	public function post_types( WP_REST_Request $request ): WP_REST_Response {
		unset( $request );

		$types = get_post_types( [ 'public' => true ], 'objects' );
		$rows  = [];
		foreach ( $types as $type ) {
			$rows[] = [
				'slug'  => (string) $type->name,
				'label' => (string) $type->label,
			];
		}
		return new WP_REST_Response( [ 'post_types' => $rows ], 200 );
	}

	public function get_meta( WP_REST_Request $request ): WP_REST_Response {
		$post_id = (int) $request->get_param( 'post_id' );
		return new WP_REST_Response( $this->postmeta->get( $post_id ), 200 );
	}

	public function save_meta( WP_REST_Request $request ): WP_REST_Response {
		$post_id = (int) $request->get_param( 'post_id' );
		$raw     = $request->get_json_params();
		if ( ! is_array( $raw ) ) {
			return new WP_REST_Response(
				[
					'code'    => 'invalid_payload',
					'message' => 'Expected a JSON object.',
				],
				400
			);
		}
		$this->postmeta->save( $post_id, $this->sanitize( $raw ) );
		return new WP_REST_Response( $this->postmeta->get( $post_id ), 200 );
	}

	public function can_edit_post_from_request( WP_REST_Request $request ): bool {
		$post_id = (int) $request->get_param( 'post_id' );
		return $post_id > 0 && current_user_can( 'edit_post', $post_id );
	}

	/**
	 * @param array<mixed, mixed> $data
	 * @return array<string, mixed>
	 */
	private function sanitize( array $data ): array {
		$out = [];
		foreach ( $data as $key => $value ) {
			$string_key = (string) $key;
			if ( is_array( $value ) ) {
				$out[ $string_key ] = $this->sanitize( $value );
				continue;
			}
			if ( is_bool( $value ) || is_int( $value ) ) {
				$out[ $string_key ] = $value;
				continue;
			}
			if ( is_numeric( $value ) ) {
				$out[ $string_key ] = $value + 0;
				continue;
			}
			$out[ $string_key ] = sanitize_text_field( (string) $value );
		}
		return $out;
	}
}
