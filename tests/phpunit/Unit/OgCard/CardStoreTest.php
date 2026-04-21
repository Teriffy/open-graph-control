<?php
/**
 * CardStore path generation tests.
 *
 * @package EvzenLeonenko\OpenGraphControl
 */

declare(strict_types=1);

namespace EvzenLeonenko\OpenGraphControl\Tests\Unit\OgCard;

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
}
