import { expect, test } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

test( 'preview fixtures have no axe-core violations', async ( { page } ) => {
	await page.goto( '/preview.html' );
	const results = await new AxeBuilder( { page } )
		.withTags( [ 'wcag2a', 'wcag2aa' ] )
		.analyze();

	if ( results.violations.length ) {
		// Pretty-print violations so CI logs are useful.
		for ( const v of results.violations ) {
			// eslint-disable-next-line no-console
			console.log(
				`[${ v.impact }] ${ v.id }: ${ v.help }\n  ${ v.nodes
					.map( ( n ) => n.target.join( ' ' ) )
					.join( '\n  ' ) }`
			);
		}
	}

	expect( results.violations ).toEqual( [] );
} );
