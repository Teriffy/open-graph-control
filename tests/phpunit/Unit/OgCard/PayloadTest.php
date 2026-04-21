<?php
declare(strict_types=1);

namespace EvzenLeonenko\OpenGraphControl\Tests\Unit\OgCard;

use EvzenLeonenko\OpenGraphControl\OgCard\Payload;
use PHPUnit\Framework\TestCase;

final class PayloadTest extends TestCase {

	public function test_construct_strips_html_from_title(): void {
		$p = new Payload( title: '<b>Hi</b>', description: 'Plain', site_name: 'X', url: 'https://x.test', meta_line: 'today' );
		$this->assertSame( 'Hi', $p->title );
	}

	public function test_construct_strips_html_from_description(): void {
		$p = new Payload( title: 'T', description: '<em>Wow</em>', site_name: 'X', url: 'https://x.test', meta_line: 'today' );
		$this->assertSame( 'Wow', $p->description );
	}

	public function test_construct_collapses_whitespace(): void {
		$p = new Payload( title: "Long\n\ntitle  with   spaces", description: 'd', site_name: 'X', url: 'https://x.test', meta_line: '' );
		$this->assertSame( 'Long title with spaces', $p->title );
	}

	public function test_construct_throws_on_empty_title(): void {
		$this->expectException( \InvalidArgumentException::class );
		new Payload( title: '   ', description: 'd', site_name: 'X', url: 'https://x.test', meta_line: '' );
	}

	public function test_to_array_roundtrip(): void {
		$p = new Payload( title: 'T', description: 'D', site_name: 'S', url: 'https://x.test', meta_line: 'M' );
		$this->assertSame(
			[
				'title'       => 'T',
				'description' => 'D',
				'site_name'   => 'S',
				'url'         => 'https://x.test',
				'meta_line'   => 'M',
			],
			$p->to_array()
		);
	}
}
