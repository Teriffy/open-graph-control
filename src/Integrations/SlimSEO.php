<?php
/**
 * Slim SEO integration.
 *
 * @package EvzenLeonenko\OpenGraphControl
 */

declare(strict_types=1);


namespace EvzenLeonenko\OpenGraphControl\Integrations;

defined( 'ABSPATH' ) || exit;

/**
 * Detects Slim SEO and clears its Open Graph tag output via
 * slim_seo_open_graph_tags.
 */
final class SlimSEO extends AbstractIntegration {

	public function slug(): string {
		return 'slim_seo';
	}

	public function label(): string {
		return 'Slim SEO';
	}

	public function is_active(): bool {
		return defined( 'SLIM_SEO_VERSION' );
	}

	public function apply_takeover(): void {
		add_filter( 'slim_seo_open_graph_tags', '__return_empty_array' );
	}
}
