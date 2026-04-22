<?php
/**
 * REST controller for ACF / JetEngine field-source mapping.
 *
 * @package EvzenLeonenko\OpenGraphControl
 */

declare(strict_types=1);


namespace EvzenLeonenko\OpenGraphControl\Admin\Rest;

use EvzenLeonenko\OpenGraphControl\Integrations\FieldDiscovery;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * Exposes three REST endpoints for managing field-source mappings:
 *
 * - GET  /field-sources/mapping                  → current mapping (ogc_field_sources option)
 * - PUT  /field-sources/mapping                  → validates and persists the mapping
 * - GET  /field-sources/{plugin}/fields           → list of field names for a post type
 *
 * All endpoints require `manage_options` capability.
 */
final class FieldSourcesController {

	/**
	 * REST namespace shared by all plugin endpoints.
	 *
	 * @var string
	 */
	private const NS = 'open-graph-control/v1';

	/**
	 * Allowed plugin slugs.
	 *
	 * @var array<int, string>
	 */
	private const PLUGINS = [ 'acf', 'jet' ];

	/**
	 * Creates a new FieldSourcesController instance.
	 *
	 * @param FieldDiscovery $discovery Field discovery service for ACF and JetEngine.
	 */
	public function __construct(
		private readonly FieldDiscovery $discovery,
	) {}

	/**
	 * Hooks route registration onto `rest_api_init`.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/**
	 * Registers all three field-sources REST routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		$auth = static fn(): bool => current_user_can( 'manage_options' );

		register_rest_route(
			self::NS,
			'/field-sources/mapping',
			[
				[
					'methods'             => 'GET',
					'callback'            => [ $this, 'get_mapping' ],
					'permission_callback' => $auth,
				],
				[
					'methods'             => 'PUT',
					'callback'            => [ $this, 'put_mapping' ],
					'permission_callback' => $auth,
				],
			]
		);

		register_rest_route(
			self::NS,
			'/field-sources/(?P<plugin>[a-z]+)/fields',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_fields' ],
				'permission_callback' => $auth,
			]
		);
	}

	/**
	 * GET /field-sources/mapping — returns the current field-source mapping.
	 *
	 * @param WP_REST_Request $req Incoming REST request.
	 *
	 * @return WP_REST_Response 200 with the current mapping array.
	 */
	public function get_mapping( WP_REST_Request $req ): WP_REST_Response {
		unset( $req );
		$mapping = (array) get_option( 'ogc_field_sources', [] );
		return new WP_REST_Response( $mapping, 200 );
	}

	/**
	 * PUT /field-sources/mapping — validates and persists the field-source mapping.
	 *
	 * Expected shape:
	 * {
	 *   "acf": { "<post_type>": { "title": "<field>|null", "description": "<field>|null" } },
	 *   "jet": { ... }
	 * }
	 *
	 * @param WP_REST_Request $req Incoming REST request (JSON body).
	 *
	 * @return WP_REST_Response 200 with stored mapping, or 400 on validation failure.
	 */
	public function put_mapping( WP_REST_Request $req ): WP_REST_Response {
		$data = $req->get_json_params();

		if ( ! is_array( $data ) ) {
			return new WP_REST_Response( [ 'error' => 'Request body must be a JSON object.' ], 400 );
		}

		// Ensure no unexpected top-level keys.
		$extra = array_diff( array_keys( $data ), self::PLUGINS );
		if ( ! empty( $extra ) ) {
			return new WP_REST_Response(
				[ 'error' => 'Unexpected keys: ' . implode( ', ', $extra ) . '. Allowed: acf, jet.' ],
				400
			);
		}

		foreach ( self::PLUGINS as $plugin ) {
			if ( ! isset( $data[ $plugin ] ) ) {
				continue;
			}
			$plugin_map = $data[ $plugin ];
			if ( ! is_array( $plugin_map ) ) {
				return new WP_REST_Response(
					[ 'error' => "Value for plugin '{$plugin}' must be an object keyed by post type." ],
					400
				);
			}
			foreach ( $plugin_map as $post_type => $fields ) {
				if ( ! is_string( $post_type ) || '' === $post_type ) {
					return new WP_REST_Response(
						[ 'error' => "Post-type keys for plugin '{$plugin}' must be non-empty strings." ],
						400
					);
				}
				if ( ! is_array( $fields ) ) {
					return new WP_REST_Response(
						[ 'error' => "Mapping for '{$plugin}.{$post_type}' must be an object." ],
						400
					);
				}
				$extra_keys = array_diff( array_keys( $fields ), [ 'title', 'description' ] );
				if ( ! empty( $extra_keys ) ) {
					return new WP_REST_Response(
						[
							'error' => "Mapping for '{$plugin}.{$post_type}' contains unexpected keys: "
								. implode( ', ', $extra_keys ) . '.',
						],
						400
					);
				}
				foreach ( [ 'title', 'description' ] as $kind ) {
					if ( ! array_key_exists( $kind, $fields ) ) {
						continue;
					}
					$v = $fields[ $kind ];
					if ( null !== $v && ! is_string( $v ) ) {
						return new WP_REST_Response(
							[ 'error' => "'{$plugin}.{$post_type}.{$kind}' must be a string or null." ],
							400
						);
					}
				}//end foreach
			}//end foreach
		}//end foreach

		update_option( 'ogc_field_sources', $data );
		return new WP_REST_Response( $data, 200 );
	}

	/**
	 * GET /field-sources/{plugin}/fields — returns field names for a plugin + post type.
	 *
	 * Query params:
	 *   - `post_type` (string, optional): limit results to a specific post type.
	 *
	 * @param WP_REST_Request $req Incoming REST request.
	 *
	 * @return WP_REST_Response 200 with array of field name strings, or 400 on unknown plugin.
	 */
	public function get_fields( WP_REST_Request $req ): WP_REST_Response {
		$plugin    = (string) $req->get_param( 'plugin' );
		$post_type = $req->get_param( 'post_type' );
		$post_type = is_string( $post_type ) && '' !== $post_type ? $post_type : null;

		if ( ! in_array( $plugin, self::PLUGINS, true ) ) {
			return new WP_REST_Response(
				[ 'error' => "Unknown plugin '{$plugin}'. Allowed: acf, jet." ],
				400
			);
		}

		$fields = match ( $plugin ) {
			'acf' => $this->discovery->acf_fields( $post_type ),
			'jet' => $this->discovery->jetengine_fields( $post_type ),
		};

		return new WP_REST_Response( array_values( $fields ), 200 );
	}
}
