import { expect, test } from '@playwright/test';
import { login } from './helpers';

test( 'per-post title override appears in rendered OG tags', async ( {
	page,
} ) => {
	await login( page );

	// Create a new post.
	await page.goto( '/wp-admin/post-new.php?post_type=post' );

	// Close welcome guide / editor tips if present.
	const closeGuide = page.locator( 'button[aria-label="Close"]' ).first();
	if ( await closeGuide.isVisible() ) {
		await closeGuide.click();
	}

	// Add a minimal title.
	await page
		.locator( '.editor-post-title__input, h1.wp-block-post-title' )
		.first()
		.fill( 'E2E Default Title' );

	// Switch to classic meta boxes area (scroll to bottom).
	await page.locator( '#ogc-metabox-root' ).scrollIntoViewIfNeeded();

	// Wait for React meta box to render.
	await expect( page.locator( '#ogc-metabox-root' ) ).toBeVisible();

	// Fill the OG title override — first text input inside the meta box.
	const titleOverride = page
		.locator( '#ogc-metabox-root input[type="text"]' )
		.first();
	await titleOverride.fill( 'Custom OG Title' );

	// Save overrides + Publish post.
	await page.click( '#ogc-metabox-root button:has-text("Save overrides")' );
	await expect(
		page.locator( '#ogc-metabox-root' ).locator( 'text=Saved.' )
	).toBeVisible();

	// Publish.
	await page.click( 'button:has-text("Publish")' );
	await page
		.locator( '.editor-post-publish-panel button:has-text("Publish")' )
		.click();

	// Open the post on the frontend and check the OG title tag.
	await page
		.locator( '.post-publish-panel__postpublish-buttons a:has-text("View Post")' )
		.click();

	const source = await page.content();
	expect( source ).toContain( 'Custom OG Title' );
	expect( source ).toContain( 'property="og:title"' );
} );
