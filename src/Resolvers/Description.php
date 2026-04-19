<?php
/**
 * Description resolver.
 *
 * @package EvzenLeonenko\OpenGraphControl
 */

declare(strict_types=1);

namespace EvzenLeonenko\OpenGraphControl\Resolvers;

use EvzenLeonenko\OpenGraphControl\Options\Repository as OptionsRepository;
use EvzenLeonenko\OpenGraphControl\PostMeta\Repository as PostMetaRepository;

/**
 * Walks the description fallback chain and returns the first non-empty value.
 *
 * Chain (by default): post_meta_override, seo_plugin_desc, post_excerpt,
 * post_content_trim (160-char trimmed body), site_description.
 */
final class Description implements ResolverInterface {

	private const TRIM_LENGTH = 160;

	public function __construct(
		private PostMetaRepository $postmeta,
		private OptionsRepository $options
	) {}

	public function resolve( Context $context ): ?string {
		$chain = $this->options->get_path( 'fallback_chains.description' );
		$chain = is_array( $chain ) ? $chain : [];
		/** @var array<int, string> $chain */
		$chain = apply_filters( 'ogc_resolve_description_chain', $chain, $context );

		foreach ( $chain as $step ) {
			$value = $this->step( (string) $step, $context );
			if ( null !== $value && '' !== $value ) {
				/** @var string $filtered */
				$filtered = apply_filters( 'ogc_resolve_description_value', $value, $context );
				return $filtered;
			}
		}
		return null;
	}

	private function step( string $step, Context $context ): ?string {
		return match ( $step ) {
			'post_meta_override' => $this->from_post_meta( $context ),
			'seo_plugin_desc'    => $this->from_seo_plugin( $context ),
			'post_excerpt'       => $context->is_singular() ? $this->nullable_string( get_the_excerpt( $context->post_id() ) ) : null,
			'post_content_trim'  => $this->from_post_content( $context ),
			'site_description'   => $this->nullable_string( get_bloginfo( 'description' ) ),
			default              => null,
		};
	}

	private function from_post_meta( Context $context ): ?string {
		$post_id = $context->post_id();
		if ( null === $post_id ) {
			return null;
		}
		$desc = $this->postmeta->get( $post_id )['description'];
		return '' === $desc ? null : $desc;
	}

	private function from_seo_plugin( Context $context ): ?string {
		/** @var string|null $value */
		$value = apply_filters( 'ogc_seo_plugin_desc', null, $context );
		return is_string( $value ) && '' !== $value ? $value : null;
	}

	private function from_post_content( Context $context ): ?string {
		if ( ! $context->is_singular() || null === $context->post_id() ) {
			return null;
		}
		$raw_content = get_post_field( 'post_content', $context->post_id() );
		$content     = is_string( $raw_content ) ? $raw_content : '';
		if ( '' === $content ) {
			return null;
		}
		$stripped = wp_strip_all_tags( strip_shortcodes( $content ), true );
		$collapse = preg_replace( '/\s+/', ' ', $stripped );
		$plain    = trim( is_string( $collapse ) ? $collapse : $stripped );
		if ( '' === $plain ) {
			return null;
		}
		if ( mb_strlen( $plain ) <= self::TRIM_LENGTH ) {
			return $plain;
		}
		return mb_substr( $plain, 0, self::TRIM_LENGTH ) . '…';
	}

	private function nullable_string( mixed $value ): ?string {
		$str = (string) $value;
		return '' === $str ? null : $str;
	}
}
