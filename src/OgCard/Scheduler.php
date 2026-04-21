<?php
/**
 * Scheduler hook adapter for OG card lifecycle.
 *
 * @package EvzenLeonenko\OpenGraphControl
 */

declare(strict_types=1);

namespace EvzenLeonenko\OpenGraphControl\OgCard;

defined( 'ABSPATH' ) || exit;

/**
 * Scheduler adapts WordPress hooks to CardGenerator and CardStore operations.
 *
 * Coordinates rendering of OG cards on post/term/user changes and deletion
 * on post/term/user removal. Defers rendering to the shutdown hook to batch
 * multiple changes in a single request.
 */
final class Scheduler {

	/**
	 * Creates a new Scheduler instance.
	 *
	 * @param CardGenerator $generator Generator for rendering cards.
	 * @param CardStore     $store     Store for card file operations (used in delete handlers).
	 */
	public function __construct(
		private readonly CardGenerator $generator,
		/** @phpstan-ignore-next-line -- Used in delete handlers (Task 5.2) */
		private readonly CardStore $store,
	) {}

	/**
	 * Registers all hook handlers.
	 *
	 * @return void
	 */
	public function register(): void {
		\add_action( 'save_post', [ $this, 'on_save_post' ], 20, 2 );
		\add_action( 'edited_term', [ $this, 'on_edited_term' ], 20, 3 );
		\add_action( 'profile_update', [ $this, 'on_profile_update' ], 20, 1 );
		\add_action( 'before_delete_post', [ $this, 'on_delete_post' ], 10, 1 );
		\add_action( 'delete_term', [ $this, 'on_delete_term' ], 10, 3 );
		\add_action( 'delete_user', [ $this, 'on_delete_user' ], 10, 1 );
	}

	/**
	 * Handles post save events.
	 *
	 * Skips drafts and revisions. Defers published post card rendering to shutdown.
	 *
	 * @param int              $post_id Post ID.
	 * @param object|mixed     $post    Post object (WP_Post or stdClass).
	 *
	 * @return void
	 */
	public function on_save_post( int $post_id, $post ): void {
		if ( \wp_is_post_revision( $post_id ) ) {
			return;
		}
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Property access on WP_Post object.
		if ( 'publish' !== ( $post->post_status ?? null ) ) {
			return;
		}
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Property access on WP_Post object.
		if ( ! isset( $post->post_type ) || ! \is_post_type_viewable( $post->post_type ) ) {
			return;
		}
		\add_action(
			'shutdown',
			function () use ( $post_id ) {
				$this->generator->ensure( CardKey::for_post( $post_id ) );
			}
		);
	}

	/**
	 * Handles term edit events.
	 *
	 * Handled in Task 5.2.
	 *
	 * @param int    $term_id  Term ID.
	 * @param int    $tt_id    Term taxonomy ID.
	 * @param string $taxonomy Taxonomy name.
	 *
	 * @return void
	 */
	public function on_edited_term( int $term_id, int $tt_id, string $taxonomy ): void {
		// Handled in Task 5.2.
	}

	/**
	 * Handles user profile update events.
	 *
	 * Handled in Task 5.2.
	 *
	 * @param int $user_id User ID.
	 *
	 * @return void
	 */
	public function on_profile_update( int $user_id ): void {
		// Handled in Task 5.2.
	}

	/**
	 * Handles post deletion.
	 *
	 * Handled in Task 5.2.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return void
	 */
	public function on_delete_post( int $post_id ): void {
		// Handled in Task 5.2.
	}

	/**
	 * Handles term deletion.
	 *
	 * Handled in Task 5.2.
	 *
	 * @param int    $term_id  Term ID.
	 * @param int    $tt_id    Term taxonomy ID.
	 * @param string $taxonomy Taxonomy name.
	 *
	 * @return void
	 */
	public function on_delete_term( int $term_id, int $tt_id, string $taxonomy ): void {
		// Handled in Task 5.2.
	}

	/**
	 * Handles user deletion.
	 *
	 * Handled in Task 5.2.
	 *
	 * @param int $user_id User ID.
	 *
	 * @return void
	 */
	public function on_delete_user( int $user_id ): void {
		// Handled in Task 5.2.
	}
}
