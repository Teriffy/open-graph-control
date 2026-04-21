<?php
/**
 * JetEngine resolver for title + description step filters.
 *
 * @package EvzenLeonenko\OpenGraphControl
 */

declare(strict_types=1);


namespace EvzenLeonenko\OpenGraphControl\Integrations;

use EvzenLeonenko\OpenGraphControl\Resolvers\Context;

defined( 'ABSPATH' ) || exit;

/**
 * Hooks into ogc_resolve_title_step and ogc_resolve_description_step to pull
 * values from JetEngine meta fields when the chain reaches the jet_title_field or
 * jet_description_field step respectively.
 *
 * Field name → post type mapping is stored in the ogc_field_sources option:
 * ['jet'][$post_type]['title'] and ['jet'][$post_type]['description'].
 *
 * Field values are read via get_post_meta() rather than a JetEngine-specific
 * function, since JetEngine stores all meta using the native WP meta API.
 *
 * NOTE: The JetEngine meta_boxes API (jet_engine()->meta_boxes->get_meta_boxes())
 * used in FieldDiscovery was modelled on the public API documented for
 * JetEngine 3.x. If that method does not exist at runtime, FieldDiscovery
 * degrades gracefully and returns []. This resolver is independent of that
 * discovery path and should work regardless of JetEngine version.
 */
final class JetEngineFieldResolver {

	private const STEP_TITLE       = 'jet_title_field';
	private const STEP_DESCRIPTION = 'jet_description_field';

	/**
	 * Register filter hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'ogc_resolve_title_step', [ $this, 'on_title_step' ], 10, 3 );
		add_filter( 'ogc_resolve_description_step', [ $this, 'on_description_step' ], 10, 3 );
	}

	/**
	 * Handle the jet_title_field step.
	 *
	 * @param string|null $value   Resolved value from a prior step, or null.
	 * @param string      $step    Current chain step name.
	 * @param Context     $context Rendering context.
	 * @return string|null
	 */
	public function on_title_step( ?string $value, string $step, Context $context ): ?string {
		return $this->resolve( $value, $step, $context, self::STEP_TITLE, 'title' );
	}

	/**
	 * Handle the jet_description_field step.
	 *
	 * @param string|null $value   Resolved value from a prior step, or null.
	 * @param string      $step    Current chain step name.
	 * @param Context     $context Rendering context.
	 * @return string|null
	 */
	public function on_description_step( ?string $value, string $step, Context $context ): ?string {
		return $this->resolve( $value, $step, $context, self::STEP_DESCRIPTION, 'description' );
	}

	/**
	 * Core resolution logic shared by both step handlers.
	 *
	 * @param string|null $value       Value from an earlier step.
	 * @param string      $step        Current step name.
	 * @param Context     $context     Rendering context.
	 * @param string      $target_step Step name this resolver handles.
	 * @param string      $kind        'title' or 'description'.
	 * @return string|null
	 */
	private function resolve( ?string $value, string $step, Context $context, string $target_step, string $kind ): ?string {
		if ( null !== $value || $step !== $target_step ) {
			return $value;
		}
		if ( ! function_exists( 'jet_engine' ) && ! class_exists( 'Jet_Engine' ) ) {
			return null;
		}
		$post_id = $context->post_id();
		if ( null === $post_id || $post_id <= 0 ) {
			return null;
		}
		$post_type = get_post_type( $post_id );
		if ( ! is_string( $post_type ) || '' === $post_type ) {
			return null;
		}
		$sources = (array) get_option( 'ogc_field_sources', [] );
		$field   = $sources['jet'][ $post_type ][ $kind ] ?? null;
		if ( ! is_string( $field ) || '' === $field ) {
			return null;
		}
		$raw = get_post_meta( $post_id, $field, true );
		if ( ! is_string( $raw ) ) {
			return null;
		}
		$clean   = wp_strip_all_tags( $raw );
		$trimmed = trim( $clean );
		return '' === $trimmed ? null : $trimmed;
	}
}
