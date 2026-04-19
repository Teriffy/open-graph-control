import { expect, test } from '@playwright/test';

test( 'homepage emits OG comment markers + core tags', async ( { page } ) => {
	await page.goto( '/' );
	const html = await page.content();

	expect( html ).toContain( '<!-- Open Graph Control' );
	expect( html ).toMatch(
		/<meta\s+property="og:title"\s+content="[^"]+"/
	);
	expect( html ).toMatch(
		/<meta\s+property="og:type"\s+content="[^"]+"/
	);
} );
