<?php
/**
 * Image regeneration REST endpoint.
 *
 * @package EvzenLeonenko\OpenGraphControl
 */

declare(strict_types=1);


namespace EvzenLeonenko\OpenGraphControl\Admin\Rest;

defined( 'ABSPATH' ) || exit;

use EvzenLeonenko\OpenGraphControl\Images\Regenerator;
use WP_REST_Request;
use WP_REST_Response;

/**
 * POST /images/regenerate → schedule regeneration
 * GET  /images/regenerate → current status
 */
final class RegenerateController extends AbstractController {

	public function __construct( private Regenerator $regenerator ) {}

	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE_BASE,
			'/images/regenerate',
			[
				[
					'methods'             => 'POST',
					'callback'            => [ $this, 'start' ],
					'permission_callback' => [ $this, 'require_manage_options' ],
				],
				[
					'methods'             => 'GET',
					'callback'            => [ $this, 'status' ],
					'permission_callback' => [ $this, 'require_manage_options' ],
				],
			]
		);
	}

	public function start( WP_REST_Request $request ): WP_REST_Response {
		unset( $request );
		$this->regenerator->start();
		return new WP_REST_Response( $this->regenerator->status(), 202 );
	}

	public function status( WP_REST_Request $request ): WP_REST_Response {
		unset( $request );
		return new WP_REST_Response( $this->regenerator->status(), 200 );
	}
}
