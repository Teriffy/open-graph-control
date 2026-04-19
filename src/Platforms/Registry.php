<?php
/**
 * Platform registry.
 *
 * @package EvzenLeonenko\OpenGraphControl
 */

declare(strict_types=1);


namespace EvzenLeonenko\OpenGraphControl\Platforms;

defined( 'ABSPATH' ) || exit;

use EvzenLeonenko\OpenGraphControl\Renderer\Tag;
use EvzenLeonenko\OpenGraphControl\Resolvers\Context;

/**
 * Collects platforms and aggregates their outputs.
 *
 * Deduplication by kind+key lets multiple platforms safely emit the same
 * og:* tag (e.g. both Facebook and Pinterest want og:title) — the first
 * platform wins, keeping output stable.
 */
class Registry {

	/** @var array<string, PlatformInterface> */
	private array $platforms = [];

	public function register( PlatformInterface $platform ): void {
		$this->platforms[ $platform->slug() ] = $platform;
	}

	/**
	 * @return PlatformInterface[]
	 */
	public function all(): array {
		return array_values( $this->platforms );
	}

	/**
	 * @return PlatformInterface[]
	 */
	public function enabled(): array {
		return array_values(
			array_filter(
				$this->platforms,
				static fn ( PlatformInterface $p ) => $p->is_enabled()
			)
		);
	}

	/**
	 * @return Tag[]
	 */
	public function collect_tags( Context $context ): array {
		$all = [];
		foreach ( $this->enabled() as $platform ) {
			foreach ( $platform->tags( $context ) as $tag ) {
				$all[] = $tag;
			}
		}
		return $this->dedupe( $all );
	}

	/**
	 * @return array<int, string>
	 */
	public function collect_json_ld( Context $context ): array {
		$payloads = [];
		foreach ( $this->enabled() as $platform ) {
			$payload = $platform->json_ld( $context );
			if ( is_string( $payload ) && '' !== $payload ) {
				$payloads[] = $payload;
			}
		}
		return $payloads;
	}

	/**
	 * @param Tag[] $tags
	 * @return Tag[]
	 */
	private function dedupe( array $tags ): array {
		$seen   = [];
		$output = [];
		foreach ( $tags as $tag ) {
			$k = $tag->kind . '::' . $tag->key;
			if ( isset( $seen[ $k ] ) ) {
				continue;
			}
			$seen[ $k ] = true;
			$output[]   = $tag;
		}
		return $output;
	}
}
