<?php
declare(strict_types=1);

namespace EvzenLeonenko\OpenGraphControl\Tests\Unit\Validation;

use EvzenLeonenko\OpenGraphControl\Validation\Validator;
use EvzenLeonenko\OpenGraphControl\Validation\Warning;
use PHPUnit\Framework\TestCase;

final class ValidatorTest extends TestCase {

	private function fieldSeverities( array $warnings ): array {
		$out = [];
		foreach ( $warnings as $w ) {
			$out[] = $w->field . ':' . $w->severity;
		}
		return $out;
	}

	public function test_empty_title_errors(): void {
		$warnings = ( new Validator() )->validate( [] );
		self::assertContains( 'title:error', $this->fieldSeverities( $warnings ) );
	}

	public function test_long_title_warns_then_errors(): void {
		$warn  = ( new Validator() )->validate( [ 'property:og:title' => str_repeat( 'a', 70 ) ] );
		$error = ( new Validator() )->validate( [ 'property:og:title' => str_repeat( 'a', 100 ) ] );
		self::assertContains( 'title:warn', $this->fieldSeverities( $warn ) );
		self::assertContains( 'title:error', $this->fieldSeverities( $error ) );
	}

	public function test_good_length_title_has_no_warning(): void {
		$warnings = ( new Validator() )->validate( [ 'property:og:title' => 'A reasonable title of 40 characters total' ] );
		self::assertNotContains( 'title:warn', $this->fieldSeverities( $warnings ) );
		self::assertNotContains( 'title:error', $this->fieldSeverities( $warnings ) );
	}

	public function test_long_description_errors_at_max(): void {
		$warn  = ( new Validator() )->validate(
			[
				'property:og:title'       => 'Ok',
				'property:og:description' => str_repeat( 'a', 150 ),
			]
		);
		$error = ( new Validator() )->validate(
			[
				'property:og:title'       => 'Ok',
				'property:og:description' => str_repeat( 'a', 210 ),
			]
		);
		self::assertContains( 'description:warn', $this->fieldSeverities( $warn ) );
		self::assertContains( 'description:error', $this->fieldSeverities( $error ) );
	}

	public function test_missing_image_warns(): void {
		$warnings = ( new Validator() )->validate(
			[
				'property:og:title'       => 'Ok',
				'property:og:description' => 'Ok',
			]
		);
		self::assertContains( 'image:warn', $this->fieldSeverities( $warnings ) );
	}

	public function test_twitter_handle_missing_at_sign_errors(): void {
		$warnings = ( new Validator() )->validate(
			[
				'property:og:title'       => 'Ok',
				'property:og:description' => 'Ok',
				'property:og:image'       => 'img.jpg',
			],
			[
				'platforms' => [
					'twitter' => [
						'site'    => 'example',
						'creator' => '@me',
					],
				],
			]
		);
		self::assertContains( 'twitter.site:error', $this->fieldSeverities( $warnings ) );
		self::assertNotContains( 'twitter.creator:error', $this->fieldSeverities( $warnings ) );
	}

	public function test_fediverse_creator_format_enforced(): void {
		$valid      = ( new Validator() )->validate(
			[
				'property:og:title'       => 'Ok',
				'property:og:description' => 'Ok',
				'property:og:image'       => 'i.jpg',
			],
			[ 'platforms' => [ 'mastodon' => [ 'fediverse_creator' => '@me@mastodon.social' ] ] ]
		);
		$invalid    = ( new Validator() )->validate(
			[
				'property:og:title'       => 'Ok',
				'property:og:description' => 'Ok',
				'property:og:image'       => 'i.jpg',
			],
			[ 'platforms' => [ 'mastodon' => [ 'fediverse_creator' => 'me' ] ] ]
		);
		$severities = $this->fieldSeverities( $valid );
		self::assertNotContains( 'platforms.mastodon.fediverse_creator:error', $severities );
		$severities_invalid = $this->fieldSeverities( $invalid );
		self::assertContains( 'platforms.mastodon.fediverse_creator:error', $severities_invalid );
	}

	public function test_discord_title_truncation_warn(): void {
		$warnings = ( new Validator() )->validate(
			[
				'property:og:title'       => str_repeat( 'A', 300 ),
				'property:og:description' => 'Ok',
				'property:og:image'       => 'img.jpg',
			],
			[ 'platforms' => [ 'discord' => [ 'enabled' => true ] ] ]
		);
		self::assertContains(
			'platforms.discord.title:warn',
			$this->fieldSeverities( $warnings )
		);
	}

	public function test_discord_checks_skip_when_platform_disabled(): void {
		$warnings = ( new Validator() )->validate(
			[
				'property:og:title'       => str_repeat( 'A', 300 ),
				'property:og:description' => 'Ok',
				'property:og:image'       => 'img.jpg',
			],
			[ 'platforms' => [ 'discord' => [ 'enabled' => false ] ] ]
		);
		self::assertNotContains(
			'platforms.discord.title:warn',
			$this->fieldSeverities( $warnings )
		);
	}

	public function test_linkedin_image_too_small_warn(): void {
		$warnings = ( new Validator() )->validate(
			[
				'property:og:title'        => 'Ok',
				'property:og:description'  => 'Ok',
				'property:og:image'        => 'img.jpg',
				'property:og:image:width'  => '800',
				'property:og:image:height' => '400',
			],
			[ 'platforms' => [ 'linkedin' => [ 'enabled' => true ] ] ]
		);
		self::assertContains(
			'platforms.linkedin.image:warn',
			$this->fieldSeverities( $warnings )
		);
	}

	public function test_linkedin_check_skipped_when_dimensions_missing(): void {
		$warnings = ( new Validator() )->validate(
			[
				'property:og:title'       => 'Ok',
				'property:og:description' => 'Ok',
				'property:og:image'       => 'img.jpg',
			],
			[ 'platforms' => [ 'linkedin' => [ 'enabled' => true ] ] ]
		);
		self::assertNotContains(
			'platforms.linkedin.image:warn',
			$this->fieldSeverities( $warnings )
		);
	}

	public function test_warning_to_array_shape(): void {
		$w = new Warning( Warning::WARN, 'foo', 'bar' );
		self::assertSame(
			[
				'severity' => 'warn',
				'field'    => 'foo',
				'message'  => 'bar',
			],
			$w->to_array()
		);
	}
}
