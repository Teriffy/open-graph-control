#!/usr/bin/env node
/**
 * Renders the wordpress.org banner + icon PNG assets from the SVG placeholders
 * in .wordpress-org/. WP.org's asset pipeline rejects SVG and WebP, so we ship
 * PNG in four sizes: 772x250 + 1544x500 banners and 128x128 + 256x256 icons.
 *
 * Usage: node bin/make-wporg-assets.mjs
 */

import { chromium } from 'playwright';
import { readFileSync, writeFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, resolve } from 'node:path';

const __dirname = dirname( fileURLToPath( import.meta.url ) );
const ROOT = resolve( __dirname, '..' );
const ASSETS = resolve( ROOT, '.wordpress-org' );

const targets = [
	{ svg: 'banner-placeholder.svg', out: 'banner-772x250.png', w: 772, h: 250 },
	{ svg: 'banner-placeholder.svg', out: 'banner-1544x500.png', w: 1544, h: 500 },
	{ svg: 'icon-placeholder.svg', out: 'icon-128x128.png', w: 128, h: 128 },
	{ svg: 'icon-placeholder.svg', out: 'icon-256x256.png', w: 256, h: 256 },
];

const browser = await chromium.launch();

for ( const { svg, out, w, h } of targets ) {
	const svgSource = readFileSync( resolve( ASSETS, svg ), 'utf8' );
	const html = `<!DOCTYPE html><html><head><style>
		html,body{margin:0;padding:0;background:transparent;}
		svg{display:block;width:${ w }px;height:${ h }px;}
	</style></head><body>${ svgSource }</body></html>`;

	const page = await browser.newPage( {
		viewport: { width: w, height: h },
		deviceScaleFactor: 1,
	} );
	await page.setContent( html, { waitUntil: 'load' } );
	const buf = await page.screenshot( {
		type: 'png',
		clip: { x: 0, y: 0, width: w, height: h },
		omitBackground: false,
	} );
	writeFileSync( resolve( ASSETS, out ), buf );
	console.log( `wrote ${ out } (${ buf.length } bytes)` );
	await page.close();
}

await browser.close();
