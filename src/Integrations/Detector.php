<?php
/**
 * SEO plugin detector + takeover orchestrator.
 *
 * @package EvzenLeonenko\OpenGraphControl
 */

declare(strict_types=1);

namespace EvzenLeonenko\OpenGraphControl\Integrations;

use EvzenLeonenko\OpenGraphControl\Options\Repository as OptionsRepository;

/**
 * Walks registered integrations once per request, updates the stored
 * "detected" list in ogc_settings, and applies takeover filters for any
 * plugin the user has opted into taking over.
 *
 * Expose integrations via ogc_detected_plugins so other plugins can
 * register their own SEO competitor classes.
 */
class Detector {

	/** @var array<int, IntegrationInterface> */
	private array $integrations = [];

	public function __construct( private OptionsRepository $options ) {}

	public function register( IntegrationInterface $integration ): void {
		$this->integrations[] = $integration;
	}

	/**
	 * @return IntegrationInterface[]
	 */
	public function all(): array {
		/** @var IntegrationInterface[] $filtered */
		$filtered = apply_filters( 'ogc_detected_plugins', $this->integrations );
		return $filtered;
	}

	/**
	 * @return IntegrationInterface[]
	 */
	public function active(): array {
		return array_values(
			array_filter(
				$this->all(),
				static fn ( IntegrationInterface $i ) => $i->is_active()
			)
		);
	}

	/**
	 * Updates ogc_settings.integrations.detected with the current snapshot
	 * and applies takeover filters based on user preference.
	 */
	public function run(): void {
		$active_slugs = array_map(
			static fn ( IntegrationInterface $i ) => $i->slug(),
			$this->active()
		);

		$current          = $this->options->get_path( 'integrations.detected' );
		$current_as_array = is_array( $current ) ? $current : [];
		if ( $current_as_array !== $active_slugs ) {
			$this->options->update(
				[
					'integrations' => [
						'detected' => $active_slugs,
					],
				]
			);
		}

		$takeover = $this->options->get_path( 'integrations.takeover' );
		$takeover = is_array( $takeover ) ? $takeover : [];

		foreach ( $this->active() as $integration ) {
			// Value bridge always runs (so resolvers can still pick up SEO titles
			// even when the user chose to keep the competitor's OG output).
			$integration->register_value_bridge();

			/** @var bool $enabled */
			$enabled = (bool) apply_filters(
				'ogc_apply_takeover_' . $integration->slug(),
				! empty( $takeover[ $integration->slug() ] )
			);
			if ( $enabled ) {
				$integration->apply_takeover();
			}
		}
	}
}
