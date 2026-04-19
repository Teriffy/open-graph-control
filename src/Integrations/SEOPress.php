<?php
/**
 * SEOPress integration.
 *
 * @package EvzenLeonenko\OpenGraphControl
 */

declare(strict_types=1);

namespace EvzenLeonenko\OpenGraphControl\Integrations;

/**
 * Detects SEOPress and disables its Open Graph + Twitter Card output
 * through seopress_social_og_enable and seopress_social_twitter_card_enable.
 */
final class SEOPress extends AbstractIntegration {

	public function slug(): string {
		return 'seopress';
	}

	public function label(): string {
		return 'SEOPress';
	}

	public function is_active(): bool {
		return defined( 'SEOPRESS_VERSION' ) || function_exists( 'seopress_init' );
	}

	public function apply_takeover(): void {
		add_filter( 'seopress_social_og_enable', '__return_false' );
		add_filter( 'seopress_social_twitter_card_enable', '__return_false' );
	}
}
