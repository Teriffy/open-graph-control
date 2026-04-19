<?php
declare(strict_types=1);

namespace EvzenLeonenko\OpenGraphControl\Tests\Unit\Resolvers;

use Brain\Monkey;
use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use EvzenLeonenko\OpenGraphControl\Options\Repository as OptionsRepository;
use EvzenLeonenko\OpenGraphControl\Resolvers\Context;
use EvzenLeonenko\OpenGraphControl\Resolvers\Locale;
use PHPUnit\Framework\TestCase;

final class LocaleTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	private function resolver( ?string $site_locale = null ): Locale {
		$opt_repo = $this->createStub( OptionsRepository::class );
		$opt_repo->method( 'get_path' )->willReturn( $site_locale ?? '' );
		return new Locale( $opt_repo );
	}

	public function test_site_setting_wins(): void {
		Filters\expectApplied( 'ogc_resolve_locale_value' )->andReturnFirstArg();
		self::assertSame( 'cs_CZ', $this->resolver( 'cs_CZ' )->resolve( Context::for_front() ) );
	}

	public function test_falls_back_to_get_locale(): void {
		Functions\when( 'get_locale' )->justReturn( 'en_US' );
		Filters\expectApplied( 'ogc_resolve_locale_value' )->andReturnFirstArg();
		self::assertSame( 'en_US', $this->resolver()->resolve( Context::for_front() ) );
	}

	public function test_normalizes_hyphen_to_underscore(): void {
		Filters\expectApplied( 'ogc_resolve_locale_value' )->andReturnFirstArg();
		self::assertSame( 'en_US', $this->resolver( 'en-US' )->resolve( Context::for_front() ) );
	}
}
