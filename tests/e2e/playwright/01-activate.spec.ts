import { expect, test } from '@playwright/test';
import { login, activatePlugin, gotoSettings } from './helpers';

test( 'plugin activates without fatal and surfaces admin menu', async ( {
	page,
} ) => {
	await login( page );
	await activatePlugin( page, 'open-graph-control' );

	// Admin menu item visible.
	await expect(
		page.locator( '#adminmenu a:has-text("Open Graph Control")' )
	).toBeVisible();

	// Settings page loads, React root renders.
	await gotoSettings( page );
	await expect( page.locator( 'h1' ) ).toContainText( 'Open Graph Control' );
} );
