<?php
/**
 * Rank Math SEO integration.
 *
 * @package EvzenLeonenko\OpenGraphControl
 */

declare(strict_types=1);


namespace EvzenLeonenko\OpenGraphControl\Integrations;

defined( 'ABSPATH' ) || exit;

/**
 * Detects Rank Math and disables its Facebook and Twitter Open Graph
 * output through its built-in tag-enable filters.
 */
final class RankMath extends AbstractIntegration {

	public function slug(): string {
		return 'rankmath';
	}

	public function label(): string {
		return 'Rank Math SEO';
	}

	public function is_active(): bool {
		return class_exists( 'RankMath' ) || defined( 'RANK_MATH_VERSION' );
	}

	public function apply_takeover(): void {
		add_filter( 'rank_math/opengraph/facebook/enable_tags', '__return_false' );
		add_filter( 'rank_math/opengraph/twitter/enable_tags', '__return_false' );
	}
}
