import { expect, test } from '@playwright/test';
import { login, gotoSettings } from './helpers';

test( 'site name saved via Settings persists across reload', async ( {
	page,
} ) => {
	await login( page );
	await gotoSettings( page );

	// Switch to Site defaults section.
	await page.click( 'button:has-text("Site defaults")' );

	const input = page.locator( 'input[type="text"]' ).first();
	await input.fill( 'E2E Test Site' );

	await page.click( 'button:has-text("Save changes")' );
	await expect( page.locator( 'text=Saved.' ) ).toBeVisible();

	// Reload, value is still there.
	await page.reload();
	await page.click( 'button:has-text("Site defaults")' );
	await expect( page.locator( 'input[value="E2E Test Site"]' ) ).toBeVisible();
} );
