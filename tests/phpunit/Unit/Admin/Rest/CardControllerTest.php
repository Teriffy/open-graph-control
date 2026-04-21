<?php
declare(strict_types=1);

namespace EvzenLeonenko\OpenGraphControl\Tests\Unit\Admin\Rest;

use Brain\Monkey;
use Brain\Monkey\Functions;
use EvzenLeonenko\OpenGraphControl\Admin\Rest\CardController;
use EvzenLeonenko\OpenGraphControl\OgCard\BackfillCron;
use EvzenLeonenko\OpenGraphControl\OgCard\CardGenerator;
use EvzenLeonenko\OpenGraphControl\OgCard\CardKey;
use EvzenLeonenko\OpenGraphControl\OgCard\CardStore;
use EvzenLeonenko\OpenGraphControl\OgCard\FontProvider;
use EvzenLeonenko\OpenGraphControl\OgCard\Payload;
use EvzenLeonenko\OpenGraphControl\OgCard\RendererInterface;
use EvzenLeonenko\OpenGraphControl\OgCard\RendererPicker;
use EvzenLeonenko\OpenGraphControl\OgCard\Template;
use PHPUnit\Framework\TestCase;
use WP_REST_Request;

/**
 * @covers \EvzenLeonenko\OpenGraphControl\Admin\Rest\CardController
 */
final class CardControllerTest extends TestCase {

