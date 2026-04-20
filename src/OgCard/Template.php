<?php
/**
 * Template value object for OG card rendering.
 *
 * @package EvzenLeonenko\OpenGraphControl
 */

declare(strict_types=1);

namespace EvzenLeonenko\OpenGraphControl\OgCard;

defined( 'ABSPATH' ) || exit;

/**
 * Immutable value object representing OG card template configuration.
 *
 * Manages rendering properties including background type/colors, text colors,
 * logo/site name display, and meta information. Provides stable hashing for
 * cache key generation and array serialization for persistence.
 */
final class Template {

	private const HEX_REGEX = '/^#[0-9a-f]{6}$/i';

	/**
	 * Creates a new Template instance.
	 *
	 * @param string $bg_type        Background type: 'gradient', 'solid', or 'image'.
	 * @param string $bg_color       Background color in hex format.
	 * @param string $bg_gradient_to Gradient end color in hex format.
	 * @param int    $bg_image_id    Attachment ID for image background.
	 * @param string $text_color     Text color in hex format.
	 * @param int    $logo_id        Attachment ID for logo.
	 * @param bool   $show_site_name Whether to display site name on card.
	 * @param bool   $show_meta_line Whether to display meta information line.
	 *
	 * @throws \InvalidArgumentException When invalid hex color or bg_type provided.
	 */
	public function __construct(
		public readonly string $bg_type = 'gradient',
		public readonly string $bg_color = '#1e40af',
		public readonly string $bg_gradient_to = '#3b82f6',
		public readonly int $bg_image_id = 0,
		public readonly string $text_color = '#ffffff',
		public readonly int $logo_id = 0,
		public readonly bool $show_site_name = true,
		public readonly bool $show_meta_line = true,
	) {
		$this->validate();
	}

	/**
	 * Returns a template with default values.
	 *
	 * @return self
	 */
	public static function default(): self {
		return new self();
	}

	/**
	 * Creates a Template from an associative array.
	 *
	 * @param array<string,mixed> $data Configuration array. Missing keys use defaults.
	 *
	 * @return self
	 * @throws \InvalidArgumentException When invalid hex color or bg_type provided.
	 */
	public static function from_array( array $data ): self {
		$defaults = ( new self() )->to_array();
		$merged   = array_merge( $defaults, $data );
		return new self(
			(string) $merged['bg_type'],
			(string) $merged['bg_color'],
			(string) $merged['bg_gradient_to'],
			(int) $merged['bg_image_id'],
			(string) $merged['text_color'],
			(int) $merged['logo_id'],
			(bool) $merged['show_site_name'],
			(bool) $merged['show_meta_line'],
		);
	}

	/**
	 * Returns a new Template with updated properties.
	 *
	 * @param array<string,mixed> $changes Properties to override.
	 *
	 * @return self
	 * @throws \InvalidArgumentException When invalid hex color or bg_type provided.
	 */
	public function with( array $changes ): self {
		return self::from_array( array_merge( $this->to_array(), $changes ) );
	}

	/**
	 * Converts the template to an associative array.
	 *
	 * Note: key order is normalized via ksort() in hash(), so callers can rely on any order.
	 *
	 * @return array<string,mixed>
	 */
	public function to_array(): array {
		return [
			'bg_type'        => $this->bg_type,
			'bg_color'       => $this->bg_color,
			'bg_gradient_to' => $this->bg_gradient_to,
			'bg_image_id'    => $this->bg_image_id,
			'text_color'     => $this->text_color,
			'logo_id'        => $this->logo_id,
			'show_site_name' => $this->show_site_name,
			'show_meta_line' => $this->show_meta_line,
		];
	}

	/**
	 * Generates a stable 8-character hex hash of the template configuration.
	 *
	 * @return string 8-character lowercase hex string.
	 */
	public function hash(): string {
		$data = $this->to_array();
		ksort( $data );
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- safe: only typed scalar values from to_array(), no objects/closures.
		return substr( md5( serialize( $data ) ), 0, 8 );
	}

	/**
	 * Validates template properties.
	 *
	 * @return void
	 * @throws \InvalidArgumentException When validation fails.
	 */
	private function validate(): void {
		if ( ! in_array( $this->bg_type, [ 'gradient', 'solid', 'image' ], true ) ) {
			throw new \InvalidArgumentException( 'Invalid bg_type: ' . $this->bg_type );
		}
		foreach ( [ 'bg_color', 'bg_gradient_to', 'text_color' ] as $field ) {
			if ( ! preg_match( self::HEX_REGEX, $this->{$field} ) ) {
				throw new \InvalidArgumentException( "Invalid hex for {$field}: " . $this->{$field} );
			}
		}
	}
}
