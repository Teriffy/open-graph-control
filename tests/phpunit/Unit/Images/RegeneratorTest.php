<?php
declare(strict_types=1);

namespace EvzenLeonenko\OpenGraphControl\Tests\Unit\Images;

use Brain\Monkey;
use Brain\Monkey\Actions;
use Brain\Monkey\Functions;
use EvzenLeonenko\OpenGraphControl\Images\Regenerator;
use PHPUnit\Framework\TestCase;

final class RegeneratorTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_register_hooks_cron_callback(): void {
		Actions\expectAdded( Regenerator::CRON_HOOK )->once();
		( new Regenerator() )->register();
		self::assertTrue( true );
	}

	public function test_start_writes_running_state_and_schedules_cron(): void {
		Functions\expect( 'update_option' )
			->once()
			->with(
				Regenerator::OPTION_STATE,
				\Mockery::on(
					static fn ( $state ) => is_array( $state )
						&& 'running' === $state['status']
						&& 0 === $state['processed']
						&& 0 === $state['offset']
				)
			);
		Functions\expect( 'wp_next_scheduled' )->once()->andReturn( false );
		Functions\expect( 'wp_schedule_single_event' )->once();

		( new Regenerator() )->start();
		self::assertTrue( true );
	}

	public function test_start_does_not_double_schedule(): void {
		Functions\expect( 'update_option' )->once();
		Functions\expect( 'wp_next_scheduled' )->once()->andReturn( 12345 );
		Functions\expect( 'wp_schedule_single_event' )->never();

		( new Regenerator() )->start();
		self::assertTrue( true );
	}

	public function test_status_returns_idle_when_option_missing(): void {
		Functions\expect( 'get_option' )->once()->andReturn( [] );
		$status = ( new Regenerator() )->status();
		self::assertSame( 'idle', $status['status'] );
		self::assertSame( 0, $status['processed'] );
	}

	public function test_status_reads_stored_state(): void {
		Functions\expect( 'get_option' )->once()->andReturn(
			[
				'status'    => 'running',
				'processed' => 42,
			]
		);
		$status = ( new Regenerator() )->status();
		self::assertSame( 'running', $status['status'] );
		self::assertSame( 42, $status['processed'] );
	}

	public function test_run_batch_marks_done_when_no_attachments(): void {
		Functions\when( 'get_option' )->justReturn(
			[
				'status'    => 'running',
				'processed' => 0,
				'offset'    => 0,
			]
		);
		Functions\when( 'get_posts' )->justReturn( [] );
		Functions\expect( 'update_option' )
			->once()
			->with(
				Regenerator::OPTION_STATE,
				\Mockery::on( static fn ( $s ) => 'done' === $s['status'] )
			);
		( new Regenerator() )->run_batch();
		self::assertTrue( true );
	}

	public function test_run_batch_skips_when_not_running(): void {
		Functions\when( 'get_option' )->justReturn( [ 'status' => 'idle' ] );
		Functions\expect( 'update_option' )->never();
		Functions\expect( 'get_posts' )->never();
		( new Regenerator() )->run_batch();
		self::assertTrue( true );
	}
}
