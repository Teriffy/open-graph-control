import { Page, expect } from '@playwright/test';

/** wp-env defaults. */
export const ADMIN = { username: 'admin', password: 'password' };

export async function login( page: Page, user = ADMIN ) {
	await page.goto( '/wp-login.php' );
	await page.fill( '#user_login', user.username );
	await page.fill( '#user_pass', user.password );
	await Promise.all( [
		page.waitForURL( /\/wp-admin\// ),
		page.click( '#wp-submit' ),
	] );
	await expect( page ).toHaveURL( /\/wp-admin\// );
}

export async function activatePlugin( page: Page, slug: string ) {
	await page.goto( '/wp-admin/plugins.php' );
	const row = page.locator( `tr[data-slug="${ slug }"]` );
	if ( await row.locator( '.activate a' ).isVisible() ) {
		await row.locator( '.activate a' ).click();
	}
}

export async function gotoSettings( page: Page ) {
	await page.goto( '/wp-admin/admin.php?page=open-graph-control' );
	await expect( page.locator( '#ogc-settings-root' ) ).toBeVisible();
}
