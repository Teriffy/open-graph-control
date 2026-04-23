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

if ( ! function_exists( 'wp_mkdir_p' ) ) {
	/**
	 * Polyfill for wp_mkdir_p — creates a directory recursively.
	 *
	 * @param string $target Directory path.
	 * @return bool True on success, false on failure.
	 */
	function wp_mkdir_p( $target ) {
		if ( is_dir( $target ) ) {
			return true;
		}
		return mkdir( $target, 0755, true ) || is_dir( $target );
	}
}

if ( ! function_exists( 'wp_delete_file' ) ) {
	/**
	 * Polyfill for wp_delete_file — deletes a file.
	 *
	 * @param string $file File path.
	 * @return void
	 */
	function wp_delete_file( $file ) {
		if ( is_file( $file ) ) {
			unlink( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- this IS the polyfill
		}
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	/**
	 * Polyfill for esc_html — escapes for HTML output.
	 *
	 * @param string $text Input string.
	 * @return string Escaped string.
	 */
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}
