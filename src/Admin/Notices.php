<?php
/**
 * Admin notice for SEO plugin conflicts.
 *
 * @package EvzenLeonenko\OpenGraphControl
 */

declare(strict_types=1);


namespace EvzenLeonenko\OpenGraphControl\Admin;

defined( 'ABSPATH' ) || exit;

use EvzenLeonenko\OpenGraphControl\Integrations\Detector;
use EvzenLeonenko\OpenGraphControl\Options\Repository as OptionsRepository;

/**
 * Shows a one-time admin notice on every admin page when a competing SEO
 * plugin is active and the user hasn't made a takeover decision yet.
 *
 * The notice offers three actions:
 *  - "Take over" — enables takeover for every active competitor and reloads.
 *  - "Keep their tags" — records the user's "not now" decision.
 *  - "Review in settings" — deep link into the Integrations section.
 *
 * Dismissal is persisted per-user in user meta so each admin sees it
 * independently.
 */
final class Notices {

	private const USER_META_KEY = 'ogc_conflict_notice_dismissed';
	private const NONCE_ACTION  = 'ogc_conflict_notice';

	public function __construct(
		private Detector $detector,
		private OptionsRepository $options
	) {}

	public function register(): void {
		add_action( 'admin_notices', [ $this, 'maybe_render' ] );
		add_action( 'admin_post_ogc_dismiss_conflict_notice', [ $this, 'handle_action' ] );
	}

	public function maybe_render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Don't double up on the plugin's own Integrations screen.
		if ( isset( $_GET['page'] ) && Page::MENU_SLUG === $_GET['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only URL check.
			return;
		}

		$user_id = get_current_user_id();
		if ( $user_id > 0 && (string) get_user_meta( $user_id, self::USER_META_KEY, true ) === '1' ) {
			return;
		}

		$active = $this->detector->active();
		if ( [] === $active ) {
			return;
		}

		// Already have a takeover decision for every active plugin? Skip notice.
		$takeover  = $this->options->get_path( 'integrations.takeover' );
		$takeover  = is_array( $takeover ) ? $takeover : [];
		$undecided = [];
		foreach ( $active as $integration ) {
			if ( ! array_key_exists( $integration->slug(), $takeover ) ) {
				$undecided[] = $integration;
			}
		}
		if ( [] === $undecided ) {
			return;
		}

		$labels = array_map( static fn ( $i ) => $i->label(), $undecided );

		$settings_url = admin_url( 'admin.php?page=' . Page::MENU_SLUG );
		$takeover_url = wp_nonce_url(
			admin_url( 'admin-post.php?action=ogc_dismiss_conflict_notice&decision=takeover' ),
			self::NONCE_ACTION
		);
		$keep_url     = wp_nonce_url(
			admin_url( 'admin-post.php?action=ogc_dismiss_conflict_notice&decision=keep' ),
			self::NONCE_ACTION
		);

		?>
		<div class="notice notice-warning">
			<p>
				<strong><?php esc_html_e( 'Open Graph Control', 'open-graph-control' ); ?>:</strong>
				<?php
				printf(
					/* translators: %s: comma-separated list of competing SEO plugins. */
					esc_html__( 'Detected competing SEO plugin(s): %s. To avoid duplicate Open Graph tags, pick an option below.', 'open-graph-control' ),
					esc_html( implode( ', ', $labels ) )
				);
				?>
			</p>
			<p>
				<a class="button button-primary" href="<?php echo esc_url( $takeover_url ); ?>">
					<?php esc_html_e( 'Take over Open Graph output', 'open-graph-control' ); ?>
				</a>
				<a class="button" href="<?php echo esc_url( $keep_url ); ?>">
					<?php esc_html_e( 'Keep their tags, disable mine', 'open-graph-control' ); ?>
				</a>
				<a href="<?php echo esc_url( $settings_url ); ?>">
					<?php esc_html_e( 'Review in settings', 'open-graph-control' ); ?>
				</a>
			</p>
		</div>
		<?php
	}

	public function handle_action(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'open-graph-control' ), 403 );
		}
		check_admin_referer( self::NONCE_ACTION );

		$decision = isset( $_GET['decision'] ) ? sanitize_key( (string) wp_unslash( $_GET['decision'] ) ) : '';

		$takeover_patch = [];
		$platform_patch = [];
		foreach ( $this->detector->active() as $integration ) {
			$slug = $integration->slug();
			if ( 'takeover' === $decision ) {
				$takeover_patch[ $slug ] = true;
			} else {
				$takeover_patch[ $slug ] = false;
			}
		}

		if ( 'keep' === $decision ) {
			// Keep their tags → disable our platform output globally.
			foreach ( [ 'facebook', 'twitter' ] as $slug ) {
				$platform_patch[ $slug ] = [ 'enabled' => false ];
			}
		}

		$patch = [
			'integrations' => [ 'takeover' => $takeover_patch ],
		];
		if ( ! empty( $platform_patch ) ) {
			$patch['platforms'] = $platform_patch;
		}
		$this->options->update( $patch );

		$user_id = get_current_user_id();
		if ( $user_id > 0 ) {
			update_user_meta( $user_id, self::USER_META_KEY, '1' );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=' . Page::MENU_SLUG ) );
		exit;
	}
}
