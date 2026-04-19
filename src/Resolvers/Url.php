<?php
/**
 * Canonical URL resolver.
 *
 * @package EvzenLeonenko\OpenGraphControl
 */

declare(strict_types=1);

namespace EvzenLeonenko\OpenGraphControl\Resolvers;

/**
 * Resolves og:url. Uses get_permalink for singulars, home_url with the
 * request URI otherwise.
 */
class Url implements ResolverInterface {

	public function resolve( Context $context ): ?string {
		$value = $this->compute( $context );
		/** @var string|null $filtered */
		$filtered = apply_filters( 'ogc_resolve_url_value', $value, $context );
		return $filtered;
	}

	private function compute( Context $context ): ?string {
		if ( $context->is_singular() && null !== $context->post_id() ) {
			$permalink = get_permalink( $context->post_id() );
			return is_string( $permalink ) && '' !== $permalink ? $permalink : null;
		}

		$request = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '/'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- URL is sanitized below via esc_url_raw.
		$url     = esc_url_raw( home_url( $request ) );
		return '' === $url ? null : $url;
	}
}
