<?php
/**
 * Generate golden PNG fixtures for image-diff regression.
 *
 * CLI script — runs outside WordPress; WP filesystem API unavailable.
 *
 * Usage: php tests/scripts/generate-goldens.php
 *
 * phpcs:disable WordPress.WP.AlternativeFunctions -- CLI only, no WP context.
 * phpcs:disable WordPress.Security.EscapeOutput   -- CLI stdout, no XSS risk.
 */

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/../../' );
}

use EvzenLeonenko\OpenGraphControl\OgCard\{FontProvider, GdRenderer, Payload, Template};

$configs_path = __DIR__ . '/../fixtures/template-configs.json';
$out_dir      = __DIR__ . '/../fixtures/expected-cards';

if ( ! is_dir( $out_dir ) ) {
	mkdir( $out_dir, 0755, true );
}

$configs = json_decode( (string) file_get_contents( $configs_path ), true );
if ( ! is_array( $configs ) ) {
	fwrite( STDERR, "Failed to parse configs\n" );
	exit( 1 );
}

$renderer = new GdRenderer( new FontProvider() );
$payload  = new Payload(
	'How to ship a WordPress plugin in 2026',
	'A practical guide to wp.org submission and maintenance',
	'example.com',
	'https://example.com',
	'example.com · April 2026'
);

foreach ( $configs as $name => $config ) {
	$template = Template::from_array( (array) $config );
	$bytes    = $renderer->render( $template, $payload );
	$file     = $out_dir . '/' . $name . '.png';
	file_put_contents( $file, $bytes );
	echo "Generated: {$file} (" . strlen( $bytes ) . " bytes)\n";
}

echo "Done.\n";
