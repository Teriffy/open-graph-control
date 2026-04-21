<?php
/**
 * GcCron garbage collection tests.
 *
 * @package EvzenLeonenko\OpenGraphControl
 */

declare(strict_types=1);

namespace EvzenLeonenko\OpenGraphControl\Tests\Unit\OgCard;

use Brain\Monkey;
use Brain\Monkey\Functions;
use EvzenLeonenko\OpenGraphControl\OgCard\GcCron;
use EvzenLeonenko\OpenGraphControl\OgCard\Template;
use PHPUnit\Framework\TestCase;

/**
 * Tests for GcCron garbage collection of stale template versions.
 *
 * Verifies that GcCron correctly registers daily cron schedules and deletes
 * orphaned card files whose template hash is no longer in use, while respecting
 * grace periods for cache scraper compliance.
 */
final class GcCronTest extends TestCase {

	/**
	 * Temporary base directory for garbage collection tests.
	 *
	 * @var string
	 */
	private string $cards_dir;

	/**
	 * Sets up test fixtures.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		if ( ! defined( 'DAY_IN_SECONDS' ) ) {
			define( 'DAY_IN_SECONDS', 86400 );
		}
		$this->cards_dir = sys_get_temp_dir() . '/' . uniqid( 'ogc_gc_test_', true );
		mkdir( $this->cards_dir, 0777, true );
	}

	/**
	 * Cleans up temporary files after each test.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
		if ( is_dir( $this->cards_dir ) ) {
			$this->remove_dir_recursively( $this->cards_dir );
		}
	}

	/**
	 * Recursively removes a directory and all its contents.
	 *
	 * @param string $dir Directory path.
	 *
	 * @return void
	 */
	private function remove_dir_recursively( string $dir ): void {
		if ( ! is_dir( $dir ) ) {
			return;
		}
		$files = scandir( $dir );
		if ( false === $files ) {
			return;
		}
		foreach ( $files as $file ) {
			if ( '.' !== $file && '..' !== $file ) {
				$path = $dir . '/' . $file;
				if ( is_dir( $path ) ) {
					$this->remove_dir_recursively( $path );
				} else {
					unlink( $path );
				}
			}
		}
		rmdir( $dir );
	}

	/**
	 * Tests register() schedules daily event if not already scheduled.
	 *
	 * @return void
	 */
	public function test_register_schedules_daily_event_if_unscheduled(): void {
		Functions\when( 'wp_next_scheduled' )->justReturn( false );
		Functions\expect( 'wp_schedule_event' )->once()->with(
			\Mockery::type( 'int' ),
			'daily',
			'ogc_og_card_gc'
		)->andReturnTrue();
		GcCron::register();
		$this->assertTrue( true ); // Verify expectations passed
	}

	/**
	 * Tests register() does not reschedule if event already scheduled.
	 *
	 * @return void
	 */
	public function test_register_skips_if_already_scheduled(): void {
		Functions\when( 'wp_next_scheduled' )->justReturn( 1234567890 );
		Functions\expect( 'wp_schedule_event' )->never();
		GcCron::register();
		$this->assertTrue( true ); // Verify expectations passed
	}

	/**
	 * Tests tick() deletes stale files older than grace period.
	 *
	 * @return void
	 */
	public function test_tick_deletes_stale_template_version_after_grace(): void {
		$current_hash = Template::default()->hash();
		$stale_hash   = 'deadbeef';

		// Create current version file (should be kept)
		$current_file = $this->cards_dir . "/post-1-{$current_hash}-landscape.png";
		file_put_contents( $current_file, 'CURRENT' );

		// Create stale file older than 7 days (should be deleted)
		$stale_file = $this->cards_dir . "/post-2-{$stale_hash}-landscape.png";
		file_put_contents( $stale_file, 'STALE' );
		// Set mtime to 8 days ago (past grace period)
		touch( $stale_file, time() - ( 8 * DAY_IN_SECONDS ) );

		$template_provider = static fn() => Template::default();
		$gc                = new GcCron( $this->cards_dir, $template_provider );
		$gc->tick();

		$this->assertFileExists( $current_file );
		$this->assertFileDoesNotExist( $stale_file );
	}

