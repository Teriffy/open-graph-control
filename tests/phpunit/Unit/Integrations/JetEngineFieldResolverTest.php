<?php
/**
 * JetEngineFieldResolver tests.
 *
 * @package EvzenLeonenko\OpenGraphControl\Tests\Unit\Integrations
 */

declare(strict_types=1);

namespace EvzenLeonenko\OpenGraphControl\Tests\Unit\Integrations;

use Brain\Monkey;
use Brain\Monkey\Functions;
use EvzenLeonenko\OpenGraphControl\Integrations\JetEngineFieldResolver;
use EvzenLeonenko\OpenGraphControl\Resolvers\Context;
use PHPUnit\Framework\TestCase;

/**
 * @covers \EvzenLeonenko\OpenGraphControl\Integrations\JetEngineFieldResolver
 */
final class JetEngineFieldResolverTest extends TestCase {

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
		$resolver = new JetEngineFieldResolver();
		$context  = Context::for_post( 1 );

		$result = $resolver->on_title_step( 'already set', 'jet_title_field', $context );

		self::assertSame( 'already set', $result );
	}

	// ------------------------------------------------------------------
	// Pass-through: wrong step name
	// ------------------------------------------------------------------

	/**
	 * When the step name does not match, the resolver returns null unchanged.
	 */
	public function test_title_step_passes_through_on_wrong_step(): void {
		$resolver = new JetEngineFieldResolver();
		$context  = Context::for_post( 1 );

		$result = $resolver->on_title_step( null, 'post_title', $context );

		self::assertNull( $result );
	}

	// ------------------------------------------------------------------
	// Pass-through: get_post_type returns non-string
	// ------------------------------------------------------------------

	/**
	 * When get_post_type() returns false (post doesn't exist), returns null.
	 */
	public function test_title_step_returns_null_when_post_type_unresolvable(): void {
		Functions\when( 'jet_engine' )->justReturn( new \stdClass() );
		Functions\when( 'get_post_type' )->justReturn( false );

		$resolver = new JetEngineFieldResolver();
		$result   = $resolver->on_title_step( null, 'jet_title_field', Context::for_post( 999 ) );

		self::assertNull( $result );
	}

	// ------------------------------------------------------------------
	// Pass-through: no post context
	// ------------------------------------------------------------------

	/**
	 * When the context has no post ID, returns null.
	 */
	public function test_title_step_returns_null_for_non_post_context(): void {
		Functions\when( 'jet_engine' )->justReturn( new \stdClass() );

		$resolver = new JetEngineFieldResolver();
		$context  = Context::for_front();

		$result = $resolver->on_title_step( null, 'jet_title_field', $context );

		self::assertNull( $result );
	}

	// ------------------------------------------------------------------
	// Pass-through: no field configured
	// ------------------------------------------------------------------

	/**
	 * When no JetEngine field is configured for the post type, returns null.
	 */
	public function test_title_step_returns_null_when_no_field_configured(): void {
		Functions\when( 'jet_engine' )->justReturn( new \stdClass() );
		Functions\when( 'get_post_type' )->justReturn( 'post' );
		Functions\when( 'get_option' )->justReturn( [] );

		$resolver = new JetEngineFieldResolver();
		$result   = $resolver->on_title_step( null, 'jet_title_field', Context::for_post( 5 ) );

		self::assertNull( $result );
	}

	// ------------------------------------------------------------------
	// Happy path: title resolved from JetEngine meta field
	// ------------------------------------------------------------------

	/**
	 * Returns stripped and trimmed value from get_post_meta() on the happy path.
	 */
	public function test_title_step_returns_jet_meta_value(): void {
		Functions\when( 'jet_engine' )->justReturn( new \stdClass() );
		Functions\when( 'get_post_meta' )->justReturn( '  <b>Jet Title</b>  ' );
		Functions\when( 'get_post_type' )->justReturn( 'post' );
		Functions\when( 'get_option' )->justReturn(
			[
				'jet' => [
					'post' => [
						'title' => 'jet_title_field_key',
					],
				],
			]
		);
		Functions\when( 'wp_strip_all_tags' )->alias(
			static fn ( string $s ): string => strip_tags( $s )
		);

		$resolver = new JetEngineFieldResolver();
		$result   = $resolver->on_title_step( null, 'jet_title_field', Context::for_post( 7 ) );

		self::assertSame( 'Jet Title', $result );
	}

	// ------------------------------------------------------------------
	// Happy path: description resolved from JetEngine meta field
	// ------------------------------------------------------------------

	/**
	 * Description step returns value from get_post_meta() for the description kind.
	 */
	public function test_description_step_returns_jet_meta_value(): void {
		Functions\when( 'jet_engine' )->justReturn( new \stdClass() );
		Functions\when( 'get_post_meta' )->justReturn( 'Jet description text' );
		Functions\when( 'get_post_type' )->justReturn( 'page' );
		Functions\when( 'get_option' )->justReturn(
			[
				'jet' => [
					'page' => [
						'description' => 'jet_desc_key',
					],
				],
			]
		);
		Functions\when( 'wp_strip_all_tags' )->alias(
			static fn ( string $s ): string => strip_tags( $s )
		);

		$resolver = new JetEngineFieldResolver();
		$result   = $resolver->on_description_step( null, 'jet_description_field', Context::for_post( 3 ) );

		self::assertSame( 'Jet description text', $result );
	}

	// ------------------------------------------------------------------
	// Edge case: get_post_meta returns non-string
	// ------------------------------------------------------------------

	/**
	 * When get_post_meta() returns a non-string value, returns null.
	 */
	public function test_title_step_returns_null_when_meta_value_not_string(): void {
		Functions\when( 'jet_engine' )->justReturn( new \stdClass() );
		Functions\when( 'get_post_meta' )->justReturn( false );
		Functions\when( 'get_post_type' )->justReturn( 'post' );
		Functions\when( 'get_option' )->justReturn(
			[
				'jet' => [
					'post' => [
						'title' => 'empty_meta_key',
					],
				],
			]
		);

		$resolver = new JetEngineFieldResolver();
		$result   = $resolver->on_title_step( null, 'jet_title_field', Context::for_post( 2 ) );

		self::assertNull( $result );
	}

	// ------------------------------------------------------------------
	// Edge case: meta value is blank after stripping
	// ------------------------------------------------------------------

	/**
	 * When the stripped meta value is empty, returns null rather than an empty string.
	 */
	public function test_title_step_returns_null_when_stripped_value_is_empty(): void {
		Functions\when( 'jet_engine' )->justReturn( new \stdClass() );
		Functions\when( 'get_post_meta' )->justReturn( '   ' );
		Functions\when( 'get_post_type' )->justReturn( 'post' );
		Functions\when( 'get_option' )->justReturn(
			[
				'jet' => [
					'post' => [
						'title' => 'whitespace_field',
					],
				],
			]
		);
		Functions\when( 'wp_strip_all_tags' )->alias(
			static fn ( string $s ): string => strip_tags( $s )
		);

		$resolver = new JetEngineFieldResolver();
		$result   = $resolver->on_title_step( null, 'jet_title_field', Context::for_post( 9 ) );

		self::assertNull( $result );
	}
}
