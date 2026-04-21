<?php
/**
 * Font provider for bundled Inter TTF font files.
 *
 * @package EvzenLeonenko\OpenGraphControl
 */

declare(strict_types=1);

namespace EvzenLeonenko\OpenGraphControl\OgCard;

defined( 'ABSPATH' ) || exit;

/**
 * Provides paths to bundled Inter font files.
 *
 * Manages access to font files bundled in the plugin assets directory,
 * resolving absolute paths for both production WordPress environments
 * (where OGC_DIR constant is defined) and unit testing contexts
 * (where fallback resolution from project root is used).
 */
final class FontProvider {

	/**
	 * Mapping of font weight names to their TTF file names.
	 */
	private const FONTS = [
		'regular' => 'Inter-Regular.ttf',
		'bold'    => 'Inter-Bold.ttf',
	];

	/**
	 * Resolves the absolute path to a font file by weight.
	 *
	 * @param string $weight The font weight name (e.g., 'regular', 'bold').
	 *
	 * @return string The absolute path to the TTF file.
	 *
	 * @throws \InvalidArgumentException When the weight is not recognized.
	 */
	public function path( string $weight ): string {
		if ( ! isset( self::FONTS[ $weight ] ) ) {
			throw new \InvalidArgumentException( 'Unknown weight: ' . $weight );
		}
		return defined( 'OGC_DIR' )
			? OGC_DIR . 'assets/fonts/Inter/' . self::FONTS[ $weight ]
			: dirname( __DIR__, 2 ) . '/assets/fonts/Inter/' . self::FONTS[ $weight ];
	}
}
