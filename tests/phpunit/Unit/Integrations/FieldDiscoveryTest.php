<?php
/**
 * FieldDiscovery tests.
 *
 * @package EvzenLeonenko\OpenGraphControl\Tests\Unit\Integrations
 */

declare(strict_types=1);

namespace EvzenLeonenko\OpenGraphControl\Tests\Unit\Integrations;

use Brain\Monkey;
use Brain\Monkey\Functions;
use EvzenLeonenko\OpenGraphControl\Integrations\FieldDiscovery;
use PHPUnit\Framework\TestCase;

/**
 * @covers \EvzenLeonenko\OpenGraphControl\Integrations\FieldDiscovery
 */
final class FieldDiscoveryTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	// ------------------------------------------------------------------
	// acf_fields — plugin missing
	// ------------------------------------------------------------------

	/**
	 * When acf_get_field_groups() is not defined, acf_fields() returns [].
	 */
	public function test_acf_fields_returns_empty_when_acf_not_installed(): void {
		// acf_get_field_groups is not defined in test environment.
		Functions\when( 'get_transient' )->justReturn( false );

		$discovery = new FieldDiscovery();
		self::assertSame( [], $discovery->acf_fields() );
	}

	// ------------------------------------------------------------------
	// acf_fields — cache hit
	// ------------------------------------------------------------------

	/**
	 * When the transient already contains 'acf' data, no collection runs.
	 */
	public function test_acf_fields_returns_cached_value_on_hit(): void {
		Functions\when( 'acf_get_field_groups' )->justReturn( [] );
		Functions\when( 'get_transient' )->justReturn(
			[
				'acf' => [
					'post' => [ 'cached_field' ],
				],
			]
		);
		// acf_get_fields should NOT be called on a cache hit.
		Functions\expect( 'acf_get_fields' )->never();

		$discovery = new FieldDiscovery();
		self::assertSame( [ 'cached_field' ], $discovery->acf_fields( 'post' ) );
	}

	// ------------------------------------------------------------------
	// acf_fields — cache miss: collects and stores
	// ------------------------------------------------------------------

	/**
	 * On a cache miss, collect_acf() runs and results are stored via set_transient.
	 */
	public function test_acf_fields_collects_and_stores_on_cache_miss(): void {
		Functions\when( 'acf_get_field_groups' )->justReturn(
			[
				[
					'key'      => 'group_1',
					'location' => [
						[
							[
								'param'    => 'post_type',
								'operator' => '==',
								'value'    => 'post',
							],
						],
					],
				],
			]
		);
		Functions\when( 'acf_get_fields' )->justReturn(
			[
				[
					'type' => 'text',
					'name' => 'seo_title',
				],
			]
		);
		// No cache initially.
		Functions\when( 'get_transient' )->justReturn( false );
		Functions\expect( 'set_transient' )->once();

		$discovery = new FieldDiscovery();
		$result    = $discovery->acf_fields( 'post' );

		self::assertSame( [ 'seo_title' ], $result );
	}

	// ------------------------------------------------------------------
	// acf_fields — type filtering
	// ------------------------------------------------------------------

	/**
	 * Only text and textarea field types are included; other types are skipped.
	 */
	public function test_acf_fields_filters_to_text_and_textarea_only(): void {
		Functions\when( 'acf_get_field_groups' )->justReturn(
			[
				[
					'key'      => 'group_1',
					'location' => [
						[
							[
								'param'    => 'post_type',
								'operator' => '==',
								'value'    => 'post',
							],
						],
					],
				],
			]
		);
		Functions\when( 'acf_get_fields' )->justReturn(
			[
				[
					'type' => 'text',
					'name' => 'field_text',
				],
				[
					'type' => 'textarea',
					'name' => 'field_textarea',
				],
				[
					'type' => 'image',
					'name' => 'field_image',
				],
				[
					'type' => 'wysiwyg',
					'name' => 'field_wysiwyg',
				],
			]
		);
		Functions\when( 'get_transient' )->justReturn( false );
		Functions\when( 'set_transient' )->justReturn( true );

		$discovery = new FieldDiscovery();
		$result    = $discovery->acf_fields( 'post' );

		self::assertSame( [ 'field_text', 'field_textarea' ], $result );
	}

	// ------------------------------------------------------------------
	// acf_fields — post-type extraction from location rules
	// ------------------------------------------------------------------

	/**
	 * Fields only appear under the post type matched by ACF location rules.
	 */
	public function test_acf_fields_extracts_post_type_from_location_rules(): void {
		Functions\when( 'acf_get_field_groups' )->justReturn(
			[
				[
					'key'      => 'group_1',
					'location' => [
						[
							[
								'param'    => 'post_type',
								'operator' => '==',
								'value'    => 'product',
							],
						],
					],
				],
			]
		);
		Functions\when( 'acf_get_fields' )->justReturn(
			[
				[
					'type' => 'text',
					'name' => 'product_title',
				],
			]
		);
		Functions\when( 'get_transient' )->justReturn( false );
		Functions\when( 'set_transient' )->justReturn( true );

		$discovery = new FieldDiscovery();

		self::assertSame( [ 'product_title' ], $discovery->acf_fields( 'product' ) );
		self::assertSame( [], $discovery->acf_fields( 'post' ) );
	}

	// ------------------------------------------------------------------
	// acf_fields(null) — flat list across all post types
	// ------------------------------------------------------------------

	/**
	 * Calling acf_fields(null) returns a flat, deduplicated list of all field names.
	 */
	public function test_acf_fields_null_returns_flat_deduplicated_list(): void {
		Functions\when( 'acf_get_field_groups' )->justReturn( [] );
		Functions\when( 'get_transient' )->justReturn(
			[
				'acf' => [
					'post'    => [ 'title_field', 'desc_field' ],
					'product' => [ 'desc_field', 'promo_text' ],
				],
			]
		);

		$discovery = new FieldDiscovery();
		$result    = $discovery->acf_fields( null );

		// desc_field appears in both post types but should only appear once.
		self::assertContains( 'title_field', $result );
		self::assertContains( 'desc_field', $result );
		self::assertContains( 'promo_text', $result );
		self::assertCount( 3, $result );
	}

	// ------------------------------------------------------------------
	// jetengine_fields — plugin missing
	// ------------------------------------------------------------------

	/**
	 * When neither jet_engine() nor Jet_Engine class exist, returns [].
	 */
	public function test_jetengine_fields_returns_empty_when_not_installed(): void {
		// jet_engine() is not defined and Jet_Engine class doesn't exist.
		Functions\when( 'get_transient' )->justReturn( false );

		$discovery = new FieldDiscovery();
		self::assertSame( [], $discovery->jetengine_fields() );
	}

	// ------------------------------------------------------------------
	// jetengine_fields — jet_engine() present but meta_boxes missing
	// ------------------------------------------------------------------

	/**
	 * When jet_engine() returns an object without meta_boxes, returns [].
	 */
	public function test_jetengine_fields_returns_empty_when_meta_boxes_missing(): void {
		$jet_stub = new \stdClass();
		// No meta_boxes property.

		Functions\when( 'jet_engine' )->justReturn( $jet_stub );
		Functions\when( 'get_transient' )->justReturn( false );
		Functions\when( 'set_transient' )->justReturn( true );

		$discovery = new FieldDiscovery();
		self::assertSame( [], $discovery->jetengine_fields() );
	}

	// ------------------------------------------------------------------
	// flush — clears transient
	// ------------------------------------------------------------------

	/**
	 * Flush() delegates to delete_transient() with the correct cache key.
	 */
	public function test_flush_deletes_transient(): void {
		$deleted_key = null;
		Functions\when( 'delete_transient' )->alias(
			static function ( string $key ) use ( &$deleted_key ): void {
				$deleted_key = $key;
			}
		);

		$discovery = new FieldDiscovery();
		$discovery->flush();

		self::assertSame( FieldDiscovery::CACHE_KEY, $deleted_key );
	}
}
