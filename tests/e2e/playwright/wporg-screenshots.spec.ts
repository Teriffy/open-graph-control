import { test, expect } from '@playwright/test';
import { login, gotoSettings } from './helpers';
import path from 'node:path';

/**
 * Regenerates the six PNG screenshots described in readme.txt's
 * "== Screenshots ==" section. Output lands in .wordpress-org/ which
 * the release workflow uploads to the wp.org SVN `assets/` subtree.
 *
 * Gated behind OGC_WPORG_SCREENSHOTS=1 so it doesn't run in the default
 * OGC_E2E_WP=1 suite. Invoke via `bash bin/make-screenshots.sh`.
 */

const SHOULD_RUN = process.env.OGC_WPORG_SCREENSHOTS === '1';
const OUT = path.resolve( __dirname, '../../../.wordpress-org' );

test.describe.configure( { mode: 'serial' } );

test.use( { viewport: { width: 1280, height: 1000 } } );

test( 'screenshot-1: debug tag output', async ( { page } ) => {
	test.skip( ! SHOULD_RUN, 'Opt in with OGC_WPORG_SCREENSHOTS=1' );
	await login( page );
	await gotoSettings( page );
	await page
		.locator( '.ogc-nav button', { hasText: /^Debug \/ Test$/ } )
		.click();
	await page.waitForTimeout( 300 );
	await page.click( 'button:has-text("Render tags")' );
	await page.waitForSelector( '.wp-list-table tbody tr', { timeout: 5000 } );
	await page.screenshot( {
		path: path.join( OUT, 'screenshot-1.png' ),
		fullPage: true,
	} );
} );

test( 'screenshot-2: platforms section', async ( { page } ) => {
	test.skip( ! SHOULD_RUN, 'Opt in with OGC_WPORG_SCREENSHOTS=1' );
	await login( page );
	await gotoSettings( page );
	await page
		.locator( '.ogc-nav button', { hasText: /^Platforms$/ } )
		.click();
	await page.waitForTimeout( 400 );
	const toggles = page.locator(
		'.components-toggle-control input[type="checkbox"]'
	);
	const count = await toggles.count();
	for ( let i = 0; i < Math.min( count, 4 ); i++ ) {
		const t = toggles.nth( i );
		if ( ! ( await t.isChecked() ) ) {
			await t.click();
		}
	}
	await page.waitForTimeout( 200 );
	await page.screenshot( {
		path: path.join( OUT, 'screenshot-2.png' ),
		fullPage: true,
	} );
} );

test( 'screenshot-3: metabox with preview', async ( { page } ) => {
	test.skip( ! SHOULD_RUN, 'Opt in with OGC_WPORG_SCREENSHOTS=1' );
	await login( page );
	await page.goto( '/wp-admin/post-new.php?post_type=post' );
	const closeGuide = page.locator( 'button[aria-label="Close"]' ).first();
	if ( await closeGuide.isVisible() ) {
		await closeGuide.click();
	}
	await page
		.locator( '.editor-post-title__input, h1.wp-block-post-title' )
		.first()
		.fill( 'How to ship a WordPress plugin' );
	await page.locator( '#ogc-metabox-root' ).scrollIntoViewIfNeeded();
	await page
		.locator( '#ogc-metabox-root input[type="text"]' )
		.first()
		.fill( 'How to ship a WordPress plugin — a practical guide' );
	await page
		.locator( '#ogc-metabox-root textarea' )
		.first()
		.fill(
			'A step-by-step walkthrough of distributing a plugin via WordPress.org: checks, screenshots, SVN, assets, and security.'
		);
	await page.waitForTimeout( 800 );
	await page.locator( '#ogc-metabox-root' ).screenshot( {
		path: path.join( OUT, 'screenshot-3.png' ),
	} );
} );

test( 'screenshot-4: security section', async ( { page } ) => {
	test.skip( ! SHOULD_RUN, 'Opt in with OGC_WPORG_SCREENSHOTS=1' );
	await login( page );
	await gotoSettings( page );
	await page
		.locator( '.ogc-nav button', { hasText: /^Security$/ } )
		.click();
	await page.waitForTimeout( 400 );
	await page.screenshot( {
		path: path.join( OUT, 'screenshot-4.png' ),
		fullPage: true,
	} );
} );

test( 'screenshot-5: archive editor on term edit', async ( { page } ) => {
	test.skip( ! SHOULD_RUN, 'Opt in with OGC_WPORG_SCREENSHOTS=1' );
	await login( page );

	// Create a category to edit.
	await page.goto( '/wp-admin/' );
	const nonce = await page.evaluate(
		() =>
			( window as unknown as { wpApiSettings?: { nonce: string } } )
				.wpApiSettings?.nonce
	);
	const res = await page.request.post( '/wp-json/wp/v2/categories', {
		headers: { 'X-WP-Nonce': nonce ?? '' },
		data: {
			name: 'Recepty',
			slug: `recepty-screenshot-${ Date.now() }`,
		},
	} );
	const category = await res.json();

	await page.goto(
		`/wp-admin/term.php?taxonomy=category&tag_ID=${ category.id }`
	);
	await expect( page.locator( '#ogc-archive-root' ) ).toBeVisible();

	// Fill demo values for a good-looking screenshot.
	await page
		.locator( '#ogc-archive-root input[type="text"]' )
		.first()
		.fill( 'Recepty z české spíže' );
	await page
		.locator( '#ogc-archive-root textarea' )
		.first()
		.fill(
			'Odzkoušené recepty z ingrediencí dostupných v běžné české samoobsluze.'
		);
	await page.waitForTimeout( 300 );

	await page.screenshot( {
		path: path.join( OUT, 'screenshot-5.png' ),
		fullPage: true,
	} );
} );

test( 'screenshot-6: card template settings', async ( { page } ) => {
	test.skip( ! SHOULD_RUN, 'Opt in with OGC_WPORG_SCREENSHOTS=1' );
	await login( page );
	await gotoSettings( page );
	await page
		.locator( '.ogc-nav button', { hasText: /^Images$/ } )
		.click();
	await page.waitForTimeout( 400 );

	// Scroll to the Card template section and take a screenshot.
	const cardSection = page.locator( 'h2', { hasText: /Card template/ } );
	await cardSection.scrollIntoViewIfNeeded();
	await page.waitForTimeout( 300 );

	await page.screenshot( {
		path: path.join( OUT, 'screenshot-6.png' ),
		fullPage: true,
	} );
} );
