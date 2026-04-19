<?php
declare(strict_types=1);

namespace EvzenLeonenko\OpenGraphControl\Tests\Unit\Admin\Rest;

use Brain\Monkey;
use Brain\Monkey\Functions;
use EvzenLeonenko\OpenGraphControl\Admin\Rest\ArchiveMetaController;
use EvzenLeonenko\OpenGraphControl\ArchiveMeta\Repository;
use PHPUnit\Framework\TestCase;
use WP_REST_Request;

final class ArchiveMetaControllerTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_get_term_forbidden_without_cap(): void {
		Functions\when( 'taxonomy_exists' )->justReturn( true );
		Functions\when( 'get_taxonomy' )->justReturn(
			(object) [ 'cap' => (object) [ 'manage_terms' => 'manage_categories' ] ]
		);
		Functions\when( 'current_user_can' )->justReturn( false );

		$request = new WP_REST_Request();
		$request->set_param( 'tax', 'category' );
		$request->set_param( 'id', 12 );

		$controller = new ArchiveMetaController( $this->createStub( Repository::class ) );
		self::assertFalse( $controller->can_manage_term_from_request( $request ) );
	}

	public function test_get_user_forbidden_without_cap(): void {
		Functions\when( 'current_user_can' )->justReturn( false );

		$request = new WP_REST_Request();
		$request->set_param( 'id', 3 );

		$controller = new ArchiveMetaController( $this->createStub( Repository::class ) );
		self::assertFalse( $controller->can_edit_user_from_request( $request ) );
	}

	public function test_post_term_invalid_taxonomy_400(): void {
		Functions\when( 'taxonomy_exists' )->justReturn( false );

		$repo = $this->createMock( Repository::class );
		$repo->expects( self::never() )->method( 'save' );

		$request = new WP_REST_Request();
		$request->set_param( 'tax', 'nope' );
		$request->set_param( 'id', 1 );
		$request->set_json_params( [ 'title' => 'X' ] );

		$response = ( new ArchiveMetaController( $repo ) )->save_term( $request );
		self::assertSame( 400, $response->get_status() );
		self::assertSame( 'invalid_taxonomy', $response->get_data()['code'] );
	}

	public function test_post_term_invalid_image_400(): void {
		Functions\when( 'taxonomy_exists' )->justReturn( true );
		Functions\when( 'get_term' )->justReturn(
			(object) [
				'term_id' => 12,
				'name'    => 'Recepty',
			]
		);
		Functions\when( 'get_post_type' )->justReturn( 'post' );

		$repo = $this->createMock( Repository::class );
		$repo->expects( self::never() )->method( 'save' );

		$request = new WP_REST_Request();
		$request->set_param( 'tax', 'category' );
		$request->set_param( 'id', 12 );
		$request->set_json_params( [ 'image_id' => 99 ] );

		$response = ( new ArchiveMetaController( $repo ) )->save_term( $request );
		self::assertSame( 400, $response->get_status() );
		self::assertSame( 'invalid_image', $response->get_data()['code'] );
	}

	public function test_post_term_roundtrip(): void {
		Functions\when( 'taxonomy_exists' )->justReturn( true );
		Functions\when( 'get_term' )->justReturn(
			(object) [
				'term_id' => 12,
				'name'    => 'Recepty',
			]
		);

		$captured = null;
		$repo     = $this->createMock( Repository::class );
		$repo->method( 'save' )->willReturnCallback(
			static function ( $kind, $id, $payload ) use ( &$captured ) {
				$captured = [ $kind, $id, $payload ];
				return true;
			}
		);
		$repo->method( 'get_for_term' )->willReturn(
			[
				'title'       => 'X',
				'description' => '',
				'image_id'    => 0,
				'exclude'     => [],
			]
		);

		$request = new WP_REST_Request();
		$request->set_param( 'tax', 'category' );
		$request->set_param( 'id', 12 );
		$request->set_json_params( [ 'title' => '  X  ' ] );

		$response = ( new ArchiveMetaController( $repo ) )->save_term( $request );
		self::assertSame( 200, $response->get_status() );
		self::assertSame( 'term', $captured[0] );
		self::assertSame( 12, $captured[1] );
		self::assertSame( 'X', $captured[2]['title'] );
		self::assertSame( 'X', $response->get_data()['title'] );
	}

	public function test_post_user_roundtrip(): void {
		Functions\when( 'get_userdata' )->justReturn(
			(object) [
				'ID'           => 3,
				'display_name' => 'Evžen',
			]
		);

		$captured = null;
		$repo     = $this->createMock( Repository::class );
		$repo->method( 'save' )->willReturnCallback(
			static function ( $kind, $id, $payload ) use ( &$captured ) {
				$captured = [ $kind, $id, $payload ];
				return true;
			}
		);
		$repo->method( 'get_for_user' )->willReturn(
			[
				'title'       => '',
				'description' => 'About Evžen',
				'image_id'    => 0,
				'exclude'     => [],
			]
		);

		$request = new WP_REST_Request();
		$request->set_param( 'id', 3 );
		$request->set_json_params( [ 'description' => 'About Evžen' ] );

		$response = ( new ArchiveMetaController( $repo ) )->save_user( $request );
		self::assertSame( 200, $response->get_status() );
		self::assertSame( 'user', $captured[0] );
		self::assertSame( 3, $captured[1] );
		self::assertSame( 'About Evžen', $captured[2]['description'] );
		self::assertSame( 'About Evžen', $response->get_data()['description'] );
	}

	public function test_list_overrides_aggregates_terms_and_users(): void {
		Functions\when( 'get_taxonomies' )->justReturn(
			[
				'category' => 'category',
				'post_tag' => 'post_tag',
			]
		);
		Functions\when( 'get_terms' )->justReturn(
			[
				(object) [
					'term_id'  => 12,
					'taxonomy' => 'category',
					'name'     => 'Recepty',
				],
			]
		);
		Functions\when( 'get_term_meta' )->justReturn(
			[
				'title'    => 'Recepty OG',
				'image_id' => 42,
			]
		);
		Functions\when( 'get_users' )->justReturn(
			[
				(object) [
					'ID'           => 3,
					'display_name' => 'Evžen Leonenko',
				],
			]
		);
		Functions\when( 'get_user_meta' )->justReturn(
			[
				'title'       => 'Evžen',
				'description' => 'Admin',
			]
		);

		$request  = new WP_REST_Request();
		$response = ( new ArchiveMetaController( new Repository() ) )->list_overrides( $request );
		self::assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		self::assertCount( 1, $data['terms'] );
		self::assertSame( 'category', $data['terms'][0]['tax'] );
		self::assertSame( 12, $data['terms'][0]['term_id'] );
		self::assertSame( 'Recepty', $data['terms'][0]['name'] );
		self::assertEqualsCanonicalizing( [ 'title', 'image_id' ], $data['terms'][0]['fields_set'] );

		self::assertCount( 1, $data['users'] );
		self::assertSame( 3, $data['users'][0]['user_id'] );
		self::assertSame( 'Evžen Leonenko', $data['users'][0]['name'] );
		self::assertEqualsCanonicalizing( [ 'title', 'description' ], $data['users'][0]['fields_set'] );
	}

	public function test_sanitize_strips_unknown_keys(): void {
		Functions\when( 'taxonomy_exists' )->justReturn( true );
		Functions\when( 'get_term' )->justReturn(
			(object) [
				'term_id' => 12,
				'name'    => 'Recepty',
			]
		);

		$captured = null;
		$repo     = $this->createMock( Repository::class );
		$repo->method( 'save' )->willReturnCallback(
			static function ( $kind, $id, $payload ) use ( &$captured ) {
				$captured = $payload;
				return true;
			}
		);
		$repo->method( 'get_for_term' )->willReturn(
			[
				'title'       => 'X',
				'description' => '',
				'image_id'    => 0,
				'exclude'     => [],
			]
		);

		$request = new WP_REST_Request();
		$request->set_param( 'tax', 'category' );
		$request->set_param( 'id', 12 );
		$request->set_json_params(
			[
				'title'     => 'X',
				'__proto__' => 'nope',
				'unknown'   => 'dropped',
			]
		);

		( new ArchiveMetaController( $repo ) )->save_term( $request );

		self::assertArrayHasKey( 'title', $captured );
		self::assertArrayNotHasKey( '__proto__', $captured );
		self::assertArrayNotHasKey( 'unknown', $captured );
	}

	public function test_exclude_intersected_with_allowlist(): void {
		Functions\when( 'taxonomy_exists' )->justReturn( true );
		Functions\when( 'get_term' )->justReturn(
			(object) [
				'term_id' => 12,
				'name'    => 'Recepty',
			]
		);

		$captured = null;
		$repo     = $this->createMock( Repository::class );
		$repo->method( 'save' )->willReturnCallback(
			static function ( $kind, $id, $payload ) use ( &$captured ) {
				$captured = $payload;
				return true;
			}
		);
		$repo->method( 'get_for_term' )->willReturn(
			[
				'title'       => '',
				'description' => '',
				'image_id'    => 0,
				'exclude'     => [ 'all' ],
			]
		);

		$request = new WP_REST_Request();
		$request->set_param( 'tax', 'category' );
		$request->set_param( 'id', 12 );
		$request->set_json_params( [ 'exclude' => [ 'all', 'bogus', 42 ] ] );

		( new ArchiveMetaController( $repo ) )->save_term( $request );

		self::assertSame( [ 'all' ], array_values( $captured['exclude'] ) );
	}
}
