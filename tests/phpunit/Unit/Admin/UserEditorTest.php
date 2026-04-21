<?php
declare(strict_types=1);

namespace EvzenLeonenko\OpenGraphControl\Tests\Unit\Admin;

use Brain\Monkey;
use Brain\Monkey\Actions;
use Brain\Monkey\Functions;
use EvzenLeonenko\OpenGraphControl\Admin\UserEditor;
use EvzenLeonenko\OpenGraphControl\ArchiveMeta\Repository;
use PHPUnit\Framework\TestCase;

final class UserEditorTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		if ( ! class_exists( 'WP_User' ) ) {
			// Cannot class_alias stdClass (internal); declare a minimal stub instead.
			eval( 'class WP_User {}' ); // phpcs:ignore Squiz.PHP.Eval.Discouraged -- test-only polyfill for PHP 8.2+ compatibility.
		}
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_register_hooks_both_profile_actions(): void {
		Actions\expectAdded( 'show_user_profile' )->once();
		Actions\expectAdded( 'edit_user_profile' )->once();

		$editor = new UserEditor( $this->createStub( Repository::class ) );
		$editor->register();

		self::assertTrue( true );
	}

	public function test_render_outputs_mount_point_with_correct_attributes(): void {
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'esc_html__' )->returnArg();

		$user     = new \WP_User();
		$user->ID = 3;

		$editor = new UserEditor( $this->createStub( Repository::class ) );

		\ob_start();
		try {
			$editor->render( $user );
			$output = (string) \ob_get_clean();
		} catch ( \Throwable $e ) {
			\ob_end_clean();
			throw $e;
		}

		self::assertStringContainsString( 'id="ogc-archive-root"', $output );
		self::assertStringContainsString( 'data-kind="user"', $output );
		self::assertStringContainsString( 'data-id="3"', $output );
	}

	public function test_render_noops_without_cap(): void {
		Functions\when( 'current_user_can' )->justReturn( false );

		$user     = new \WP_User();
		$user->ID = 3;

		$editor = new UserEditor( $this->createStub( Repository::class ) );

		\ob_start();
		$editor->render( $user );
		$output = (string) \ob_get_clean();

		self::assertSame( '', $output );
	}
}
