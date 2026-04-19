<?php
/**
 * The SEO Framework integration.
 *
 * @package EvzenLeonenko\OpenGraphControl
 */

declare(strict_types=1);

namespace EvzenLeonenko\OpenGraphControl\Integrations;

/**
 * Detects The SEO Framework (TSF) and disables its Open Graph output
 * via the_seo_framework_og_output.
 */
final class TSF extends AbstractIntegration {

	public function slug(): string {
		return 'tsf';
	}

	public function label(): string {
		return 'The SEO Framework';
	}

	public function is_active(): bool {
		return defined( 'THE_SEO_FRAMEWORK_VERSION' );
	}

	public function apply_takeover(): void {
		add_filter( 'the_seo_framework_og_output', '__return_false' );
	}
}
