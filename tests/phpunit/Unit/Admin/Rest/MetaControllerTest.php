<?php
declare(strict_types=1);

namespace EvzenLeonenko\OpenGraphControl\Tests\Unit\Admin\Rest;

use Brain\Monkey;
use EvzenLeonenko\OpenGraphControl\Admin\Rest\MetaController;
use EvzenLeonenko\OpenGraphControl\PostMeta\Repository as PostMetaRepository;
use PHPUnit\Framework\TestCase;
use WP_REST_Request;

final class MetaControllerTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_get_meta_returns_post_meta(): void {
		$repo = $this->createMock( PostMetaRepository::class );
		$repo->method( 'get' )->with( 42 )->willReturn(
			[
				'title'       => 'T',
				'description' => 'D',
				'image_id'    => 7,
				'type'        => '',
				'platforms'   => [],
				'exclude'     => [],
			]
		);

		$request = new WP_REST_Request();
		$request->set_param( 'post_id', 42 );

		$response = ( new MetaController( $repo ) )->get_meta( $request );
		self::assertSame( 'T', $response->get_data()['title'] );
		self::assertSame( 7, $response->get_data()['image_id'] );
	}

	public function test_save_meta_rejects_non_object_body(): void {
		$repo = $this->createMock( PostMetaRepository::class );
		$repo->expects( self::never() )->method( 'save' );

		$request = new WP_REST_Request();
		$request->set_param( 'post_id', 7 );
		$request->set_json_params( null );

		$response = ( new MetaController( $repo ) )->save_meta( $request );
		self::assertSame( 400, $response->get_status() );
	}

	public function test_save_meta_sanitizes_and_returns_updated(): void {
		$captured_payload = null;

		$repo = $this->createMock( PostMetaRepository::class );
		$repo->method( 'save' )->willReturnCallback(
			static function ( $id, $payload ) use ( &$captured_payload ) {
				$captured_payload = $payload;
				return true;
			}
		);
		$repo->method( 'get' )->willReturn(
			[
				'title'       => 'X',
				'description' => '',
				'image_id'    => 0,
				'type'        => '',
				'platforms'   => [],
				'exclude'     => [],
			]
		);

		$request = new WP_REST_Request();
		$request->set_param( 'post_id', 9 );
		$request->set_json_params(
			[
				'title'    => '  trimmed  ',
				'image_id' => 42,
			]
		);

		$response = ( new MetaController( $repo ) )->save_meta( $request );
		self::assertSame( 200, $response->get_status() );
		self::assertSame( 'trimmed', $captured_payload['title'] );
		self::assertSame( 42, $captured_payload['image_id'] );
	}
}
