<?php
/**
 * PHPStan bootstrap — declares plugin constants so static analysis
 * can reason about them without parsing the WordPress plugin loader.
 *
 * @package EvzenLeonenko\OpenGraphControl
 */

declare(strict_types=1);

if ( ! defined( 'OGC_VERSION' ) ) {
	define( 'OGC_VERSION', '0.0.1' );
}
if ( ! defined( 'OGC_FILE' ) ) {
	define( 'OGC_FILE', __FILE__ );
}
if ( ! defined( 'OGC_DIR' ) ) {
	define( 'OGC_DIR', __DIR__ . '/' );
}
if ( ! defined( 'OGC_URL' ) ) {
	define( 'OGC_URL', 'https://example.test/wp-content/plugins/open-graph-control/' );
}
