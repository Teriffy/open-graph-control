<?php
/**
 * Wp_head output pipeline.
 *
 * @package EvzenLeonenko\OpenGraphControl
 */

declare(strict_types=1);


namespace EvzenLeonenko\OpenGraphControl\Renderer;

defined( 'ABSPATH' ) || exit;

use EvzenLeonenko\OpenGraphControl\Options\Repository as OptionsRepository;
use EvzenLeonenko\OpenGraphControl\Platforms\Registry as PlatformRegistry;
use EvzenLeonenko\OpenGraphControl\Resolvers\Context;

/**
 * Detects the current request context, asks the PlatformRegistry for tags,
 * and echoes the resulting HTML inside <head>.
 *
 * Optional HTML comment markers wrap the output so site owners and support
 * engineers can see at a glance which plugin emitted which tags.
 */
final class Head {

	public function __construct(
		private PlatformRegistry $registry,
		private TagBuilder $builder,
		private OptionsRepository $options,
		private \EvzenLeonenko\OpenGraphControl\PostMeta\Repository $postmeta,
		private Cache $cache
	) {}

	public function register(): void {
		add_action( 'wp_head', [ $this, 'render' ], 1 );
	}

	public function render(): void {
		$context = $this->detect_context();
		if ( ! $this->is_context_enabled( $context ) ) {
			return;
		}
		if ( $this->is_post_excluded( $context ) ) {
			return;
		}

		$cached = $this->cache->get( $context );
		if ( null !== $cached ) {
			echo $cached; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- already-escaped cache payload.
			return;
		}

		$tags     = $this->registry->collect_tags( $context );
		$tag_html = $this->builder->render( $tags );
		$json_ld  = $this->registry->collect_json_ld( $context );

		if ( '' === trim( $tag_html ) && [] === $json_ld ) {
			return;
		}

		$markers = (bool) $this->options->get_path( 'output.comment_markers' );

		ob_start();
		if ( $markers ) {
			printf(
				"\n<!-- Open Graph Control v%s https://wordpress.org/plugins/open-graph-control/ -->\n",
				esc_html( (string) ( defined( 'OGC_VERSION' ) ? OGC_VERSION : '0.0.0' ) )
			);
		}
		if ( '' !== trim( $tag_html ) ) {
			echo $tag_html . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- TagBuilder escapes each tag.
		}
		foreach ( $json_ld as $payload ) {
			echo '<script type="application/ld+json">' . $payload . "</script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Payload is JSON encoded by platform class.
		}
		if ( $markers ) {
			echo "<!-- /Open Graph Control -->\n";
		}

		$output = (string) ob_get_clean();
		$this->cache->set( $context, $output );
		echo $output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Composed above from already-escaped pieces.
	}

	private function detect_context(): Context {
		if ( is_404() ) {
			return Context::for_404();
		}
		if ( is_search() ) {
			return Context::for_search();
		}
		if ( is_front_page() ) {
			return Context::for_front();
		}
		if ( is_home() ) {
			return Context::for_blog();
		}
		if ( is_singular() ) {
			$queried = get_queried_object();
			$post_id = isset( $queried->ID ) ? (int) $queried->ID : 0;
			return Context::for_post( $post_id );
		}
		if ( is_author() ) {
			$queried = get_queried_object();
			$user_id = isset( $queried->ID ) ? (int) $queried->ID : 0;
			return Context::for_author( $user_id );
		}
		if ( is_date() ) {
			return Context::for_date();
		}
		if ( is_archive() ) {
			$kind = is_category() ? 'category' : ( is_tag() ? 'tag' : ( is_tax() ? 'taxonomy' : 'post_type' ) );
			return Context::for_archive( $kind );
		}
		return Context::for_front();
	}

	private function is_post_excluded( Context $context ): bool {
		if ( ! $context->is_singular() || null === $context->post_id() ) {
			return false;
		}
		$meta = $this->postmeta->get( $context->post_id() );
		return in_array( 'all', $meta['exclude'], true );
	}

	private function is_context_enabled( Context $context ): bool {
		$map = [
			Context::TYPE_FRONT   => 'front',
			Context::TYPE_BLOG    => 'blog',
			Context::TYPE_ARCHIVE => 'archive',
			Context::TYPE_AUTHOR  => 'author',
			Context::TYPE_DATE    => 'archive',
			Context::TYPE_SEARCH  => 'search',
			Context::TYPE_404     => 'not_found',
		];
		$key = $map[ $context->type() ] ?? null;
		if ( null === $key ) {
			return true;
			// Singular always renders; gated only per-post via _ogc_meta exclude.
		}
		return (bool) $this->options->get_path( 'non_post_pages.' . $key . '.enabled' );
	}
}
