<?php
/**
 * WP function polyfills for unit tests.
 *
 * This file MUST be required after Patchwork has registered its stream wrapper
 * so that Patchwork can redeclare these functions per-test via Brain Monkey.
 *
 * @package EvzenLeonenko\OpenGraphControl\Tests
 */

declare(strict_types=1);

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	/**
	 * Strips all HTML tags including script and style.
	 *
	 * @param mixed $text          The input string.
	 * @param bool  $remove_breaks Whether to remove line breaks and tabs.
	 * @return string Sanitised string.
	 */
	function wp_strip_all_tags( $text, $remove_breaks = false ) {
		$value = strip_tags( (string) $text ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- this IS the polyfill
		if ( $remove_breaks ) {
			$value = (string) preg_replace( '/[\r\n\t ]+/', ' ', $value );
		}
		return trim( $value );
	}
}
