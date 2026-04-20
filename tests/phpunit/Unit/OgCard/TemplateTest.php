<?php
declare(strict_types=1);

namespace EvzenLeonenko\OpenGraphControl\Tests\Unit\OgCard;

use EvzenLeonenko\OpenGraphControl\OgCard\Template;
use PHPUnit\Framework\TestCase;

final class TemplateTest extends TestCase {

	public function test_default_template_has_gradient_bg(): void {
		$t = Template::default();
		$this->assertSame( 'gradient', $t->bg_type );
		$this->assertSame( '#1e40af', $t->bg_color );
		$this->assertSame( '#3b82f6', $t->bg_gradient_to );
	}

	public function test_hash_is_stable_across_same_config(): void {
		$a = Template::default();
		$b = Template::default();
		$this->assertSame( $a->hash(), $b->hash() );
	}

	public function test_hash_changes_when_bg_color_changes(): void {
		$a = Template::default();
		$b = Template::default()->with( [ 'bg_color' => '#ff0000' ] );
		$this->assertNotSame( $a->hash(), $b->hash() );
	}

	public function test_hash_is_8_hex_chars(): void {
		$this->assertMatchesRegularExpression( '/^[0-9a-f]{8}$/', Template::default()->hash() );
	}

	public function test_from_array_rejects_invalid_hex(): void {
		$this->expectException( \InvalidArgumentException::class );
		Template::from_array( [ 'bg_color' => 'red' ] );
	}

	public function test_to_array_roundtrip(): void {
		$a = Template::default()->with(
			[
				'logo_id'        => 42,
				'show_meta_line' => false,
			]
		);
		$b = Template::from_array( $a->to_array() );
		$this->assertSame( $a->to_array(), $b->to_array() );
	}
}
