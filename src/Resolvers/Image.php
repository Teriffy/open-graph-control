<?php
/**
 * Image resolver.
 *
 * @package EvzenLeonenko\OpenGraphControl
 */

declare(strict_types=1);


namespace EvzenLeonenko\OpenGraphControl\Resolvers;

defined( 'ABSPATH' ) || exit;

use EvzenLeonenko\OpenGraphControl\ArchiveMeta\Repository as ArchiveMetaRepository;
use EvzenLeonenko\OpenGraphControl\Options\Repository as OptionsRepository;
use EvzenLeonenko\OpenGraphControl\PostMeta\Repository as PostMetaRepository;

/**
 * Walks the image fallback chain and returns either:
 *
 * - a numeric string of an attachment ID (preferred — platforms can then
 *   resolve to the right registered image size), or
 * - an absolute URL string when extracted from raw post content.
 *
 * Chain (by default): post_meta_override, archive_override, featured_image,
 * first_content_image, first_block_image, site_master_image.
 */
class Image implements ResolverInterface {

	public function __construct(
		private PostMetaRepository $postmeta,
		private OptionsRepository $options,
		private ArchiveMetaRepository $archive
	) {}

	public function resolve( Context $context ): ?string {
		$chain = $this->options->get_path( 'fallback_chains.image' );
		$chain = is_array( $chain ) ? $chain : [];
		/** @var array<int, string> $chain */
		$chain = apply_filters( 'ogc_resolve_image_chain', $chain, $context );

		foreach ( $chain as $step ) {
			$value = $this->step( (string) $step, $context );
			if ( null !== $value && '' !== $value ) {
				/** @var string $filtered */
				$filtered = apply_filters( 'ogc_resolve_image_value', $value, $context );
				return $filtered;
			}
		}
		return null;
	}

	private function step( string $step, Context $context ): ?string {
		return match ( $step ) {
			'post_meta_override'  => $this->from_post_meta( $context ),
			'archive_override'    => $this->from_archive_override( $context ),
			'featured_image'      => $this->from_featured( $context ),
			'first_content_image' => $this->from_content_img( $context ),
			'first_block_image'   => $this->from_block_image( $context ),
			'site_master_image'   => $this->from_site_master(),
			default               => null,
		};
	}

	private function from_post_meta( Context $context ): ?string {
		$post_id = $context->post_id();
		if ( null === $post_id ) {
			return null;
		}
		$id = $this->postmeta->get( $post_id )['image_id'];
		return $id > 0 ? (string) $id : null;
	}

	private function from_archive_override( Context $context ): ?string {
		if ( $context->is_author() ) {
			$user_id = $context->user_id();
			if ( null === $user_id || $user_id <= 0 ) {
				return null;
			}
			$id = $this->archive->get_for_user( $user_id )['image_id'];
			return $id > 0 ? (string) $id : null;
		}
		if ( $context->is_archive_term() ) {
			$term_id = $context->archive_term_id();
			if ( null === $term_id || $term_id <= 0 ) {
				return null;
			}
			$id = $this->archive->get_for_term( $term_id )['image_id'];
			return $id > 0 ? (string) $id : null;
		}
		return null;
	}

	private function from_featured( Context $context ): ?string {
		if ( ! $context->is_singular() || null === $context->post_id() ) {
			return null;
		}
		if ( ! has_post_thumbnail( $context->post_id() ) ) {
			return null;
		}
		$id = (int) get_post_thumbnail_id( $context->post_id() );
		return $id > 0 ? (string) $id : null;
	}

	private function from_content_img( Context $context ): ?string {
		$content = $this->post_content( $context );
		if ( null === $content ) {
			return null;
		}
		if ( ! preg_match( '/<img\b[^>]*\bsrc=["\']([^"\']+)["\']/i', $content, $match ) ) {
			return null;
		}
		// Reject javascript:/data:/vbscript: and any other non-allowlisted
		// scheme by routing through esc_url_raw, which returns an empty
		// string for URLs whose protocol isn't in wp_allowed_protocols().
		$safe = esc_url_raw( $match[1] );
		return '' === $safe ? null : $safe;
	}

	private function from_block_image( Context $context ): ?string {
		$content = $this->post_content( $context );
		if ( null === $content || ! has_blocks( $content ) ) {
			return null;
		}
		/** @var array<int, array<string, mixed>> $blocks */
		$blocks = parse_blocks( $content );
		$id     = $this->find_first_image_id( $blocks );
		return null === $id ? null : (string) $id;
	}

	/**
	 * @param array<int, array<string, mixed>> $blocks
	 */
	private function find_first_image_id( array $blocks ): ?int {
		foreach ( $blocks as $block ) {
			$name = is_string( $block['blockName'] ?? null ) ? $block['blockName'] : '';
			if ( 'core/image' === $name ) {
				$attrs = is_array( $block['attrs'] ?? null ) ? $block['attrs'] : [];
				$id    = isset( $attrs['id'] ) ? (int) $attrs['id'] : 0;
				if ( $id > 0 ) {
					return $id;
				}
			}
			$inner = is_array( $block['innerBlocks'] ?? null ) ? $block['innerBlocks'] : [];
			if ( [] !== $inner ) {
				$nested = $this->find_first_image_id( $inner );
				if ( null !== $nested ) {
					return $nested;
				}
			}
		}
		return null;
	}

	private function from_site_master(): ?string {
		$id = (int) $this->options->get_path( 'site.master_image_id' );
		return $id > 0 ? (string) $id : null;
	}

	private function post_content( Context $context ): ?string {
		if ( ! $context->is_singular() || null === $context->post_id() ) {
			return null;
		}
		$raw     = get_post_field( 'post_content', $context->post_id() );
		$content = is_string( $raw ) ? $raw : '';
		return '' === $content ? null : $content;
	}
}
