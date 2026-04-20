<?php
/**
 * Payload value object for OG card rendering.
 *
 * @package EvzenLeonenko\OpenGraphControl
 */

declare(strict_types=1);

namespace EvzenLeonenko\OpenGraphControl\OgCard;

defined( 'ABSPATH' ) || exit;

/**
 * Immutable value object representing OG card payload data.
 *
 * Encapsulates the dynamic content (title, description, meta line) rendered on
 * OG cards. Automatically sanitizes HTML and collapses whitespace on construction,
 * ensuring clean data for template rendering. Provides array serialization for
 * persistence and round-trip conversion.
 */
final class Payload {

	public readonly string $title;
	public readonly string $description;

	/**
	 * Creates a new Payload instance.
	 *
	 * @param string $title       The OG card title. HTML stripped and whitespace collapsed.
	 * @param string $description The OG card description. HTML stripped and whitespace collapsed.
	 * @param string $site_name   The site name to display on the card.
	 * @param string $url         The canonical URL for the card.
	 * @param string $meta_line   The meta information line (e.g., publish date, author).
	 *
	 * @throws \InvalidArgumentException When title is empty after sanitization.
	 */
	public function __construct(
		string $title,
		string $description,
		public readonly string $site_name,
		public readonly string $url,
		public readonly string $meta_line,
	) {
		$clean_title = self::clean( $title );
		if ( '' === $clean_title ) {
			throw new \InvalidArgumentException( 'Payload title cannot be empty' );
		}
		$this->title       = $clean_title;
		$this->description = self::clean( $description );
	}

	/**
	 * Sanitizes a string by stripping HTML tags and collapsing whitespace.
	 *
	 * @param string $value The string to clean.
	 *
	 * @return string The cleaned string.
	 */
	private static function clean( string $value ): string {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- Fallback for non-WP context (unit tests with Brain Monkey).
		$stripped  = function_exists( 'wp_strip_all_tags' ) ? wp_strip_all_tags( $value ) : strip_tags( $value );
		$collapsed = preg_replace( '/\s+/u', ' ', $stripped );
		return trim( (string) $collapsed );
	}

	/**
	 * Converts the payload to an associative array.
	 *
	 * @return array<string,string>
	 */
	public function to_array(): array {
		return [
			'title'       => $this->title,
			'description' => $this->description,
			'site_name'   => $this->site_name,
			'url'         => $this->url,
			'meta_line'   => $this->meta_line,
		];
	}
}
