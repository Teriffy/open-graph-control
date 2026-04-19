import { expect, test } from '@playwright/test';
import { login, gotoSettings } from './helpers';

test( 'site name saved via Settings persists across reload', async ( {
	page,
} ) => {
	await login( page );
	await gotoSettings( page );

	// React app mounts under #ogc-settings-root. Wait for sidebar nav to render.
	await expect( page.locator( '.ogc-nav' ) ).toBeVisible( {
		timeout: 15_000,
	} );

	// Switch to Site defaults section and wait for its heading.
	await page
		.locator( '.ogc-nav button', { hasText: /^Site defaults$/ } )
		.click();
	// Give React a moment to re-render the section.
	await page.waitForTimeout( 500 );
	await expect(
		page.getByRole( 'heading', { name: 'Site defaults' } )
	).toBeVisible( { timeout: 10_000 } );

	// Fill the first input under Site defaults section.
	const input = page
		.locator( '.ogc-section-site-defaults input' )
		.first();
	await expect( input ).toBeVisible();
	await input.fill( 'E2E Test Site' );

	await page
		.locator( 'main button', { hasText: 'Save changes' } )
		.click();
	await expect( page.getByText( 'Saved.' ) ).toBeVisible( {
		timeout: 10_000,
	} );

	await page.reload();
	await expect( page.locator( '.ogc-nav' ) ).toBeVisible( {
		timeout: 15_000,
	} );
	await page
		.locator( '.ogc-nav button', { hasText: /^Site defaults$/ } )
		.click();
	// Give React a moment to re-render the section.
	await page.waitForTimeout( 500 );
	await expect(
		page.locator( 'input[value="E2E Test Site"]' )
	).toBeVisible( { timeout: 10_000 } );
} );
