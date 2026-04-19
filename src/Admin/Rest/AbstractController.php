<?php
/**
 * Base REST controller.
 *
 * @package EvzenLeonenko\OpenGraphControl
 */

declare(strict_types=1);


namespace EvzenLeonenko\OpenGraphControl\Admin\Rest;

defined( 'ABSPATH' ) || exit;

/**
 * Shared namespace + capability helpers for plugin REST endpoints.
 *
 * Concrete controllers implement register_routes(); AbstractController
 * registers register_routes() on rest_api_init so the caller only needs
 * to call ::register() once from the Plugin boot step.
 */
abstract class AbstractController {

	public const NAMESPACE_BASE = 'open-graph-control/v1';

	public function register(): void {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	abstract public function register_routes(): void;

	public function require_manage_options(): bool {
		return current_user_can( 'manage_options' );
	}

	public function require_edit_post( int $post_id ): bool {
		return current_user_can( 'edit_post', $post_id );
	}
}
