<?php
/**
 * Facebook platform.
 *
 * @package EvzenLeonenko\OpenGraphControl
 */

declare(strict_types=1);

namespace EvzenLeonenko\OpenGraphControl\Platforms;

use EvzenLeonenko\OpenGraphControl\Images\SizeRegistry;
use EvzenLeonenko\OpenGraphControl\Renderer\Tag;
use EvzenLeonenko\OpenGraphControl\Resolvers\Context;

/**
 * Emits the canonical og:* stream + fb:app_id + article:* tags.
 *
 * Most other OG-reading platforms (LinkedIn, Threads, WhatsApp, Telegram,
 * Slack, Bluesky, Discord) piggy-back on this output — their own classes
 * return an empty tag list for MVP.
 */
final class Facebook extends AbstractPlatform {

	public function slug(): string {
		return 'facebook';
	}

	/**
	 * @return Tag[]
	 */
	public function tags( Context $context ): array {
		$tags = [
			new Tag( Tag::KIND_PROPERTY, 'og:title', (string) $this->title->resolve( $context ) ),
			new Tag( Tag::KIND_PROPERTY, 'og:description', (string) $this->description->resolve( $context ) ),
			new Tag( Tag::KIND_PROPERTY, 'og:url', (string) $this->url->resolve( $context ) ),
			new Tag( Tag::KIND_PROPERTY, 'og:type', (string) $this->type->resolve( $context ) ),
			new Tag( Tag::KIND_PROPERTY, 'og:locale', (string) $this->locale->resolve( $context ) ),
			new Tag( Tag::KIND_PROPERTY, 'og:site_name', (string) $this->options->get_path( 'site.name' ) ),
		];

		foreach ( $this->image_tags( $context ) as $tag ) {
			$tags[] = $tag;
		}

		$fb_app_id = (string) $this->options->get_path( 'platforms.facebook.fb_app_id' );
		if ( '' !== $fb_app_id ) {
			$tags[] = new Tag( Tag::KIND_PROPERTY, 'fb:app_id', $fb_app_id );
		}

		$type = (string) $this->type->resolve( $context );
		if ( 'article' === $type && $context->is_singular() && null !== $context->post_id() ) {
			foreach ( $this->article_tags( $context->post_id() ) as $tag ) {
				$tags[] = $tag;
			}
		}

		return $tags;
	}

	/**
	 * @return Tag[]
	 */
	private function image_tags( Context $context ): array {
		$reference = $this->image->resolve( $context );
		if ( null === $reference || '' === $reference ) {
			return [];
		}

		if ( ! ctype_digit( $reference ) ) {
			return [ new Tag( Tag::KIND_PROPERTY, 'og:image', $reference ) ];
		}

		$id  = (int) $reference;
		$src = wp_get_attachment_image_src( $id, SizeRegistry::LANDSCAPE );
		if ( ! is_array( $src ) ) {
			return [];
		}

		$url    = (string) $src[0];
		$width  = (int) $src[1];
		$height = (int) $src[2];

		$tags = [
			new Tag( Tag::KIND_PROPERTY, 'og:image', $url ),
		];
		if ( $width > 0 ) {
			$tags[] = new Tag( Tag::KIND_PROPERTY, 'og:image:width', (string) $width );
		}
		if ( $height > 0 ) {
			$tags[] = new Tag( Tag::KIND_PROPERTY, 'og:image:height', (string) $height );
		}
		$alt = (string) get_post_meta( $id, '_wp_attachment_image_alt', true );
		if ( '' !== $alt ) {
			$tags[] = new Tag( Tag::KIND_PROPERTY, 'og:image:alt', $alt );
		}
		return $tags;
	}

	/**
	 * @return Tag[]
	 */
	private function article_tags( int $post_id ): array {
		$post = get_post( $post_id );
		if ( null === $post ) {
			return [];
		}

		$tags = [];

		$published = (string) mysql2date( 'c', (string) $post->post_date_gmt );
		$modified  = (string) mysql2date( 'c', (string) $post->post_modified_gmt );
		if ( '' !== $published ) {
			$tags[] = new Tag( Tag::KIND_PROPERTY, 'article:published_time', $published );
		}
		if ( '' !== $modified ) {
			$tags[] = new Tag( Tag::KIND_PROPERTY, 'article:modified_time', $modified );
		}

		$author_name = (string) get_the_author_meta( 'display_name', (int) $post->post_author );
		if ( '' !== $author_name ) {
			$tags[] = new Tag( Tag::KIND_PROPERTY, 'article:author', $author_name );
		}

		$categories = get_the_category( $post_id );
		if ( is_array( $categories ) && isset( $categories[0]->name ) ) {
			$section = (string) $categories[0]->name;
			if ( '' !== $section ) {
				$tags[] = new Tag( Tag::KIND_PROPERTY, 'article:section', $section );
			}
		}

		$post_tags = get_the_tags( $post_id );
		if ( is_array( $post_tags ) ) {
			foreach ( $post_tags as $term ) {
				$name = (string) $term->name;
				if ( '' !== $name ) {
					$tags[] = new Tag( Tag::KIND_PROPERTY, 'article:tag', $name );
				}
			}
		}

		return $tags;
	}
}
