<?php
/**
 * ACF resolver for title + description step filters.
 *
 * @package EvzenLeonenko\OpenGraphControl
 */

declare(strict_types=1);


namespace EvzenLeonenko\OpenGraphControl\Integrations;

use EvzenLeonenko\OpenGraphControl\Resolvers\Context;

defined( 'ABSPATH' ) || exit;

/**
 * Hooks into ogc_resolve_title_step and ogc_resolve_description_step to pull
 * values from ACF custom fields when the chain reaches the acf_title_field or
 * acf_description_field step respectively.
 *
 * Field name → post type mapping is stored in the ogc_field_sources option:
 * ['acf'][$post_type]['title'] and ['acf'][$post_type]['description'].
 */
final class AcfFieldResolver {

	private const STEP_TITLE       = 'acf_title_field';
	private const STEP_DESCRIPTION = 'acf_description_field';

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
	 * Handle the acf_title_field step.
	 *
	 * @param string|null $value  Resolved value from a prior step, or null.
	 * @param string      $step   Current chain step name.
	 * @param Context     $context Rendering context.
	 * @return string|null
	 */
	public function on_title_step( ?string $value, string $step, Context $context ): ?string {
		return $this->resolve( $value, $step, $context, self::STEP_TITLE, 'title' );
	}

	/**
	 * Handle the acf_description_field step.
	 *
	 * @param string|null $value  Resolved value from a prior step, or null.
	 * @param string      $step   Current chain step name.
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
		if ( ! function_exists( 'get_field' ) ) {
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
		$field   = $sources['acf'][ $post_type ][ $kind ] ?? null;
		if ( ! is_string( $field ) || '' === $field ) {
			return null;
		}
		$raw = get_field( $field, $post_id );
		if ( ! is_string( $raw ) ) {
			return null;
		}
		$clean   = wp_strip_all_tags( $raw );
		$trimmed = trim( $clean );
		return '' === $trimmed ? null : $trimmed;
	}
}
