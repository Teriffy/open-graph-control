<?php
/**
 * AcfFieldResolver tests.
 *
 * @package EvzenLeonenko\OpenGraphControl\Tests\Unit\Integrations
 */

declare(strict_types=1);

namespace EvzenLeonenko\OpenGraphControl\Tests\Unit\Integrations;

use Brain\Monkey;
use Brain\Monkey\Functions;
use EvzenLeonenko\OpenGraphControl\Integrations\AcfFieldResolver;
use EvzenLeonenko\OpenGraphControl\Resolvers\Context;
use PHPUnit\Framework\TestCase;

/**
 * @covers \EvzenLeonenko\OpenGraphControl\Integrations\AcfFieldResolver
 */
final class AcfFieldResolverTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	// ------------------------------------------------------------------
	// Pass-through: value already set
	// ------------------------------------------------------------------

	/**
	 * When a non-null value is passed, the resolver returns it unchanged.
	 */
	public function test_title_step_passes_through_existing_value(): void {
		$resolver = new AcfFieldResolver();
		$context  = Context::for_post( 1 );

		$result = $resolver->on_title_step( 'already set', 'acf_title_field', $context );

		self::assertSame( 'already set', $result );
	}

	// ------------------------------------------------------------------
	// Pass-through: wrong step name
	// ------------------------------------------------------------------

	/**
	 * When the step name does not match, the resolver returns null unchanged.
	 */
	public function test_title_step_passes_through_on_wrong_step(): void {
		$resolver = new AcfFieldResolver();
		$context  = Context::for_post( 1 );

		$result = $resolver->on_title_step( null, 'post_title', $context );

		self::assertNull( $result );
	}

	// ------------------------------------------------------------------
	// Pass-through: get_field not available
	// ------------------------------------------------------------------

	/**
	 * When get_field() is not defined, returns null.
	 */
	public function test_title_step_returns_null_when_get_field_missing(): void {
		// get_field is not defined in the test environment.
		$resolver = new AcfFieldResolver();
		$context  = Context::for_post( 1 );

		$result = $resolver->on_title_step( null, 'acf_title_field', $context );

		self::assertNull( $result );
	}

	// ------------------------------------------------------------------
	// Pass-through: no post context
	// ------------------------------------------------------------------

	/**
	 * When the context has no post ID, returns null.
	 */
	public function test_title_step_returns_null_for_non_post_context(): void {
		Functions\when( 'get_field' )->justReturn( 'anything' );

		$resolver = new AcfFieldResolver();
		$context  = Context::for_front();

		$result = $resolver->on_title_step( null, 'acf_title_field', $context );

		self::assertNull( $result );
	}

	// ------------------------------------------------------------------
	// Pass-through: no field configured
	// ------------------------------------------------------------------

	/**
	 * When no ACF field is configured for the post type, returns null.
	 */
	public function test_title_step_returns_null_when_no_field_configured(): void {
		Functions\when( 'get_field' )->justReturn( 'anything' );
		Functions\when( 'get_post_type' )->justReturn( 'post' );
		Functions\when( 'get_option' )->justReturn( [] );

		$resolver = new AcfFieldResolver();
		$result   = $resolver->on_title_step( null, 'acf_title_field', Context::for_post( 5 ) );

		self::assertNull( $result );
	}

	// ------------------------------------------------------------------
	// Happy path: title resolved from ACF field
	// ------------------------------------------------------------------

	/**
	 * Returns stripped and trimmed value from get_field() on the happy path.
	 */
	public function test_title_step_returns_acf_field_value(): void {
		Functions\when( 'get_field' )->justReturn( '  <b>ACF Title</b>  ' );
		Functions\when( 'get_post_type' )->justReturn( 'post' );
		Functions\when( 'get_option' )->justReturn(
			[
				'acf' => [
					'post' => [
						'title' => 'my_title_field',
					],
				],
			]
		);
		Functions\when( 'wp_strip_all_tags' )->alias(
			static fn ( string $s ): string => strip_tags( $s )
		);

		$resolver = new AcfFieldResolver();
		$result   = $resolver->on_title_step( null, 'acf_title_field', Context::for_post( 7 ) );

		self::assertSame( 'ACF Title', $result );
	}

	// ------------------------------------------------------------------
	// Happy path: description resolved from ACF field
	// ------------------------------------------------------------------

	/**
	 * Description step returns value from get_field() for the description kind.
	 */
	public function test_description_step_returns_acf_field_value(): void {
		Functions\when( 'get_field' )->justReturn( 'ACF description text' );
		Functions\when( 'get_post_type' )->justReturn( 'page' );
		Functions\when( 'get_option' )->justReturn(
			[
				'acf' => [
					'page' => [
						'description' => 'my_desc_field',
					],
				],
			]
		);
		Functions\when( 'wp_strip_all_tags' )->alias(
			static fn ( string $s ): string => strip_tags( $s )
		);

		$resolver = new AcfFieldResolver();
		$result   = $resolver->on_description_step( null, 'acf_description_field', Context::for_post( 3 ) );

		self::assertSame( 'ACF description text', $result );
	}

	// ------------------------------------------------------------------
	// Edge case: get_field returns non-string
	// ------------------------------------------------------------------

	/**
	 * When get_field() returns a non-string (e.g. array), returns null.
	 */
	public function test_title_step_returns_null_when_field_value_not_string(): void {
		Functions\when( 'get_field' )->justReturn( [ 'not', 'a', 'string' ] );
		Functions\when( 'get_post_type' )->justReturn( 'post' );
		Functions\when( 'get_option' )->justReturn(
			[
				'acf' => [
					'post' => [
						'title' => 'array_field',
					],
				],
			]
		);

		$resolver = new AcfFieldResolver();
		$result   = $resolver->on_title_step( null, 'acf_title_field', Context::for_post( 2 ) );

		self::assertNull( $result );
	}

	// ------------------------------------------------------------------
	// Edge case: get_field returns blank string after stripping
	// ------------------------------------------------------------------

	/**
	 * When the stripped value is empty, returns null rather than an empty string.
	 */
	public function test_title_step_returns_null_when_stripped_value_is_empty(): void {
		Functions\when( 'get_field' )->justReturn( '   ' );
		Functions\when( 'get_post_type' )->justReturn( 'post' );
		Functions\when( 'get_option' )->justReturn(
			[
				'acf' => [
					'post' => [
						'title' => 'blank_field',
					],
				],
			]
		);
		Functions\when( 'wp_strip_all_tags' )->alias(
			static fn ( string $s ): string => strip_tags( $s )
		);

		$resolver = new AcfFieldResolver();
		$result   = $resolver->on_title_step( null, 'acf_title_field', Context::for_post( 9 ) );

		self::assertNull( $result );
	}
}
