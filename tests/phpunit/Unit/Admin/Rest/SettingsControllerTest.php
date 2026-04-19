<?php
declare(strict_types=1);

namespace EvzenLeonenko\OpenGraphControl\Tests\Unit\Admin\Rest;

use Brain\Monkey;
use EvzenLeonenko\OpenGraphControl\Admin\Rest\SettingsController;
use EvzenLeonenko\OpenGraphControl\Options\Repository as OptionsRepository;
use PHPUnit\Framework\TestCase;
use WP_REST_Request;

final class SettingsControllerTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_get_returns_full_settings(): void {
		$options = $this->createMock( OptionsRepository::class );
		$options->method( 'get' )->willReturn(
			[
				'version' => 1,
				'site'    => [ 'name' => 'S' ],
			]
		);

		$response = ( new SettingsController( $options ) )->get( new WP_REST_Request() );
		self::assertSame( 200, $response->get_status() );
		self::assertSame( 'S', $response->get_data()['site']['name'] );
	}

	public function test_post_rejects_non_object_body(): void {
		$options = $this->createMock( OptionsRepository::class );
		$options->expects( self::never() )->method( 'update' );

		$request = new WP_REST_Request();
		$request->set_json_params( null );

		$response = ( new SettingsController( $options ) )->post( $request );
		self::assertSame( 400, $response->get_status() );
		self::assertSame( 'invalid_payload', $response->get_data()['code'] );
	}

	public function test_post_updates_with_sanitized_payload(): void {
		$options = $this->createMock( OptionsRepository::class );
		$options->expects( self::once() )->method( 'update' )->with(
			self::callback(
				static function ( $patch ) {
					return is_array( $patch )
						&& isset( $patch['site']['name'] )
						&& 'Clean' === $patch['site']['name'];
				}
			)
		);
		$options->method( 'get' )->willReturn( [ 'site' => [ 'name' => 'Clean' ] ] );

		$request = new WP_REST_Request();
		$request->set_json_params(
			[
				'site' => [
					'name' => '  Clean  ',
				],
			]
		);

		$response = ( new SettingsController( $options ) )->post( $request );
		self::assertSame( 200, $response->get_status() );
	}

	public function test_post_preserves_booleans_and_ints(): void {
		$captured = null;
		$options  = $this->createMock( OptionsRepository::class );
		$options->method( 'update' )->willReturnCallback(
			static function ( $patch ) use ( &$captured ) {
				$captured = $patch;
				return true;
			}
		);
		$options->method( 'get' )->willReturn( [] );

		$request = new WP_REST_Request();
		$request->set_json_params(
			[
				'output' => [
					'strict_mode' => true,
					'cache_ttl'   => 3600,
				],
			]
		);
		( new SettingsController( $options ) )->post( $request );

		self::assertTrue( $captured['output']['strict_mode'] );
		self::assertSame( 3600, $captured['output']['cache_ttl'] );
	}
}
