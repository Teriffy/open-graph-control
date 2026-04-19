import { expect, test } from '@playwright/test';

test.describe( 'Preview card visual snapshots', () => {
	test.beforeEach( async ( { page } ) => {
		await page.setViewportSize( { width: 1280, height: 1600 } );
		await page.goto( '/preview.html' );
		// Wait for all cards to be laid out.
		await page.waitForLoadState( 'networkidle' );
	} );

	test( 'all-cards grid', async ( { page } ) => {
		await expect( page.locator( '.grid' ) ).toHaveScreenshot(
			'all-cards.png',
			{ maxDiffPixelRatio: 0.02 }
		);
	} );
} );
