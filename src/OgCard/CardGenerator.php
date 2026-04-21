<?php
/**
 * CardGenerator orchestrator for rendering and caching OG cards.
 *
 * @package EvzenLeonenko\OpenGraphControl
 */

declare(strict_types=1);

namespace EvzenLeonenko\OpenGraphControl\OgCard;

defined( 'ABSPATH' ) || exit;

/**
 * Orchestrator for rendering OG cards with cached result storage.
 *
 * Coordinates template resolution, payload building, renderer selection,
 * rendering, and persistence. Uses closure-based dependency injection
 * to maintain testability without coupling to PayloadBuilder or RendererPicker.
 * Failures during rendering log an error and return null.
 */
final class CardGenerator {

	/**
	 * Callable to pick the appropriate renderer.
	 *
	 * @var callable(): RendererInterface
	 */
	private $picker;

	/**
	 * Callable to provide the current template configuration.
	 *
	 * @var callable(): Template
	 */
	private $template_provider;

	/**
	 * Callable to build payload from a card key.
	 *
	 * @var callable(CardKey): Payload
	 */
	private $payload_provider;

	/**
	 * Creates a new CardGenerator instance.
	 *
	 * @param callable(): RendererInterface $picker             Callable returning a RendererInterface.
	 * @param CardStore                     $store             Store for filesystem operations.
	 * @param callable(): Template          $template_provider Callable returning current Template.
	 * @param callable(CardKey): Payload    $payload_provider  Callable building Payload from CardKey.
	 */
	public function __construct(
		$picker,
		private readonly CardStore $store,
		$template_provider,
		$payload_provider,
	) {
		$this->picker            = $picker;
		$this->template_provider = $template_provider;
		$this->payload_provider  = $payload_provider;
	}

	/**
	 * Ensures a card is rendered and cached, or returns existing cached path.
	 *
	 * Short-circuits if a card for the given key and current template already
	 * exists on disk. Otherwise, calls payload_provider() and picker() to build
	 * the card, renders it via renderer->render(), and stores the result.
	 * Any exception during render logs and returns null.
	 *
	 * @param CardKey $key Identifies the card target.
	 *
	 * @return string|null Path to the card file, or null if rendering failed.
	 */
	public function ensure( CardKey $key ): ?string {
		$template = ( $this->template_provider )();
		if ( $this->store->exists( $key, $template, 'landscape' ) ) {
			return $this->store->path( $key, $template, 'landscape' );
		}
		try {
			$payload  = ( $this->payload_provider )( $key );
			$renderer = ( $this->picker )();
			$bytes    = $renderer->render( $template, $payload );
			return $this->store->write( $key, $template, 'landscape', $bytes );
		} catch ( \Throwable $e ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Justified: native error logging for rendering failures, matches CardStore filesystem error pattern.
			error_log( sprintf( '[OGC] CardGenerator::ensure failed for %s: %s', $key->segment, $e->getMessage() ) );
			return null;
		}
	}
}
