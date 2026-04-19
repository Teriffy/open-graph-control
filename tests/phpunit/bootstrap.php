<?php
/**
 * PHPUnit bootstrap file.
 *
 * Provides WP escaping polyfills so TagBuilder and similar components can be
 * unit-tested without spinning up WordPress. Brain Monkey handles filters and
 * actions; these helpers handle the direct escaping helpers used at output time.
 *
 * @package EvzenLeonenko\OpenGraphControl\Tests
 */

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $url ) {
		$filtered = filter_var( (string) $url, FILTER_SANITIZE_URL );
		return false === $filtered ? '' : $filtered;
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}
