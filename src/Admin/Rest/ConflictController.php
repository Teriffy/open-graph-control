<?php
/**
 * SEO plugin conflict REST endpoint.
 *
 * @package EvzenLeonenko\OpenGraphControl
 */

declare(strict_types=1);


namespace EvzenLeonenko\OpenGraphControl\Admin\Rest;

defined( 'ABSPATH' ) || exit;

use EvzenLeonenko\OpenGraphControl\Integrations\Detector;
use WP_REST_Request;
use WP_REST_Response;

/**
 * GET /open-graph-control/v1/conflicts
 *
 * Returns the detected set of competing SEO plugins + current takeover
 * decisions so the admin UI can render an integrations dashboard.
 */
final class ConflictController extends AbstractController {

	public function __construct( private Detector $detector ) {}

	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE_BASE,
			'/conflicts',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get' ],
				'permission_callback' => [ $this, 'require_manage_options' ],
			]
		);
	}

	public function get( WP_REST_Request $request ): WP_REST_Response {
		unset( $request );

		$rows = array_map(
			static fn ( $integration ) => [
				'slug'   => $integration->slug(),
				'label'  => $integration->label(),
				'active' => $integration->is_active(),
			],
			$this->detector->all()
		);

		return new WP_REST_Response( [ 'integrations' => $rows ], 200 );
	}
}
