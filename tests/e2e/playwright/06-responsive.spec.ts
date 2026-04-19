import { expect, test } from '@playwright/test';
import { login, gotoSettings } from './helpers';

/**
 * Lock-in test for the mobile wp-admin layout (WP's breakpoint is 782px).
 * Verifies that at a narrow viewport:
 *  - The settings layout stacks vertically (nav above content) instead of
 *    side-by-side, and nav-item touch targets are ≥ 44px.
 *  - The metabox's preview pane expands to full width instead of the
 *    desktop 320px fixed sidebar that pushed the form offscreen.
 */

const MOBILE_VIEWPORT = { width: 414, height: 896 };

test( 'settings layout stacks and nav gets touch-sized targets at ≤782px', async ( {
	page,
} ) => {
	await page.setViewportSize( MOBILE_VIEWPORT );
	await login( page );
	await gotoSettings( page );

	const layout = page.locator( '.ogc-layout' );
	await expect( layout ).toBeVisible();

	const direction = await layout.evaluate(
		( el ) => getComputedStyle( el ).flexDirection
	);
	expect( direction ).toBe( 'column' );

	const navItemHeight = await page
		.locator( '.ogc-nav .ogc-nav-item' )
		.first()
		.evaluate( ( el ) => el.getBoundingClientRect().height );
	expect( navItemHeight ).toBeGreaterThanOrEqual( 44 );
} );

test( 'metabox preview pane stacks below the form at ≤782px', async ( {
	page,
} ) => {
	await page.setViewportSize( MOBILE_VIEWPORT );
	await login( page );
	await page.goto( '/wp-admin/post-new.php?post_type=post' );

	const closeGuide = page.locator( 'button[aria-label="Close"]' ).first();
	if ( await closeGuide.isVisible() ) {
		await closeGuide.click();
	}
	await page
		.locator( '.editor-post-title__input, h1.wp-block-post-title' )
		.first()
		.fill( 'Mobile layout test' );
	await page.locator( '#ogc-metabox-root' ).scrollIntoViewIfNeeded();

	const metabox = page.locator( '#ogc-metabox-root .ogc-metabox' );
	await expect( metabox ).toBeVisible();

	const direction = await metabox.evaluate(
		( el ) => getComputedStyle( el ).flexDirection
	);
	expect( direction ).toBe( 'column' );

	const metaboxWidth = await metabox.evaluate(
		( el ) => el.getBoundingClientRect().width
	);
	const paneWidth = await page
		.locator( '#ogc-metabox-root .ogc-metabox__preview-pane' )
		.evaluate( ( el ) => el.getBoundingClientRect().width );

	// Preview pane should span (nearly) the full metabox width, not the
	// fixed 320px sidebar that fails on phones.
	expect( paneWidth ).toBeGreaterThan( metaboxWidth * 0.9 );
} );
