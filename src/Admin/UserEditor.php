<?php
/**
 * Inline Open Graph overrides editor on the user profile screen.
 *
 * @package EvzenLeonenko\OpenGraphControl
 */

declare(strict_types=1);


namespace EvzenLeonenko\OpenGraphControl\Admin;

defined( 'ABSPATH' ) || exit;

use EvzenLeonenko\OpenGraphControl\ArchiveMeta\Repository;
use WP_User;

/**
 * Injects the React mount point on the show / edit user profile screens.
 *
 * The React bundle (build/admin/archive.js) takes over once mounted and talks
 * to the REST API directly; no server-side save hook is wired here.
 */
final class UserEditor {

	/**
	 * The repository is accepted for symmetry with TermEditor / future save hooks,
	 * but rendering itself is stateless — the React bundle talks to REST directly.
	 */
	public function __construct( Repository $archive ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		unset( $archive );
	}

	public function register(): void {
		add_action( 'show_user_profile', [ $this, 'render' ] );
		add_action( 'edit_user_profile', [ $this, 'render' ] );
	}

	public function render( WP_User $user ): void {
		if ( ! current_user_can( 'edit_user', (int) $user->ID ) ) {
			return;
		}
		printf(
			'<h2>%s</h2><table class="form-table"><tr><th scope="row">%s</th>' .
			'<td><div id="ogc-archive-root" data-kind="user" data-id="%d"></div></td></tr></table>',
			esc_html__( 'Open Graph overrides', 'open-graph-control' ),
			esc_html__( 'Author archive', 'open-graph-control' ),
			(int) $user->ID
		);
	}
}
