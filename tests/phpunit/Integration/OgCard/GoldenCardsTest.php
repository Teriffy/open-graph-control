<?php
declare(strict_types=1);

namespace EvzenLeonenko\OpenGraphControl\Tests\Integration\OgCard;

use EvzenLeonenko\OpenGraphControl\OgCard\{FontProvider, GdRenderer, Payload, Template};
use PHPUnit\Framework\TestCase;

/**
 * Visual regression — renders cards and compares to pre-baked fixtures.
 *
 * If GD/FreeType metrics drift more than the 10% tolerance, these tests fail
 * and golden fixtures should be re-baked via `bin/regen-goldens.sh`.
 *
 * @group golden
 */
final class GoldenCardsTest extends TestCase {

	private const TOLERANCE_PCT = 0.10; // 10% pixel diff tolerance for cross-distro FreeType drift

	protected function setUp(): void {
		if ( ! extension_loaded( 'gd' ) ) {
			$this->markTestSkipped( 'GD required' );
		}
	}

	/**
	 * @dataProvider goldenConfigs
	 */
	public function test_rendered_card_matches_golden( string $id, array $config ): void {
		$golden_path = __DIR__ . '/../../../fixtures/expected-cards/' . $id . '.png';
		if ( ! file_exists( $golden_path ) ) {
			$this->markTestSkipped( "Golden missing: {$id}" );
		}

		$renderer = new GdRenderer( new FontProvider() );
		$payload  = new Payload(
			'How to ship a WordPress plugin in 2026',
			'A practical guide to wp.org submission and maintenance',
			'example.com',
			'https://example.com',
			'example.com · April 2026'
		);
		$template = Template::from_array( $config );
		$actual   = $renderer->render( $template, $payload );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- test fixture, no WP context.
		$expected_img = imagecreatefromstring( (string) file_get_contents( $golden_path ) );
		$actual_img   = imagecreatefromstring( $actual );

		$this->assertSame( imagesx( $expected_img ), imagesx( $actual_img ), "Width mismatch for {$id}" );
		$this->assertSame( imagesy( $expected_img ), imagesy( $actual_img ), "Height mismatch for {$id}" );

		$w         = imagesx( $actual_img );
		$h         = imagesy( $actual_img );
		$total     = $w * $h;
		$diff      = 0;
		$threshold = 30; // per-channel diff threshold

		for ( $y = 0; $y < $h; $y += 3 ) { // stride sampling — every 3rd pixel for speed
			for ( $x = 0; $x < $w; $x += 3 ) {
				$a  = imagecolorat( $actual_img, $x, $y );
				$e  = imagecolorat( $expected_img, $x, $y );
				$ar = ( $a >> 16 ) & 0xFF;
				$ag = ( $a >> 8 ) & 0xFF;
				$ab = $a & 0xFF;
				$er = ( $e >> 16 ) & 0xFF;
				$eg = ( $e >> 8 ) & 0xFF;
				$eb = $e & 0xFF;
				if ( abs( $ar - $er ) > $threshold || abs( $ag - $eg ) > $threshold || abs( $ab - $eb ) > $threshold ) {
					++$diff;
				}
			}
		}

		$sampled    = intdiv( $total, 9 ); // stride 3×3 = 1/9 sample
		$diff_ratio = $diff / $sampled;
		$this->assertLessThan( self::TOLERANCE_PCT, $diff_ratio, sprintf( 'Golden %s differs by %.1f%% (threshold %.1f%%)', $id, $diff_ratio * 100, self::TOLERANCE_PCT * 100 ) );
	}

	/**
	 * @return array<string, array{string, array<string, mixed>}>
	 */
	public static function goldenConfigs(): array {
		$path = __DIR__ . '/../../../fixtures/template-configs.json';
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- test fixture, no WP context.
		$configs = json_decode( (string) file_get_contents( $path ), true );
		$out     = [];
		foreach ( (array) $configs as $id => $config ) {
			$out[ $id ] = [ (string) $id, (array) $config ];
		}
		return $out;
	}
}
