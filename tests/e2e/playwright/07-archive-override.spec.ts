import { expect, test } from '@playwright/test';
import { login } from './helpers';

/**
 * Grab the REST nonce that WordPress bootstraps on every admin page via
 * `wpApiSettings`. Requires the page to currently be on a /wp-admin/* URL.
 */
async function getRestNonce( page ): Promise< string > {
	return page.evaluate(
		() =>
			( window as unknown as {
				wpApiSettings?: { nonce?: string };
			} ).wpApiSettings?.nonce ?? ''
	);
}

test( 'category override renders on the category archive', async ( {
	page,
} ) => {
	await login( page );

	// Ensure we're on an admin page so `wpApiSettings.nonce` is available.
	await page.goto( '/wp-admin/' );
	const nonce = await getRestNonce( page );
	expect( nonce ).not.toBe( '' );

	// Create a category via REST (faster than UI).
	const slug = `e2e-recepty-${ Date.now() }`;
	const catRes = await page.request.post(
		'/wp-json/wp/v2/categories',
		{
			headers: { 'X-WP-Nonce': nonce },
			data: { name: 'E2E Recepty', slug },
		}
	);
	expect( catRes.ok() ).toBeTruthy();
	const category = await catRes.json();
	const termId: number = category.id;

	// Open the term edit screen and confirm the mount is present.
	await page.goto(
		`/wp-admin/term.php?taxonomy=category&tag_ID=${ termId }`
	);
	await expect( page.locator( '#ogc-archive-root' ) ).toBeVisible();

	// Fill OG title in the injected React editor.
	const title = 'Recepty z české spíže';
	await page
		.locator( '#ogc-archive-root input[type="text"]' )
		.first()
		.fill( title );
	await page.click(
		'#ogc-archive-root button:has-text("Save overrides")'
	);
	await expect(
		page.locator( '#ogc-archive-root' ).getByText( 'Saved.' )
	).toBeVisible( { timeout: 10_000 } );

	// Create at least one published post so the category archive renders.
	const postRes = await page.request.post( '/wp-json/wp/v2/posts', {
		headers: { 'X-WP-Nonce': nonce },
		data: {
			title: 'E2E archive test post',
			status: 'publish',
			categories: [ termId ],
		},
	} );
	expect( postRes.ok() ).toBeTruthy();

	// Visit the archive front-end.
	await page.goto( `/?cat=${ termId }` );
	const source = await page.content();
	expect( source ).toContain( title );
	expect( source ).toContain( 'property="og:title"' );
} );

test( 'author override renders on the author archive', async ( {
	page,
} ) => {
	await login( page );
	await page.goto( '/wp-admin/profile.php' );

	await expect( page.locator( '#ogc-archive-root' ) ).toBeVisible();

	const title = 'Evžen Leonenko — kód z první ruky';
	await page
		.locator( '#ogc-archive-root input[type="text"]' )
		.first()
		.fill( title );
	await page.click(
		'#ogc-archive-root button:has-text("Save overrides")'
	);
	await expect(
		page.locator( '#ogc-archive-root' ).getByText( 'Saved.' )
	).toBeVisible( { timeout: 10_000 } );

	// Visit the author archive front-end. admin is user id 1 on wp-env.
	await page.goto( '/?author=1' );
	const source = await page.content();
	expect( source ).toContain( title );
	expect( source ).toContain( 'property="og:title"' );
} );
