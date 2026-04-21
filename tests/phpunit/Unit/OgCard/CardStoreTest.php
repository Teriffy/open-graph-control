<?php
/**
 * CardStore path generation tests.
 *
 * @package EvzenLeonenko\OpenGraphControl
 */

declare(strict_types=1);

namespace EvzenLeonenko\OpenGraphControl\Tests\Unit\OgCard;

use Brain\Monkey;
use Brain\Monkey\Functions;
use EvzenLeonenko\OpenGraphControl\OgCard\CardKey;
use EvzenLeonenko\OpenGraphControl\OgCard\CardStore;
use EvzenLeonenko\OpenGraphControl\OgCard\Template;
use PHPUnit\Framework\TestCase;

/**
 * Tests for CardStore path generation.
 *
 * Verifies that CardStore correctly generates versioned filesystem paths
 * for OG card images based on CardKey type and Template hash.
 */
final class CardStoreTest extends TestCase {

	/**
	 * Temporary base directory for write tests.
	 *
	 * @var string
	 */
	private string $base;

	/**
	 * Sets up test fixtures.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		$this->base = sys_get_temp_dir() . '/' . uniqid( 'ogc_test_', true );
	}

	/**
	 * Cleans up temporary files after each test.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
		if ( is_dir( $this->base ) ) {
			$this->remove_dir_recursively( $this->base );
		}
	}

	/**
	 * Recursively removes a directory and all its contents.
	 *
	 * @param string $dir Directory path.
	 *
	 * @return void
	 */
	private function remove_dir_recursively( string $dir ): void {
		if ( ! is_dir( $dir ) ) {
			return;
		}
		$files = scandir( $dir );
		if ( false === $files ) {
			return;
		}
		foreach ( $files as $file ) {
			if ( '.' !== $file && '..' !== $file ) {
				$path = $dir . '/' . $file;
				if ( is_dir( $path ) ) {
					$this->remove_dir_recursively( $path );
				} else {
					unlink( $path );
				}
			}
		}
		rmdir( $dir );
	}

	/**
	 * Tests path generation for post cards includes template hash.
	 *
	 * @return void
	 */
	public function test_path_for_post_includes_template_hash(): void {
		$store = new CardStore( '/uploads' );
		$path  = $store->path( CardKey::for_post( 123 ), Template::default(), 'landscape' );
		$hash  = Template::default()->hash();
		$this->assertSame( '/uploads/og-cards/post-123-' . $hash . '-landscape.png', $path );
	}

	/**
	 * Tests path generation for archive cards includes taxonomy and term ID.
	 *
	 * @return void
	 */
	public function test_path_for_archive_includes_taxonomy(): void {
		$store = new CardStore( '/uploads' );
		$path  = $store->path( CardKey::for_archive( 'category', 7 ), Template::default(), 'landscape' );
		$hash  = Template::default()->hash();
		$this->assertSame( '/uploads/og-cards/archive/category-7-' . $hash . '-landscape.png', $path );
	}

	/**
	 * Tests path generation for author cards.
	 *
	 * @return void
	 */
	public function test_path_for_author(): void {
		$store = new CardStore( '/uploads' );
		$path  = $store->path( CardKey::for_author( 5 ), Template::default(), 'landscape' );
		$hash  = Template::default()->hash();
		$this->assertSame( '/uploads/og-cards/author/5-' . $hash . '-landscape.png', $path );
	}

	/**
	 * Tests write() creates file with provided bytes.
	 *
	 * @return void
	 */
	public function test_write_creates_file_with_bytes(): void {
		$store = new CardStore( $this->base );
		$path  = $store->write( CardKey::for_post( 1 ), Template::default(), 'landscape', 'PNGBYTES' );
		$this->assertFileExists( $path );
		$this->assertSame( 'PNGBYTES', file_get_contents( $path ) );
	}

	/**
	 * Tests write() creates directory if missing.
	 *
	 * @return void
	 */
	public function test_write_creates_directory_if_missing(): void {
		$store = new CardStore( $this->base );
		$path  = $store->write( CardKey::for_archive( 'tag', 9 ), Template::default(), 'landscape', 'X' );
		$this->assertDirectoryExists( dirname( $path ) );
	}

	/**
	 * Tests write() overwrites existing file.
	 *
	 * @return void
	 */
	public function test_write_overwrites_existing(): void {
		$store = new CardStore( $this->base );
		$store->write( CardKey::for_post( 1 ), Template::default(), 'landscape', 'OLD' );
		$path = $store->write( CardKey::for_post( 1 ), Template::default(), 'landscape', 'NEW' );
		$this->assertSame( 'NEW', file_get_contents( $path ) );
	}

	/**
	 * Tests url() returns null when file missing.
	 *
	 * @return void
	 */
	public function test_url_returns_null_when_file_missing(): void {
		$store = new CardStore( $this->base, 'https://example.test/uploads' );
		$this->assertNull( $store->url( CardKey::for_post( 999 ), Template::default(), 'landscape' ) );
	}

	/**
	 * Tests url() returns public URL when file exists.
	 *
	 * @return void
	 */
	public function test_url_returns_public_url_when_file_exists(): void {
		$store = new CardStore( $this->base, 'https://example.test/uploads' );
		$store->write( CardKey::for_post( 1 ), Template::default(), 'landscape', 'X' );
		$hash = Template::default()->hash();
		$this->assertSame(
			"https://example.test/uploads/og-cards/post-1-{$hash}-landscape.png",
			$store->url( CardKey::for_post( 1 ), Template::default(), 'landscape' )
		);
	}

	/**
	 * Tests missing_post_ids() returns published posts without cards.
	 *
	 * @return void
	 */
	public function test_missing_post_ids_returns_published_without_card(): void {
		Functions\when( 'get_posts' )->justReturn( [ 1, 2, 3 ] );
		$store = new CardStore( $this->base );
		$store->write( CardKey::for_post( 2 ), Template::default(), 'landscape', 'X' );
		$missing = $store->missing_post_ids( Template::default(), 5 );
		$this->assertSame( [ 1, 3 ], $missing );
	}

	/**
	 * Tests delete_for_key removes all versions of a card.
	 *
	 * @return void
	 */
	public function test_delete_for_key_removes_all_versions(): void {
		$store = new CardStore( $this->base );
		$t1    = Template::default();
		$t2    = $t1->with( [ 'bg_color' => '#ff0000' ] );
		$store->write( CardKey::for_post( 1 ), $t1, 'landscape', 'A' );
		$store->write( CardKey::for_post( 1 ), $t2, 'landscape', 'B' );
		$store->delete_for_key( CardKey::for_post( 1 ) );
		$this->assertFalse( $store->exists( CardKey::for_post( 1 ), $t1, 'landscape' ) );
		$this->assertFalse( $store->exists( CardKey::for_post( 1 ), $t2, 'landscape' ) );
	}

	/**
	 * Tests purge_all removes directory contents.
	 *
	 * @return void
	 */
	public function test_purge_all_removes_directory_contents(): void {
		$store = new CardStore( $this->base );
		$store->write( CardKey::for_post( 1 ), Template::default(), 'landscape', 'A' );
		$store->purge_all();
		$this->assertFalse( $store->exists( CardKey::for_post( 1 ), Template::default(), 'landscape' ) );
	}
}