	/**
	 * Tests tick() keeps current template version files.
	 *
	 * @return void
	 */
	public function test_tick_keeps_current_template_version(): void {
		$current_hash = Template::default()->hash();

		// Create file with current hash (should be kept)
		$file = $this->cards_dir . "/post-1-{$current_hash}-landscape.png";
		file_put_contents( $file, 'CURRENT' );
		// Make it very old
		touch( $file, time() - ( 30 * DAY_IN_SECONDS ) );

		$template_provider = static fn() => Template::default();
		$gc                = new GcCron( $this->cards_dir, $template_provider );
		$gc->tick();

		$this->assertFileExists( $file );
	}

	/**
	 * Tests tick() keeps stale files within grace period.
	 *
	 * @return void
	 */
	public function test_tick_keeps_stale_file_within_grace_period(): void {
		$current_hash = Template::default()->hash();
		$stale_hash   = 'deadbeef';

		// Create stale file within grace period (should be kept)
		$file = $this->cards_dir . "/post-1-{$stale_hash}-landscape.png";
		file_put_contents( $file, 'STALE_BUT_YOUNG' );
		// Set mtime to 3 days ago (within 7-day grace)
		touch( $file, time() - ( 3 * DAY_IN_SECONDS ) );

		$template_provider = static fn() => Template::default();
		$gc                = new GcCron( $this->cards_dir, $template_provider );
		$gc->tick();

		$this->assertFileExists( $file );
	}

	/**
	 * Tests tick() handles subdirectory structure (archive/author cards).
	 *
	 * @return void
	 */
	public function test_tick_handles_subdirectories(): void {
		$current_hash = Template::default()->hash();
		$stale_hash   = 'deadbeef';

		// Create archive subdirectory with stale file
		mkdir( $this->cards_dir . '/archive' );
		$stale_file = $this->cards_dir . "/archive/category-5-{$stale_hash}-landscape.png";
		file_put_contents( $stale_file, 'STALE' );
		touch( $stale_file, time() - ( 8 * DAY_IN_SECONDS ) );

		// Create author subdirectory with current file
		mkdir( $this->cards_dir . '/author' );
		$current_file = $this->cards_dir . "/author/123-{$current_hash}-landscape.png";
		file_put_contents( $current_file, 'CURRENT' );

		$template_provider = static fn() => Template::default();
		$gc                = new GcCron( $this->cards_dir, $template_provider );
		$gc->tick();

		$this->assertFileDoesNotExist( $stale_file );
		$this->assertFileExists( $current_file );
	}

	/**
	 * Tests tick() ignores non-PNG files.
	 *
	 * @return void
	 */
	public function test_tick_ignores_non_png_files(): void {
		// Create non-PNG files that should be ignored
		$txt_file = $this->cards_dir . '/some-file.txt';
		file_put_contents( $txt_file, 'NOT PNG' );

		$jpg_file = $this->cards_dir . '/image.jpg';
		file_put_contents( $jpg_file, 'JPEG DATA' );

		$template_provider = static fn() => Template::default();
		$gc                = new GcCron( $this->cards_dir, $template_provider );
		$gc->tick();

		$this->assertFileExists( $txt_file );
		$this->assertFileExists( $jpg_file );
	}

	/**
	 * Tests tick() ignores files with invalid hash pattern.
	 *
	 * @return void
	 */
	public function test_tick_ignores_invalid_filename_pattern(): void {
		// File without proper hash pattern should be ignored
		$invalid_file = $this->cards_dir . '/post-1-invalid-landscape.png';
		file_put_contents( $invalid_file, 'DATA' );
		touch( $invalid_file, time() - ( 8 * DAY_IN_SECONDS ) );

		$template_provider = static fn() => Template::default();
		$gc                = new GcCron( $this->cards_dir, $template_provider );
		$gc->tick();

		// File should not be deleted because hash pattern doesn't match
		$this->assertFileExists( $invalid_file );
	}

	/**
	 * Tests tick() handles missing directory gracefully.
	 *
	 * @return void
	 */
	public function test_tick_handles_missing_directory(): void {
		$nonexistent_dir   = sys_get_temp_dir() . '/' . uniqid( 'ogc_missing_', true );
		$template_provider = static fn() => Template::default();
		$gc                = new GcCron( $nonexistent_dir, $template_provider );

		// Should not throw exception
		$gc->tick();

		$this->assertTrue( true ); // Reached without exception
	}
}
