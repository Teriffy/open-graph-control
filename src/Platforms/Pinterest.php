<?php
/**
 * Pinterest platform.
 *
 * @package EvzenLeonenko\OpenGraphControl
 */

declare(strict_types=1);

namespace EvzenLeonenko\OpenGraphControl\Platforms;

use EvzenLeonenko\OpenGraphControl\Images\SizeRegistry;
use EvzenLeonenko\OpenGraphControl\Renderer\Tag;
use EvzenLeonenko\OpenGraphControl\Resolvers\Context;

/**
 * Pinterest reads og:* for basic cards and a schema.org JSON-LD payload
 * (Article / Product / Recipe) for Rich Pins. The pin type comes from
 * platforms.pinterest.rich_pins_type, or an ogc_pinterest_rich_pins_type
 * filter override.
 */
final class Pinterest extends AbstractPlatform {

	public function slug(): string {
		return 'pinterest';
	}

	/**
	 * @return Tag[]
	 */
	public function tags( Context $context ): array {
		return [];
	}

	public function json_ld( Context $context ): ?string {
		if ( ! $context->is_singular() || null === $context->post_id() ) {
			return null;
		}

		$type = (string) $this->options->get_path( 'platforms.pinterest.rich_pins_type' );
		if ( '' === $type ) {
			$type = 'article';
		}
		/** @var string $type */
		$type = apply_filters( 'ogc_pinterest_rich_pins_type', $type, $context );

		$payload = match ( $type ) {
			'product' => $this->product_schema( $context ),
			'recipe'  => $this->recipe_schema( $context ),
			default   => $this->article_schema( $context ),
		};

		/** @var array<string, mixed> $payload */
		$payload = apply_filters( 'ogc_pinterest_rich_pin_payload', $payload, $type, $context );

		$encoded = wp_json_encode( $payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		return false === $encoded ? null : $encoded;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function article_schema( Context $context ): array {
		$post_id = (int) $context->post_id();
		$post    = get_post( $post_id );

		$payload = [
			'@context'    => 'https://schema.org',
			'@type'       => 'Article',
			'headline'    => (string) $this->title->resolve( $context ),
			'description' => (string) $this->description->resolve( $context ),
			'url'         => (string) $this->url->resolve( $context ),
		];

		$image_url = $this->image_url( $context );
		if ( null !== $image_url ) {
			$payload['image'] = $image_url;
		}

		if ( null !== $post ) {
			$published = (string) mysql2date( 'c', (string) $post->post_date_gmt );
			if ( '' !== $published ) {
				$payload['datePublished'] = $published;
			}
			$author_name = (string) get_the_author_meta( 'display_name', (int) $post->post_author );
			if ( '' !== $author_name ) {
				$payload['author'] = [
					'@type' => 'Person',
					'name'  => $author_name,
				];
			}
		}

		return $payload;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function product_schema( Context $context ): array {
		$payload   = [
			'@context'    => 'https://schema.org',
			'@type'       => 'Product',
			'name'        => (string) $this->title->resolve( $context ),
			'description' => (string) $this->description->resolve( $context ),
			'url'         => (string) $this->url->resolve( $context ),
		];
		$image_url = $this->image_url( $context );
		if ( null !== $image_url ) {
			$payload['image'] = $image_url;
		}
		return $payload;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function recipe_schema( Context $context ): array {
		$payload   = [
			'@context'    => 'https://schema.org',
			'@type'       => 'Recipe',
			'name'        => (string) $this->title->resolve( $context ),
			'description' => (string) $this->description->resolve( $context ),
			'url'         => (string) $this->url->resolve( $context ),
		];
		$image_url = $this->image_url( $context );
		if ( null !== $image_url ) {
			$payload['image'] = $image_url;
		}
		return $payload;
	}

	private function image_url( Context $context ): ?string {
		$reference = $this->image->resolve( $context );
		if ( null === $reference || '' === $reference ) {
			return null;
		}
		if ( ! ctype_digit( $reference ) ) {
			return $reference;
		}
		$url = wp_get_attachment_image_url( (int) $reference, SizeRegistry::PINTEREST );
		return is_string( $url ) && '' !== $url ? $url : null;
	}
}
