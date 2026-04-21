<?php
declare(strict_types=1);

namespace EvzenLeonenko\OpenGraphControl\Tests\Unit\Admin;

use Brain\Monkey;
use Brain\Monkey\Actions;
use Brain\Monkey\Functions;
use EvzenLeonenko\OpenGraphControl\Admin\TermEditor;
use EvzenLeonenko\OpenGraphControl\ArchiveMeta\Repository;
use PHPUnit\Framework\TestCase;

final class TermEditorTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		if ( ! class_exists( 'WP_Term' ) ) {
			// Cannot class_alias stdClass (internal); declare a minimal stub instead.
			eval( 'class WP_Term {}' ); // phpcs:ignore Squiz.PHP.Eval.Discouraged -- test-only polyfill for PHP 8.2+ compatibility.
		}
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_hook_taxonomies_registers_public_taxes_only(): void {
		Functions\when( 'get_taxonomies' )->justReturn(
			[
				'category'       => 'category',
				'post_tag'       => 'post_tag',
				'attachment'     => 'attachment',
				'portfolio_type' => 'portfolio_type',
			]
		);

		Actions\expectAdded( 'category_edit_form_fields' )->once();
		Actions\expectAdded( 'post_tag_edit_form_fields' )->once();
		Actions\expectAdded( 'portfolio_type_edit_form_fields' )->once();

		$editor = new TermEditor( $this->createStub( Repository::class ) );
		$editor->hook_taxonomies();

		self::assertFalse( \has_action( 'attachment_edit_form_fields' ) );
	}

	public function test_register_hooks_admin_init(): void {
		Actions\expectAdded( 'admin_init' )->once();

		$editor = new TermEditor( $this->createStub( Repository::class ) );
		$editor->register();

		self::assertTrue( true );
	}

	public function test_render_outputs_mount_point_with_correct_attributes(): void {
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'esc_html__' )->returnArg();

		$term          = new \WP_Term();
		$term->term_id = 12;

		$editor = new TermEditor( $this->createStub( Repository::class ) );

		\ob_start();
		try {
			$editor->render( $term, 'category' );
			$output = (string) \ob_get_clean();
		} catch ( \Throwable $e ) {
			\ob_end_clean();
			throw $e;
		}

		self::assertStringContainsString( 'id="ogc-archive-root"', $output );
		self::assertStringContainsString( 'data-kind="term"', $output );
		self::assertStringContainsString( 'data-tax="category"', $output );
		self::assertStringContainsString( 'data-id="12"', $output );
	}

	public function test_render_noops_without_cap(): void {
		Functions\when( 'current_user_can' )->justReturn( false );

		$term          = new \WP_Term();
		$term->term_id = 12;

		$editor = new TermEditor( $this->createStub( Repository::class ) );

		\ob_start();
		$editor->render( $term, 'category' );
		$output = (string) \ob_get_clean();

		self::assertSame( '', $output );
	}
}
