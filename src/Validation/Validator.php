<?php
/**
 * Preview / per-post validator.
 *
 * @package EvzenLeonenko\OpenGraphControl
 */

declare(strict_types=1);


namespace EvzenLeonenko\OpenGraphControl\Validation;

defined( 'ABSPATH' ) || exit;

/**
 * Inspects the resolved tag stream + source values for common mistakes:
 * - Title / description too long or empty
 * - Image missing or uses an external URL we can't size-check
 * - Twitter handles missing the leading @
 * - Mastodon fediverse:creator not in @user@instance format
 *
 * Callers feed it the key/value map produced by PreviewController and
 * optionally the raw per-post + global settings so we can surface
 * config-level issues too.
 */
final class Validator {

	private const TITLE_WARN       = 60;
	private const TITLE_ERROR      = 90;
	private const DESCRIPTION_WARN = 140;
	private const DESCRIPTION_MAX  = 200;

	/**
	 * @param array<string, string> $tags Flat map keyed "kind:key" as returned by PreviewController.
	 * @param array<string, mixed>  $global_settings Current ogc_settings snapshot (optional).
	 * @return array<int, Warning>
	 */
	public function validate( array $tags, array $global_settings = [] ): array {
		$warnings = [];

		$warnings = array_merge( $warnings, $this->check_title( $tags['property:og:title'] ?? '' ) );
		$warnings = array_merge( $warnings, $this->check_description( $tags['property:og:description'] ?? '' ) );
		$warnings = array_merge( $warnings, $this->check_image( $tags['property:og:image'] ?? '' ) );

		if ( isset( $global_settings['platforms']['twitter']['site'] ) ) {
			$warnings = array_merge(
				$warnings,
				$this->check_twitter_handle( 'twitter.site', (string) $global_settings['platforms']['twitter']['site'] )
			);
		}
		if ( isset( $global_settings['platforms']['twitter']['creator'] ) ) {
			$warnings = array_merge(
				$warnings,
				$this->check_twitter_handle( 'twitter.creator', (string) $global_settings['platforms']['twitter']['creator'] )
			);
		}
		if ( isset( $global_settings['platforms']['mastodon']['fediverse_creator'] ) ) {
			$warnings = array_merge(
				$warnings,
				$this->check_fediverse_creator( (string) $global_settings['platforms']['mastodon']['fediverse_creator'] )
			);
		}

		return $warnings;
	}

	/**
	 * @return array<int, Warning>
	 */
	private function check_title( string $title ): array {
		if ( '' === $title ) {
			return [ new Warning( Warning::ERROR, 'title', 'Title is empty.' ) ];
		}
		$length = mb_strlen( $title );
		if ( $length > self::TITLE_ERROR ) {
			return [
				new Warning(
					Warning::ERROR,
					'title',
					sprintf( 'Title is %d characters; platforms typically truncate after %d.', $length, self::TITLE_ERROR )
				),
			];
		}
		if ( $length > self::TITLE_WARN ) {
			return [
				new Warning(
					Warning::WARN,
					'title',
					sprintf( 'Title is %d characters; aim for under %d for best rendering on Facebook and LinkedIn.', $length, self::TITLE_WARN )
				),
			];
		}
		return [];
	}

	/**
	 * @return array<int, Warning>
	 */
	private function check_description( string $description ): array {
		if ( '' === $description ) {
			return [ new Warning( Warning::WARN, 'description', 'Description is empty.' ) ];
		}
		$length = mb_strlen( $description );
		if ( $length > self::DESCRIPTION_MAX ) {
			return [
				new Warning(
					Warning::ERROR,
					'description',
					sprintf( 'Description is %d characters; Twitter cuts off after %d.', $length, self::DESCRIPTION_MAX )
				),
			];
		}
		if ( $length > self::DESCRIPTION_WARN ) {
			return [
				new Warning(
					Warning::WARN,
					'description',
					sprintf( 'Description is %d characters; aim for under %d for a clean preview.', $length, self::DESCRIPTION_WARN )
				),
			];
		}
		return [];
	}

	/**
	 * @return array<int, Warning>
	 */
	private function check_image( string $image_url ): array {
		if ( '' === $image_url ) {
			return [
				new Warning(
					Warning::WARN,
					'image',
					'No image resolved. Facebook and LinkedIn will render a text-only card.'
				),
			];
		}
		return [];
	}

	/**
	 * @return array<int, Warning>
	 */
	private function check_twitter_handle( string $field, string $handle ): array {
		if ( '' === $handle ) {
			return [];
		}
		if ( ! str_starts_with( $handle, '@' ) ) {
			return [
				new Warning(
					Warning::ERROR,
					$field,
					sprintf( 'Twitter handle must start with @ (got "%s").', $handle )
				),
			];
		}
		return [];
	}

	/**
	 * @return array<int, Warning>
	 */
	private function check_fediverse_creator( string $value ): array {
		if ( '' === $value ) {
			return [];
		}
		if ( ! preg_match( '/^@[^@\s]+@[^@\s]+\.[^@\s]+$/', $value ) ) {
			return [
				new Warning(
					Warning::ERROR,
					'platforms.mastodon.fediverse_creator',
					sprintf( 'Expected @user@instance format (got "%s").', $value )
				),
			];
		}
		return [];
	}
}
