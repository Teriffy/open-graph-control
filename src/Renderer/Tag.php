<?php
/**
 * Meta tag value object.
 *
 * @package EvzenLeonenko\OpenGraphControl
 */

declare(strict_types=1);


namespace EvzenLeonenko\OpenGraphControl\Renderer;

defined( 'ABSPATH' ) || exit;

/**
 * Immutable description of a single <meta> tag, consumed by TagBuilder.
 *
 * Kind selects the attribute used: "property" for OG/Article/Facebook tags,
 * "name" for Twitter/theme-color/fediverse:creator.
 */
final class Tag {

	public const KIND_PROPERTY = 'property';
	public const KIND_NAME     = 'name';

	public function __construct(
		public readonly string $kind,
		public readonly string $key,
		public readonly string $content
	) {}
}
