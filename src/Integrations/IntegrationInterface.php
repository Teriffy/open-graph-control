<?php
/**
 * SEO plugin integration contract.
 *
 * @package EvzenLeonenko\OpenGraphControl
 */

declare(strict_types=1);


namespace EvzenLeonenko\OpenGraphControl\Integrations;

defined( 'ABSPATH' ) || exit;

/**
 * Each integration represents one SEO/social plugin we detect and
 * optionally take over the Open Graph output from.
 */
interface IntegrationInterface {

	/** Stable identifier used in settings (e.g. 'yoast'). */
	public function slug(): string;

	/** User-facing name. */
	public function label(): string;

	/** True when the other plugin is currently active in this WP install. */
	public function is_active(): bool;

	/**
	 * Disable the other plugin's OG/Twitter output by adding its own opt-out
	 * filters. Idempotent — safe to call multiple times.
	 */
	public function apply_takeover(): void;

	/**
	 * Expose the other plugin's computed title/description so the Title /
	 * Description resolvers can pick them up via ogc_seo_plugin_title /
	 * ogc_seo_plugin_desc filters.
	 */
	public function register_value_bridge(): void;
}
