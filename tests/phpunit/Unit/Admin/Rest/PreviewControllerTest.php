<?php
declare(strict_types=1);

namespace EvzenLeonenko\OpenGraphControl\Tests\Unit\Admin\Rest;

use Brain\Monkey;
use EvzenLeonenko\OpenGraphControl\Admin\Rest\PreviewController;
use EvzenLeonenko\OpenGraphControl\Admin\Rest\RateLimiter;
use EvzenLeonenko\OpenGraphControl\Options\Repository as OptionsRepository;
use EvzenLeonenko\OpenGraphControl\Platforms\Registry as PlatformRegistry;
use EvzenLeonenko\OpenGraphControl\Renderer\Tag;
use EvzenLeonenko\OpenGraphControl\Validation\Validator;
use PHPUnit\Framework\TestCase;
use WP_REST_Request;

final class PreviewControllerTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	private function passThroughLimiter(): RateLimiter {
		$stub = $this->createStub( RateLimiter::class );
		$stub->method( 'check' )->willReturn( true );
		return $stub;
	}

	public function test_rate_limited_returns_429(): void {
		$limiter = $this->createStub( RateLimiter::class );
		$limiter->method( 'check' )->willReturn( false );

		$controller = new PreviewController(
			$this->createMock( PlatformRegistry::class ),
			$this->createMock( OptionsRepository::class ),
			new Validator(),
			$limiter
		);

		$response = $controller->handle( new WP_REST_Request() );
		self::assertSame( 429, $response->get_status() );
		self::assertSame( 'rate_limited', $response->get_data()['code'] );
	}

	public function test_handle_returns_tags_json_ld_warnings(): void {
		$registry = $this->createMock( PlatformRegistry::class );
		$registry->method( 'collect_tags' )->willReturn(
			[
				new Tag( Tag::KIND_PROPERTY, 'og:title', 'Hello' ),
				new Tag( Tag::KIND_NAME, 'twitter:card', 'summary' ),
			]
		);
		$registry->method( 'collect_json_ld' )->willReturn( [ '{"@type":"Article"}' ] );

		$options = $this->createMock( OptionsRepository::class );
		$options->method( 'get' )->willReturn( [] );

		$controller = new PreviewController( $registry, $options, new Validator(), $this->passThroughLimiter() );

		$request = new WP_REST_Request();
		$request->set_param( 'context', 'front' );

		$response = $controller->handle( $request );
		self::assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		self::assertSame( 'Hello', $data['tags']['property:og:title'] );
		self::assertSame( 'summary', $data['tags']['name:twitter:card'] );
		self::assertSame( [ '{"@type":"Article"}' ], $data['json_ld'] );
		self::assertIsArray( $data['warnings'] );
	}

	public function test_singular_context_passes_post_id(): void {
		$received = null;

		$registry = $this->createMock( PlatformRegistry::class );
		$registry->method( 'collect_tags' )->willReturnCallback(
			static function ( $context ) use ( &$received ) {
				$received = $context;
				return [];
			}
		);
		$registry->method( 'collect_json_ld' )->willReturn( [] );

		$options = $this->createMock( OptionsRepository::class );
		$options->method( 'get' )->willReturn( [] );

		$request = new WP_REST_Request();
		$request->set_param( 'post_id', 42 );
		$request->set_param( 'context', 'singular' );

		( new PreviewController( $registry, $options, new Validator(), $this->passThroughLimiter() ) )->handle( $request );

		self::assertNotNull( $received );
		self::assertTrue( $received->is_singular() );
		self::assertSame( 42, $received->post_id() );
	}
}
