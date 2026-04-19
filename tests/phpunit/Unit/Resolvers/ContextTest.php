<?php
declare(strict_types=1);

namespace EvzenLeonenko\OpenGraphControl\Tests\Unit\Resolvers;

use EvzenLeonenko\OpenGraphControl\Resolvers\Context;
use PHPUnit\Framework\TestCase;

final class ContextTest extends TestCase {

	public function test_singular_post_context(): void {
		$c = Context::for_post( 42 );
		self::assertTrue( $c->is_singular() );
		self::assertSame( 42, $c->post_id() );
		self::assertSame( Context::TYPE_SINGULAR, $c->type() );
	}

	public function test_front_context(): void {
		$c = Context::for_front();
		self::assertFalse( $c->is_singular() );
		self::assertSame( Context::TYPE_FRONT, $c->type() );
		self::assertNull( $c->post_id() );
	}

	public function test_author_carries_user_id(): void {
		$c = Context::for_author( 7 );
		self::assertSame( 7, $c->extra( 'user_id' ) );
		self::assertNull( $c->extra( 'missing' ) );
		self::assertSame( 'fb', $c->extra( 'missing', 'fb' ) );
	}

	public function test_archive_kind_preserved(): void {
		$c = Context::for_archive( 'category' );
		self::assertSame( 'category', $c->extra( 'archive_kind' ) );
		self::assertSame( Context::TYPE_ARCHIVE, $c->type() );
	}

	public function test_other_factories(): void {
		self::assertSame( Context::TYPE_BLOG, Context::for_blog()->type() );
		self::assertSame( Context::TYPE_DATE, Context::for_date()->type() );
		self::assertSame( Context::TYPE_SEARCH, Context::for_search()->type() );
		self::assertSame( Context::TYPE_404, Context::for_404()->type() );
	}

	public function test_for_archive_term_stores_taxonomy_and_term_id(): void {
		$context = Context::for_archive_term( 'category', 42 );
		self::assertTrue( $context->is_archive() );
		self::assertTrue( $context->is_archive_term() );
		self::assertSame( 42, $context->archive_term_id() );
		self::assertSame( 'category', $context->archive_kind() );
	}

	public function test_for_archive_returns_null_term_id(): void {
		$context = Context::for_archive( 'post_type' );
		self::assertFalse( $context->is_archive_term() );
		self::assertNull( $context->archive_term_id() );
	}

	public function test_is_archive_returns_true_for_kind_only_archive(): void {
		$c = Context::for_archive( 'post_type' );
		self::assertTrue( $c->is_archive() );
		self::assertFalse( $c->is_archive_term() );
	}
}
