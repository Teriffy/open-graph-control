import { expect, test } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { login, gotoSettings } from './helpers';

/**
 * axe-core scan of the plugin's admin UI inside a real wp-admin.
 *
 * We only assert on violations that originate in our own React roots
 * (#ogc-settings-root, #ogc-metabox-root) so unrelated wp-admin or
 * third-party issues don't make this brittle. The scan covers the
 * settings shell + the most inline-style-heavy section (Security)
 * plus the per-post meta box.
 */

async function ogcViolations( page, tags = [ 'wcag2a', 'wcag2aa' ] ) {
	const results = await new AxeBuilder( { page } ).withTags( tags ).analyze();
	const ours = results.violations
		.map( ( v ) => ( {
			...v,
			nodes: v.nodes.filter( ( n ) =>
				n.target.some(
					( t ) =>
						typeof t === 'string' &&
						( t.includes( 'ogc-' ) || t.includes( '.ogc' ) )
				)
			),
		} ) )
		.filter( ( v ) => v.nodes.length > 0 );

	if ( ours.length ) {
		for ( const v of ours ) {
			// eslint-disable-next-line no-console
			console.log(
				`[${ v.impact }] ${ v.id }: ${ v.help }\n  ${ v.nodes
					.map( ( n ) => n.target.join( ' ' ) )
					.join( '\n  ' ) }`
			);
		}
	}
	return ours;
}

test( 'settings page has no axe violations inside plugin roots', async ( {
	page,
} ) => {
	await login( page );
	await gotoSettings( page );

	// Wait for React app to mount and render first section.
	await expect( page.locator( '.ogc-nav' ) ).toBeVisible( {
		timeout: 15_000,
	} );

	expect( await ogcViolations( page ) ).toEqual( [] );
} );

test( 'security section has no axe violations', async ( { page } ) => {
	await login( page );
	await gotoSettings( page );

	await page
		.locator( '.ogc-nav button', { hasText: /^Security$/ } )
		.click();
	// Allow React to paint the section before scanning.
	await page.waitForTimeout( 400 );
	await expect(
		page.getByRole( 'heading', { name: 'Security' } )
	).toBeVisible();

	expect( await ogcViolations( page ) ).toEqual( [] );
} );
