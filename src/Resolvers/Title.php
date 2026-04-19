<?php
/**
 * Title resolver.
 *
 * @package EvzenLeonenko\OpenGraphControl
 */

declare(strict_types=1);


namespace EvzenLeonenko\OpenGraphControl\Resolvers;

defined( 'ABSPATH' ) || exit;

use EvzenLeonenko\OpenGraphControl\Options\Repository as OptionsRepository;
use EvzenLeonenko\OpenGraphControl\PostMeta\Repository as PostMetaRepository;

/**
 * Walks the title fallback chain and returns the first non-empty value.
 *
 * Chain steps (by default): post_meta_override, seo_plugin_title, post_title, site_name.
 * SEO plugin integrations hook into 'ogc_seo_plugin_title' in Phase 15.
 */
class Title implements ResolverInterface {

	public function __construct(
		private PostMetaRepository $postmeta,
		private OptionsRepository $options
	) {}

	public function resolve( Context $context ): ?string {
		$chain = $this->options->get_path( 'fallback_chains.title' );
		$chain = is_array( $chain ) ? $chain : [];
		/** @var array<int, string> $chain */
		$chain = apply_filters( 'ogc_resolve_title_chain', $chain, $context );

		foreach ( $chain as $step ) {
			$value = $this->step( (string) $step, $context );
			if ( null !== $value && '' !== $value ) {
				/** @var string $filtered */
				$filtered = apply_filters( 'ogc_resolve_title_value', $value, $context );
				return $filtered;
			}
		}
		return null;
	}

	private function step( string $step, Context $context ): ?string {
		return match ( $step ) {
			'post_meta_override' => $this->from_post_meta( $context ),
			'seo_plugin_title'   => $this->from_seo_plugin( $context ),
			'post_title'         => $context->is_singular() && null !== $context->post_id() ? (string) get_the_title( $context->post_id() ) : null,
			'site_name'          => (string) get_bloginfo( 'name' ),
			default              => null,
		};
	}

	private function from_post_meta( Context $context ): ?string {
		$post_id = $context->post_id();
		if ( null === $post_id ) {
			return null;
		}
		$title = $this->postmeta->get( $post_id )['title'];
		return '' === $title ? null : $title;
	}

	private function from_seo_plugin( Context $context ): ?string {
		/** @var string|null $value */
		$value = apply_filters( 'ogc_seo_plugin_title', null, $context );
		return is_string( $value ) && '' !== $value ? $value : null;
	}
}
