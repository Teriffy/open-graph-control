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

	private const TITLE_WARN          = 60;
	private const TITLE_ERROR         = 90;
	private const DESCRIPTION_WARN    = 140;
	private const DESCRIPTION_MAX     = 200;
	private const DISCORD_TITLE_MAX   = 256;
	private const DISCORD_DESC_MAX    = 2048;
	private const LINKEDIN_MIN_WIDTH  = 1200;
	private const LINKEDIN_MIN_HEIGHT = 627;

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
		$warnings = array_merge(
			$warnings,
			$this->check_discord_limits(
				(string) ( $tags['property:og:title'] ?? '' ),
				(string) ( $tags['property:og:description'] ?? '' ),
				$global_settings
			)
		);
		$warnings = array_merge(
			$warnings,
			$this->check_linkedin_image( $tags, $global_settings )
		);
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
	 * @param array<string, mixed> $global_settings
	 * @return array<int, Warning>
	 */
	private function check_discord_limits( string $title, string $description, array $global_settings ): array {
		if ( empty( $global_settings['platforms']['discord']['enabled'] ) ) {
			return [];
		}
		$out = [];
		if ( mb_strlen( $title ) > self::DISCORD_TITLE_MAX ) {
			$out[] = new Warning(
				Warning::WARN,
				'platforms.discord.title',
				sprintf( 'Title is %d characters; Discord truncates the embed title at %d.', mb_strlen( $title ), self::DISCORD_TITLE_MAX )
			);
		}
		if ( mb_strlen( $description ) > self::DISCORD_DESC_MAX ) {
			$out[] = new Warning(
				Warning::WARN,
				'platforms.discord.description',
				sprintf( 'Description is %d characters; Discord truncates the embed description at %d.', mb_strlen( $description ), self::DISCORD_DESC_MAX )
			);
		}
		return $out;
	}

	/**
	 * @param array<string, string> $tags
	 * @param array<string, mixed>  $global_settings
	 * @return array<int, Warning>
	 */
	private function check_linkedin_image( array $tags, array $global_settings ): array {
		if ( empty( $global_settings['platforms']['linkedin']['enabled'] ) ) {
			return [];
		}
		$width  = isset( $tags['property:og:image:width'] ) ? (int) $tags['property:og:image:width'] : 0;
		$height = isset( $tags['property:og:image:height'] ) ? (int) $tags['property:og:image:height'] : 0;
		if ( $width <= 0 || $height <= 0 ) {
			return [];
		}
		if ( $width < self::LINKEDIN_MIN_WIDTH || $height < self::LINKEDIN_MIN_HEIGHT ) {
			return [
				new Warning(
					Warning::WARN,
					'platforms.linkedin.image',
					sprintf(
						'Image is %d×%d; LinkedIn requires at least %d×%d for a large preview.',
						$width,
						$height,
						self::LINKEDIN_MIN_WIDTH,
						self::LINKEDIN_MIN_HEIGHT
					)
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
