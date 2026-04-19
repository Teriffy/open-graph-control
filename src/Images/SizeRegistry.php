<?php
/**
 * OG image size registration.
 *
 * @package EvzenLeonenko\OpenGraphControl
 */

declare(strict_types=1);


namespace EvzenLeonenko\OpenGraphControl\Images;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the three platform-optimized image sizes via add_image_size.
 *
 * Sizes are hard-cropped (center) for predictable dimensions across a
 * heterogeneous media library. Users who want a different crop can
 * upload a per-platform override (Phase 11).
 */
final class SizeRegistry {

	public const LANDSCAPE = 'ogc_landscape';
	public const SQUARE    = 'ogc_square';
	public const PINTEREST = 'ogc_pinterest';

	public function register(): void {
		add_image_size( self::LANDSCAPE, 1200, 630, true );
		add_image_size( self::SQUARE, 600, 600, true );
		add_image_size( self::PINTEREST, 1000, 1500, true );
	}
}
