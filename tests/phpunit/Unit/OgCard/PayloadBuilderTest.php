<?php
/**
 * PayloadBuilder factory tests.
 *
 * @package EvzenLeonenko\OpenGraphControl
 */

declare(strict_types=1);

namespace EvzenLeonenko\OpenGraphControl\Tests\Unit\OgCard;

use Brain\Monkey;
use EvzenLeonenko\OpenGraphControl\OgCard\PayloadBuilder;
use EvzenLeonenko\OpenGraphControl\Resolvers\{Description, Title, Context};
use PHPUnit\Framework\TestCase;

/**
 * Tests for PayloadBuilder factory.
 *
 * Verifies that PayloadBuilder correctly integrates Title and Description
 * resolvers and creates Payload instances with proper fallback logic.
 */
final class PayloadBuilderTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Tests for_post() calls title and description resolvers.
	 *
	 * @return void
	 */
	public function test_for_post_calls_title_and_description_resolvers(): void {
		$title = $this->createMock( Title::class );
		$title->method( 'resolve' )->willReturn( 'Resolved title' );
		$desc = $this->createMock( Description::class );
		$desc->method( 'resolve' )->willReturn( 'Resolved description' );

		Monkey\Functions\when( 'get_post' )->justReturn(
			(object) [
				'ID'          => 5,
				'post_title'  => 'Fallback',
				'post_date'   => '2026-04-20',
				'post_author' => 1,
			]
		);
		Monkey\Functions\when( 'get_bloginfo' )->justReturn( 'My Site' );
		Monkey\Functions\when( 'get_permalink' )->justReturn( 'https://example.test/p/5' );
		Monkey\Functions\when( 'get_userdata' )->justReturn( (object) [ 'display_name' => 'Author' ] );
		Monkey\Functions\when( 'wp_date' )->justReturn( 'April 2026' );
		Monkey\Functions\when( 'wp_parse_url' )->justReturn( [ 'host' => 'example.test' ] );

		$builder = new PayloadBuilder( $title, $desc );
		$payload = $builder->for_post( 5 );

		$this->assertSame( 'Resolved title', $payload->title );
		$this->assertSame( 'Resolved description', $payload->description );
		$this->assertSame( 'My Site', $payload->site_name );
		$this->assertStringContainsString( 'April 2026', $payload->meta_line );
		$this->assertStringContainsString( 'Author', $payload->meta_line );
	}

	/**
	 * Tests for_post() falls back to post_title when resolver returns null.
	 *
	 * @return void
	 */
	public function test_for_post_falls_back_to_post_title(): void {
		$title = $this->createMock( Title::class );
		$title->method( 'resolve' )->willReturn( null );
		$desc = $this->createMock( Description::class );
		$desc->method( 'resolve' )->willReturn( null );

		Monkey\Functions\when( 'get_post' )->justReturn(
			(object) [
				'ID'          => 5,
				'post_title'  => 'Post Title Fallback',
				'post_date'   => '2026-04-20',
				'post_author' => 1,
			]
		);
		Monkey\Functions\when( 'get_bloginfo' )->justReturn( 'My Site' );
		Monkey\Functions\when( 'get_permalink' )->justReturn( 'https://example.test/p/5' );
		Monkey\Functions\when( 'get_userdata' )->justReturn( (object) [ 'display_name' => 'Author' ] );
		Monkey\Functions\when( 'wp_date' )->justReturn( 'April 2026' );
		Monkey\Functions\when( 'wp_parse_url' )->justReturn( [ 'host' => 'example.test' ] );

		$builder = new PayloadBuilder( $title, $desc );
		$payload = $builder->for_post( 5 );

		$this->assertSame( 'Post Title Fallback', $payload->title );
		$this->assertSame( '', $payload->description );
	}

	/**
	 * Tests for_post() throws when post not found.
	 *
	 * @return void
	 */
	public function test_for_post_throws_when_post_not_found(): void {
		$title = $this->createMock( Title::class );
		$desc  = $this->createMock( Description::class );

		Monkey\Functions\when( 'get_post' )->justReturn( null );

		$builder = new PayloadBuilder( $title, $desc );

		$this->expectException( \InvalidArgumentException::class );
		$builder->for_post( 999 );
	}

	/**
	 * Tests for_archive_term() calls resolvers and includes term name.
	 *
	 * @return void
	 */
	public function test_for_archive_term_calls_resolvers(): void {
		$title = $this->createMock( Title::class );
		$title->method( 'resolve' )->willReturn( 'Category Title' );
		$desc = $this->createMock( Description::class );
		$desc->method( 'resolve' )->willReturn( 'Category Description' );

		Monkey\Functions\when( 'get_term' )->justReturn(
			(object) [
				'term_id' => 3,
				'name'    => 'Uncategorized',
			]
		);
		Monkey\Functions\when( 'get_bloginfo' )->justReturn( 'My Site' );
		Monkey\Functions\when( 'get_term_link' )->justReturn( 'https://example.test/category/uncategorized' );

		$builder = new PayloadBuilder( $title, $desc );
		$payload = $builder->for_archive_term( 'category', 3 );

		$this->assertSame( 'Category Title', $payload->title );
		$this->assertSame( 'Category Description', $payload->description );
		$this->assertSame( 'https://example.test/category/uncategorized', $payload->url );
	}

	/**
	 * Tests for_archive_term() falls back to term name.
	 *
	 * @return void
	 */
	public function test_for_archive_term_falls_back_to_term_name(): void {
		$title = $this->createMock( Title::class );
		$title->method( 'resolve' )->willReturn( null );
		$desc = $this->createMock( Description::class );
		$desc->method( 'resolve' )->willReturn( null );

		Monkey\Functions\when( 'get_term' )->justReturn(
			(object) [
				'term_id' => 3,
				'name'    => 'My Category',
			]
		);
		Monkey\Functions\when( 'get_bloginfo' )->justReturn( 'My Site' );
		Monkey\Functions\when( 'get_term_link' )->justReturn( 'https://example.test/category/my-category' );

		$builder = new PayloadBuilder( $title, $desc );
		$payload = $builder->for_archive_term( 'category', 3 );

		$this->assertSame( 'My Category', $payload->title );
		$this->assertSame( '', $payload->description );
	}

	/**
	 * Tests for_author() calls resolvers and includes author info.
	 *
	 * @return void
	 */
	public function test_for_author_calls_resolvers(): void {
		$title = $this->createMock( Title::class );
		$title->method( 'resolve' )->willReturn( 'John Doe' );
		$desc = $this->createMock( Description::class );
		$desc->method( 'resolve' )->willReturn( 'Author Biography' );

		Monkey\Functions\when( 'get_userdata' )->justReturn(
			(object) [
				'ID'           => 1,
				'display_name' => 'John Doe',
			]
		);
		Monkey\Functions\when( 'get_bloginfo' )->justReturn( 'My Site' );
		Monkey\Functions\when( 'get_author_posts_url' )->justReturn( 'https://example.test/author/john-doe' );

		$builder = new PayloadBuilder( $title, $desc );
		$payload = $builder->for_author( 1 );

		$this->assertSame( 'John Doe', $payload->title );
		$this->assertSame( 'Author Biography', $payload->description );
		$this->assertSame( 'https://example.test/author/john-doe', $payload->url );
	}

	/**
	 * Tests for_author() falls back to display_name.
	 *
	 * @return void
	 */
	public function test_for_author_falls_back_to_display_name(): void {
		$title = $this->createMock( Title::class );
		$title->method( 'resolve' )->willReturn( null );
		$desc = $this->createMock( Description::class );
		$desc->method( 'resolve' )->willReturn( null );

		Monkey\Functions\when( 'get_userdata' )->justReturn(
			(object) [
				'ID'           => 1,
				'display_name' => 'Jane Smith',
			]
		);
		Monkey\Functions\when( 'get_bloginfo' )->justReturn( 'My Site' );
		Monkey\Functions\when( 'get_author_posts_url' )->justReturn( 'https://example.test/author/jane-smith' );

		$builder = new PayloadBuilder( $title, $desc );
		$payload = $builder->for_author( 1 );

		$this->assertSame( 'Jane Smith', $payload->title );
		$this->assertSame( '', $payload->description );
	}
}
