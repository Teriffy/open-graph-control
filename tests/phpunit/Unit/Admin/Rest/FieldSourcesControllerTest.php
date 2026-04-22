<?php
/**
 * Unit tests for FieldSourcesController.
 *
 * @package EvzenLeonenko\OpenGraphControl
 */

declare(strict_types=1);

namespace EvzenLeonenko\OpenGraphControl\Tests\Unit\Admin\Rest;

use Brain\Monkey;
use Brain\Monkey\Functions;
use EvzenLeonenko\OpenGraphControl\Admin\Rest\FieldSourcesController;
use EvzenLeonenko\OpenGraphControl\Integrations\FieldDiscovery;
use PHPUnit\Framework\TestCase;
use WP_REST_Request;

/**
 * @covers \EvzenLeonenko\OpenGraphControl\Admin\Rest\FieldSourcesController
 */
final class FieldSourcesControllerTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Returns a controller backed by a real FieldDiscovery instance.
	 *
	 * @return FieldSourcesController
	 */
	private function make_controller(): FieldSourcesController {
		return new FieldSourcesController( new FieldDiscovery() );
	}

	// -------------------------------------------------------------------------
	// 1. register_routes registers exactly 2 route registrations
	// -------------------------------------------------------------------------

	public function test_register_routes_registers_two_routes(): void {
		Functions\expect( 'register_rest_route' )->times( 2 );

		$this->make_controller()->register_routes();

		self::assertTrue( true );
	}

	// -------------------------------------------------------------------------
	// 2. GET /field-sources/mapping — returns empty array when option is absent
	// -------------------------------------------------------------------------

	public function test_get_mapping_returns_empty_when_option_absent(): void {
		Functions\when( 'get_option' )->justReturn( [] );

		$req      = new WP_REST_Request();
		$response = $this->make_controller()->get_mapping( $req );

		self::assertSame( 200, $response->get_status() );
		self::assertSame( [], $response->get_data() );
	}

	// -------------------------------------------------------------------------
	// 3. PUT /field-sources/mapping — rejects a null (empty) body
	// -------------------------------------------------------------------------

	public function test_put_mapping_rejects_null_body(): void {
		// set_json_params accepts ?array; passing null simulates missing/invalid body.
		$req = new WP_REST_Request();
		$req->set_json_params( null );

		$response = $this->make_controller()->put_mapping( $req );

		self::assertSame( 400, $response->get_status() );
		self::assertArrayHasKey( 'error', $response->get_data() );
	}

	// -------------------------------------------------------------------------
	// 4. PUT /field-sources/mapping — rejects unexpected top-level key
	// -------------------------------------------------------------------------

	public function test_put_mapping_rejects_unknown_plugin_key(): void {
		$req = new WP_REST_Request();
		$req->set_json_params( [ 'unknown_plugin' => [] ] );

		$response = $this->make_controller()->put_mapping( $req );

		self::assertSame( 400, $response->get_status() );
		self::assertStringContainsString( 'Unexpected keys', (string) $response->get_data()['error'] );
	}

	// -------------------------------------------------------------------------
	// 5. PUT /field-sources/mapping — rejects invalid per-post-type shape
	// -------------------------------------------------------------------------

	public function test_put_mapping_rejects_bad_field_type(): void {
		$req = new WP_REST_Request();
		$req->set_json_params(
			[
				'acf' => [
					'post' => [
						'title' => 42, // must be string|null
					],
				],
			]
		);

		$response = $this->make_controller()->put_mapping( $req );

		self::assertSame( 400, $response->get_status() );
		self::assertArrayHasKey( 'error', $response->get_data() );
	}

	// -------------------------------------------------------------------------
	// 6. PUT /field-sources/mapping — saves valid input and returns 200
	// -------------------------------------------------------------------------

	public function test_put_mapping_saves_valid_mapping(): void {
		$mapping = [
			'acf' => [
				'post' => [
					'title'       => 'acf_post_title',
					'description' => null,
				],
			],
			'jet' => [],
		];

		Functions\expect( 'update_option' )
			->once()
			->with( 'ogc_field_sources', $mapping );

		$req = new WP_REST_Request();
		$req->set_json_params( $mapping );

		$response = $this->make_controller()->put_mapping( $req );

		self::assertSame( 200, $response->get_status() );
		self::assertSame( $mapping, $response->get_data() );
	}

	// -------------------------------------------------------------------------
	// 7. GET /field-sources/acf/fields — returns empty when ACF is not active
	// -------------------------------------------------------------------------

	public function test_get_fields_returns_empty_when_acf_not_active(): void {
		// acf_get_field_groups is intentionally NOT stubbed here; FieldDiscovery
		// will short-circuit and return [] when the function does not exist.
		// We only assert that the controller wraps that cleanly as 200 + [].
		$req = new WP_REST_Request();
		$req->set_param( 'plugin', 'acf' );
		$req->set_param( 'post_type', 'post' );

		$response = $this->make_controller()->get_fields( $req );

		self::assertSame( 200, $response->get_status() );
		self::assertSame( [], $response->get_data() );
	}

	// -------------------------------------------------------------------------
	// 8. GET /field-sources/unknown/fields — returns 400 for unknown plugin
	// -------------------------------------------------------------------------

	public function test_get_fields_returns_400_for_unknown_plugin(): void {
		$req = new WP_REST_Request();
		$req->set_param( 'plugin', 'unknown' );
		$req->set_param( 'post_type', 'post' );

		$response = $this->make_controller()->get_fields( $req );

		self::assertSame( 400, $response->get_status() );
		self::assertArrayHasKey( 'error', $response->get_data() );
	}
}
