<?php
/**
 * Settings REST endpoints.
 *
 * @package EvzenLeonenko\OpenGraphControl
 */

declare(strict_types=1);

namespace EvzenLeonenko\OpenGraphControl\Admin\Rest;

use EvzenLeonenko\OpenGraphControl\Options\Repository as OptionsRepository;
use WP_REST_Request;
use WP_REST_Response;

/**
 * GET /open-graph-control/v1/settings   → full merged ogc_settings
 * POST /open-graph-control/v1/settings  → patch (deep-merged, sanitized)
 *
 * Both require manage_options. POST accepts a partial patch — consumers
 * send only the keys they want to change and the repository's deep merge
 * preserves the rest.
 */
final class SettingsController extends AbstractController {

	public function __construct( private OptionsRepository $options ) {}

	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE_BASE,
			'/settings',
			[
				[
					'methods'             => 'GET',
					'callback'            => [ $this, 'get' ],
					'permission_callback' => [ $this, 'require_manage_options' ],
				],
				[
					'methods'             => 'POST',
					'callback'            => [ $this, 'post' ],
					'permission_callback' => [ $this, 'require_manage_options' ],
				],
			]
		);
		register_rest_route(
			self::NAMESPACE_BASE,
			'/settings/reset',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'reset' ],
				'permission_callback' => [ $this, 'require_manage_options' ],
			]
		);
	}

	public function reset( WP_REST_Request $request ): WP_REST_Response {
		unset( $request );
		// Delete the option entirely; next get() seeds from DefaultSettings.
		delete_option( \EvzenLeonenko\OpenGraphControl\Options\Repository::OPTION_KEY );
		$this->options->flush_cache();
		return new WP_REST_Response( $this->options->get(), 200 );
	}

	public function get( WP_REST_Request $request ): WP_REST_Response {
		unset( $request );
		return new WP_REST_Response( $this->options->get(), 200 );
	}

	public function post( WP_REST_Request $request ): WP_REST_Response {
		$raw = $request->get_json_params();
		if ( ! is_array( $raw ) ) {
			return new WP_REST_Response(
				[
					'code'    => 'invalid_payload',
					'message' => 'Expected a JSON object.',
				],
				400
			);
		}

		if ( isset( $raw['version'] ) && is_numeric( $raw['version'] )
			&& (int) $raw['version'] > \EvzenLeonenko\OpenGraphControl\Options\DefaultSettings::SCHEMA_VERSION ) {
			return new WP_REST_Response(
				[
					'code'    => 'schema_too_new',
					'message' => sprintf(
						'Payload targets schema version %d, but this plugin only understands up to %d. Upgrade the plugin first.',
						(int) $raw['version'],
						\EvzenLeonenko\OpenGraphControl\Options\DefaultSettings::SCHEMA_VERSION
					),
				],
				400
			);
		}

		/** @var array<string, mixed> $patch */
		$patch = $this->sanitize( $raw );
		$this->options->update( $patch );

		return new WP_REST_Response( $this->options->get(), 200 );
	}

	/**
	 * Recursive sanitizer. Keeps shape permissive (admin UI owns schema
	 * correctness) but coerces scalars.
	 *
	 * @param array<mixed, mixed> $data
	 * @return array<string, mixed>
	 */
	private function sanitize( array $data ): array {
		$result = [];
		foreach ( $data as $key => $value ) {
			$string_key = (string) $key;
			if ( is_array( $value ) ) {
				$result[ $string_key ] = $this->sanitize( $value );
				continue;
			}
			if ( is_bool( $value ) ) {
				$result[ $string_key ] = $value;
				continue;
			}
			if ( is_int( $value ) ) {
				$result[ $string_key ] = $value;
				continue;
			}
			if ( is_numeric( $value ) ) {
				$result[ $string_key ] = 0 + $value;
				continue;
			}
			$result[ $string_key ] = sanitize_text_field( (string) $value );
		}
		return $result;
	}
}
