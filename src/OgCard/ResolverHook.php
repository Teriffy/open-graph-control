<?php
/**
 * ResolverHook connects OgCard to the resolver filter chain.
 *
 * @package EvzenLeonenko\OpenGraphControl\OgCard
 */

declare(strict_types=1);

namespace EvzenLeonenko\OpenGraphControl\OgCard;

use EvzenLeonenko\OpenGraphControl\Resolvers\Context;

defined( 'ABSPATH' ) || exit;

/**
 * Hooks into the resolver step filter to provide auto-generated OG cards.
 *
 * When the resolver chain hits the auto_card step, this hook checks:
 * 1. Feature enabled?
 * 2. Valid context (post/archive/author)?
 * 3. Card exists on disk?
 * 4. If not, schedule cold-start render at shutdown.
 */
final class ResolverHook {

	private const STEP_NAME = 'auto_card';

	/**
	 * @param callable(): Template $template_provider
	 */
	public function __construct(
		private readonly CardStore $store,
		private readonly CardGenerator $generator,
		private $template_provider,
	) {}

	public function register(): void {
		add_filter( 'ogc_resolve_image_step', [ $this, 'on_step' ], 10, 3 );
	}

	/**
	 * Handle the auto_card step in the resolver chain.
	 *
	 * @param string|null $value Value from earlier step (null if step returned nothing).
	 * @param string $step Current step name.
	 * @param Context $context Rendering context.
	 * @return string|null Attachment URL when card exists; null otherwise.
	 */
	public function on_step( ?string $value, string $step, Context $context ): ?string {
		// Pass through if value already set or step is not auto_card.
		if ( null !== $value || self::STEP_NAME !== $step ) {
			return $value;
		}

		// Check if feature is enabled.
		if ( ! $this->is_enabled() ) {
			return null;
		}

		// Get card key for this context.
		$key = $this->key_for_context( $context );
		if ( null === $key ) {
			return null;
		}

		// Get current template and check for existing card.
		$template = ( $this->template_provider )();
		$url      = $this->store->url( $key, $template, 'landscape' );

		// Return URL if card exists.
		if ( null !== $url ) {
			return $url;
		}

		// Schedule cold-start render and return null.
		$this->schedule_cold_render( $key );
		return null;
	}

	/**
	 * Check if the feature is enabled.
	 *
	 * @return bool
	 */
	private function is_enabled(): bool {
		$opts = (array) get_option( 'ogc_card_template', [] );
		return ! empty( $opts['enabled'] );
	}

	/**
	 * Get the card key for the given context.
	 *
	 * @param Context $context Rendering context.
	 * @return CardKey|null
	 */
	private function key_for_context( Context $context ): ?CardKey {
		// Check for post context.
		if ( null !== $context->post_id() ) {
			return CardKey::for_post( $context->post_id() );
		}

		// Check for author context.
		if ( $context->is_author() ) {
			$user_id = $context->user_id();
			return ( $user_id && $user_id > 0 ) ? CardKey::for_author( $user_id ) : null;
		}

		// Check for archive term context.
		if ( $context->is_archive_term() ) {
			$term_id = $context->archive_term_id();
			$tax     = method_exists( $context, 'archive_taxonomy' )
				? (string) $context->archive_taxonomy()
				: 'category';
			return ( $term_id && $term_id > 0 ) ? CardKey::for_archive( $tax, $term_id ) : null;
		}

		return null;
	}

	/**
	 * Schedule a cold-start render at shutdown.
	 *
	 * Uses a transient lock to prevent duplicate scheduling when the same
	 * URL is hit multiple times in rapid succession (e.g. bots/scrapers).
	 *
	 * @param CardKey $key Card key.
	 * @return void
	 */
	private function schedule_cold_render( CardKey $key ): void {
		$lock_key = 'ogc_card_pending_' . md5( $key->segment );

		// Check if already scheduled.
		if ( get_transient( $lock_key ) ) {
			return;
		}

		// Set transient lock for 30 seconds.
		set_transient( $lock_key, true, 30 );

		// Schedule render at shutdown.
		add_action(
			'shutdown',
			function () use ( $key, $lock_key ) {
				$this->generator->ensure( $key );
				delete_transient( $lock_key );
			}
		);
	}
}
