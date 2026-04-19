<?php
/**
 * Locale resolver.
 *
 * @package EvzenLeonenko\OpenGraphControl
 */

declare(strict_types=1);

namespace EvzenLeonenko\OpenGraphControl\Resolvers;

use EvzenLeonenko\OpenGraphControl\Options\Repository as OptionsRepository;

/**
 * Resolves og:locale. Normalizes hyphen to underscore (OG wants en_US, not en-US).
 */
class Locale implements ResolverInterface {

	public function __construct(
		private OptionsRepository $options
	) {}

	public function resolve( Context $context ): ?string {
		$site_locale = $this->options->get_path( 'site.locale' );
		$candidate   = is_string( $site_locale ) && '' !== $site_locale ? $site_locale : (string) get_locale();

		$normalized = $this->normalize( $candidate );
		/** @var string|null $filtered */
		$filtered = apply_filters( 'ogc_resolve_locale_value', $normalized, $context );
		return $filtered;
	}

	private function normalize( string $locale ): ?string {
		$trim = trim( str_replace( '-', '_', $locale ) );
		return '' === $trim ? null : $trim;
	}
}
