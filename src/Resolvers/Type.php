<?php
/**
 * OG type resolver.
 *
 * @package EvzenLeonenko\OpenGraphControl
 */

declare(strict_types=1);

namespace EvzenLeonenko\OpenGraphControl\Resolvers;

use EvzenLeonenko\OpenGraphControl\Options\Repository as OptionsRepository;
use EvzenLeonenko\OpenGraphControl\PostMeta\Repository as PostMetaRepository;

/**
 * Returns the og:type value: post override, per-post-type default, or 'website'.
 */
final class Type implements ResolverInterface {

	public function __construct(
		private PostMetaRepository $postmeta,
		private OptionsRepository $options
	) {}

	public function resolve( Context $context ): ?string {
		$value = $this->compute( $context );
		/** @var string $filtered */
		$filtered = apply_filters( 'ogc_resolve_type_value', $value, $context );
		return $filtered;
	}

	private function compute( Context $context ): string {
		$post_id = $context->post_id();

		if ( null !== $post_id ) {
			$override = $this->postmeta->get( $post_id )['type'];
			if ( '' !== $override ) {
				return $override;
			}

			$post_type = get_post_type( $post_id );
			if ( is_string( $post_type ) && '' !== $post_type ) {
				$default = $this->options->get_path( 'post_types.' . $post_type . '.default_type' );
				if ( is_string( $default ) && '' !== $default ) {
					return $default;
				}
			}
		}

		if ( $context->is_singular() ) {
			return 'article';
		}

		$site_default = $this->options->get_path( 'site.type' );
		return is_string( $site_default ) && '' !== $site_default ? $site_default : 'website';
	}
}
