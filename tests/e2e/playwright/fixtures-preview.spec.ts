import { expect, test } from '@playwright/test';

const PLATFORMS = [
	'facebook',
	'twitter',
	'linkedin',
	'imessage',
	'threads',
	'mastodon',
	'bluesky',
	'whatsapp',
	'discord',
	'pinterest',
	'telegram',
	'slack',
] as const;

test.describe( 'Preview card fixtures', () => {
	test.beforeEach( async ( { page } ) => {
		await page.goto( '/preview.html' );
	} );

	test( 'renders all 12 platform cards', async ( { page } ) => {
		for ( const slug of PLATFORMS ) {
			await expect(
				page.locator( `[data-platform="${ slug }"]` )
			).toBeVisible();
		}
	} );

	for ( const slug of PLATFORMS ) {
		test( `${ slug } card has text and image`, async ( { page } ) => {
			const card = page.locator( `[data-platform="${ slug }"]` );
			// Title placement varies by card layout (Telegram puts the post
			// title in .desc and uses .title for the site name). Assert the
			// post title appears somewhere inside the card.
			await expect( card ).toContainText(
				'How I ship WordPress plugins'
			);
			await expect( card.locator( '.ogc-preview-img' ) ).toBeVisible();
		} );
	}

	test( 'twitter card has domain overlay', async ( { page } ) => {
		const card = page.locator( '[data-platform="twitter"]' );
		await expect( card.locator( '.domain' ) ).toHaveText( 'example.com' );
	} );

	test( 'discord card uses dark background', async ( { page } ) => {
		const card = page.locator( '[data-platform="discord"] .ogc-preview-card' );
		const bg = await card.evaluate(
			( el ) => window.getComputedStyle( el ).backgroundColor
		);
		// #2b2d31 rendered as rgb.
		expect( bg ).toBe( 'rgb(43, 45, 49)' );
	} );

	test( 'pinterest card is vertical', async ( { page } ) => {
		const img = page.locator(
			'[data-platform="pinterest"] .ogc-preview-img'
		);
		const box = await img.boundingBox();
		expect( box ).not.toBeNull();
		if ( box ) {
			expect( box.height ).toBeGreaterThan( box.width );
		}
	} );

	test( 'whatsapp card embeds title in green bubble', async ( { page } ) => {
		const card = page.locator( '[data-platform="whatsapp"] .ogc-preview-card' );
		const bg = await card.evaluate(
			( el ) => window.getComputedStyle( el ).backgroundColor
		);
		// #dcf8c6 rendered as rgb.
		expect( bg ).toBe( 'rgb(220, 248, 198)' );
	} );
} );
