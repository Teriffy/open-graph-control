<?php
declare(strict_types=1);

namespace EvzenLeonenko\OpenGraphControl\Tests\Unit\Admin\Rest;

use Brain\Monkey;
use EvzenLeonenko\OpenGraphControl\Admin\Rest\ConflictController;
use EvzenLeonenko\OpenGraphControl\Integrations\Detector;
use EvzenLeonenko\OpenGraphControl\Integrations\IntegrationInterface;
use PHPUnit\Framework\TestCase;
use WP_REST_Request;

final class ConflictControllerTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_get_returns_integration_rows(): void {
		$integration = $this->createStub( IntegrationInterface::class );
		$integration->method( 'slug' )->willReturn( 'yoast' );
		$integration->method( 'label' )->willReturn( 'Yoast SEO' );
		$integration->method( 'is_active' )->willReturn( true );

		$detector = $this->createMock( Detector::class );
		$detector->method( 'all' )->willReturn( [ $integration ] );

		$response = ( new ConflictController( $detector ) )->get( new WP_REST_Request() );
		self::assertSame( 200, $response->get_status() );

		$rows = $response->get_data()['integrations'];
		self::assertCount( 1, $rows );
		self::assertSame( 'yoast', $rows[0]['slug'] );
		self::assertSame( 'Yoast SEO', $rows[0]['label'] );
		self::assertTrue( $rows[0]['active'] );
	}
}