	/** @var string */
	private string $base;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		$this->base = sys_get_temp_dir() . '/' . uniqid( 'ogc_cc_test_', true );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
		if ( is_dir( $this->base ) ) {
			$this->remove_dir_recursively( $this->base );
		}
	}

	/**
	 * Recursively removes a directory.
	 *
	 * @param string $dir
	 * @return void
	 */
	private function remove_dir_recursively( string $dir ): void {
		if ( ! is_dir( $dir ) ) {
			return;
		}
		$files = scandir( $dir );
		if ( false === $files ) {
			return;
		}
		foreach ( $files as $file ) {
			if ( '.' !== $file && '..' !== $file ) {
				$path = $dir . '/' . $file;
				is_dir( $path ) ? $this->remove_dir_recursively( $path ) : unlink( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- test cleanup
			}
		}
		rmdir( $dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- test cleanup
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Returns a real CardStore backed by a temp dir.
	 */
	private function make_store(): CardStore {
		return new CardStore( $this->base, 'https://example.com/wp-content/uploads' );
	}

	/**
	 * Returns a real CardGenerator using a stub RendererInterface.
	 */
	private function make_generator( CardStore $store, ?RendererInterface $renderer = null ): CardGenerator {
		$r = $renderer ?? $this->make_stub_renderer();
		return new CardGenerator(
			picker:            static fn() => $r,
			store:             $store,
			template_provider: static fn() => Template::default(),
			// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- $key intentionally ignored; payload is static in tests.
			payload_provider:  static fn( CardKey $key ) => new Payload( 'Title', 'Desc', 'Site', 'https://example.com', 'today' ),
		);
	}

	/**
	 * Returns a stub RendererInterface that returns minimal PNG magic bytes.
	 */
	private function make_stub_renderer(): RendererInterface {
		$renderer = $this->createMock( RendererInterface::class );
		$renderer->method( 'render' )->willReturn( 'fake_bytes' );
		return $renderer;
	}

	/**
	 * Returns a real RendererPicker (always picks GdRenderer).
	 */
	private function make_picker(): RendererPicker {
		return new RendererPicker( new FontProvider() );
	}

	/**
	 * Builds a controller from real OgCard services backed by the temp dir.
	 */
	private function make_controller(
		?CardStore $store = null,
		?CardGenerator $generator = null,
		?RendererPicker $picker = null,
	): CardController {
		$store     = $store ?? $this->make_store();
		$generator = $generator ?? $this->make_generator( $store );
		$picker    = $picker ?? $this->make_picker();
		return new CardController( $store, $generator, $picker );
	}

	// -------------------------------------------------------------------------
	// 1. register_routes adds exactly 5 route registrations
	// -------------------------------------------------------------------------

	public function test_register_routes_registers_all_routes(): void {
		// 4 register_rest_route calls: template (GET+PUT combined), preview, regenerate, status.
		// That covers all 5 HTTP method+path combinations.
		Functions\expect( 'register_rest_route' )->times( 4 );

		$this->make_controller()->register_routes();

		// Expectation is verified on tearDown; add a trivial assertion so PHPUnit
		// does not flag this as a risky test.
		self::assertTrue( true );
	}

	// -------------------------------------------------------------------------
	// 2. GET /og-card/template — defaults when option is empty
	// -------------------------------------------------------------------------

	public function test_get_template_returns_defaults_plus_enabled_false(): void {
		Functions\when( 'get_option' )->justReturn( [] );

		$req      = new WP_REST_Request();
		$response = $this->make_controller()->get_template( $req );

		self::assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		self::assertFalse( $data['enabled'] );
		self::assertSame( 'gradient', $data['bg_type'] );
		self::assertSame( '#1e40af', $data['bg_color'] );
	}

	// -------------------------------------------------------------------------
	// 3. PUT /og-card/template — rejects invalid hex
	// -------------------------------------------------------------------------

	public function test_put_template_rejects_invalid_hex(): void {
		$req = new WP_REST_Request();
		$req->set_json_params( [ 'bg_color' => 'not-a-hex' ] );

		$response = $this->make_controller()->put_template( $req );

		self::assertSame( 400, $response->get_status() );
		self::assertArrayHasKey( 'error', $response->get_data() );
	}

	// -------------------------------------------------------------------------
	// 4. PUT /og-card/template — persists valid config
	// -------------------------------------------------------------------------

	public function test_put_template_saves_valid_config(): void {
		Functions\expect( 'update_option' )
			->once()
			->with( 'ogc_card_template', \Mockery::type( 'array' ) );

		$req = new WP_REST_Request();
		$req->set_json_params(
			[
				'bg_type'        => 'solid',
				'bg_color'       => '#ff0000',
				'bg_gradient_to' => '#00ff00',
				'text_color'     => '#ffffff',
				'enabled'        => true,
			]
		);

		$response = $this->make_controller()->put_template( $req );

		self::assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		self::assertSame( 'solid', $data['bg_type'] );
		self::assertTrue( $data['enabled'] );
	}

	// -------------------------------------------------------------------------
	// 5. POST /og-card/preview — returns base64 PNG data URI (GD renders live)
	// -------------------------------------------------------------------------

	public function test_post_preview_returns_base64_png(): void {
		if ( ! extension_loaded( 'gd' ) ) {
			self::markTestSkipped( 'GD extension required' );
		}
		Functions\when( 'get_bloginfo' )->justReturn( 'Test Site' );
		Functions\when( 'get_site_url' )->justReturn( 'https://example.com' );
		Functions\when( 'wp_date' )->justReturn( 'April 2026' );
		Functions\when( 'wp_strip_all_tags' )->returnArg();
		Functions\when( 'apply_filters' )->justReturn( false );

		$req = new WP_REST_Request();
		$req->set_json_params(
			[
				'title'       => 'Hello preview',
				'description' => 'World',
				'template'    => [ 'bg_type' => 'solid' ],
			]
		);

		$response = $this->make_controller()->post_preview( $req );

		self::assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		self::assertStringStartsWith( 'data:image/png;base64,', $data['image'] );
		self::assertGreaterThan( 0, $data['bytes'] );
	}

	// -------------------------------------------------------------------------
	// 6. POST /og-card/preview — bad hex in template body → 400
	// -------------------------------------------------------------------------

	public function test_post_preview_rejects_invalid_template(): void {
		$req = new WP_REST_Request();
		$req->set_json_params( [ 'template' => [ 'bg_color' => 'ZZZZZZ' ] ] );

		$response = $this->make_controller()->post_preview( $req );

		self::assertSame( 400, $response->get_status() );
		self::assertArrayHasKey( 'error', $response->get_data() );
	}

	// -------------------------------------------------------------------------
	// 7. POST /og-card/regenerate scope=all — purges + queues cron → 202
	// -------------------------------------------------------------------------

	public function test_post_regenerate_all_purges_and_queues_cron(): void {
		Functions\expect( 'wp_schedule_single_event' )
			->once()
			->with( \Mockery::type( 'int' ), BackfillCron::HOOK );

		// Use a real store; purge_all is a real filesystem operation on the temp dir.
		$store = $this->make_store();

		$req = new WP_REST_Request();
		$req->set_param( 'scope', 'all' );

		$response = $this->make_controller( store: $store )->post_regenerate( $req );

		self::assertSame( 202, $response->get_status() );
		self::assertSame( 'queued', $response->get_data()['status'] );
	}

	// -------------------------------------------------------------------------
	// 8. POST /og-card/regenerate scope=post — deletes + regenerates → 200 + path
	// -------------------------------------------------------------------------

	public function test_post_regenerate_post_deletes_and_regenerates(): void {
		Functions\when( 'apply_filters' )->justReturn( true ); // short-circuit ogc_card_should_generate
		Functions\when( 'do_action' )->justReturn( null );
		Functions\when( 'wp_strip_all_tags' )->returnArg();

		$store     = $this->make_store();
		$renderer  = $this->make_stub_renderer();
		$generator = $this->make_generator( $store, $renderer );

		$req = new WP_REST_Request();
		$req->set_param( 'scope', 'post' );
		$req->set_param( 'id', 42 );

		$response = $this->make_controller( store: $store, generator: $generator )->post_regenerate( $req );

		self::assertSame( 200, $response->get_status() );
		// CardGenerator::ensure returns the path or null; either is acceptable here
		// since the card was written with stub bytes.
		$data = $response->get_data();
		self::assertArrayHasKey( 'path', $data );
	}

	// -------------------------------------------------------------------------
	// 9. POST /og-card/regenerate — invalid scope → 400
	// -------------------------------------------------------------------------

	public function test_post_regenerate_invalid_scope_returns_400(): void {
		$req = new WP_REST_Request();
		$req->set_param( 'scope', 'unknown' );

		$response = $this->make_controller()->post_regenerate( $req );

		self::assertSame( 400, $response->get_status() );
		self::assertSame( 'invalid scope', $response->get_data()['error'] );
	}

	// -------------------------------------------------------------------------
	// 10. GET /og-card/status — global counts (no key params)
	// -------------------------------------------------------------------------

	public function test_get_status_returns_global_counts(): void {
		Functions\when( 'get_option' )->justReturn( [] );
		Functions\when( 'wp_count_posts' )->justReturn( (object) [ 'publish' => 50 ] );
		Functions\when( 'get_posts' )->justReturn( [] ); // no posts → missing = 0

		$req = new WP_REST_Request();

		$response = $this->make_controller()->get_status( $req );

		self::assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		self::assertSame( 50, $data['total'] );
		self::assertSame( 0, $data['missing'] );
		self::assertSame( 50, $data['generated'] );
	}

	// -------------------------------------------------------------------------
	// 11. GET /og-card/status — per-key check for a post
	// -------------------------------------------------------------------------

	public function test_get_status_returns_per_key_status_for_post(): void {
		Functions\when( 'get_option' )->justReturn( [] );

		// Write a real card file so exists() returns true.
		$store    = $this->make_store();
		$template = Template::default();
		$key      = CardKey::for_post( 7 );
		$path     = $store->path( $key, $template, 'landscape' );
		$dir      = dirname( $path );
		if ( ! is_dir( $dir ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- WP_Filesystem unavailable in unit tests.
			mkdir( $dir, 0755, true );
		}
		file_put_contents( $path, 'fake_png' ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- test setup

		$req = new WP_REST_Request();
		$req->set_param( 'post_id', 7 );

		$response = $this->make_controller( store: $store )->get_status( $req );

		self::assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		self::assertTrue( $data['exists'] );
		self::assertStringContainsString( 'post-7', (string) $data['url'] );
	}
}
