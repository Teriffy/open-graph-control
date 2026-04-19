/**
 * Boot payload injected by PHP (Admin\Assets::boot_payload()).
 *
 * @typedef {Object} Boot
 * @property {string}   apiUrl    Base REST URL including the plugin namespace.
 * @property {string}   nonce     WP REST nonce for the current session.
 * @property {string}   version   Plugin version string.
 * @property {string[]} platforms Enabled platform slugs surfaced to the UI.
 */

/** @type {Boot} */
const BOOT = ( typeof window !== 'undefined' && window.OGC_BOOT ) || {
	apiUrl: '/wp-json/open-graph-control/v1/',
	nonce: '',
	version: '0.0.0',
	platforms: [],
};

async function request( path, { method = 'GET', body } = {} ) {
	const url = BOOT.apiUrl.replace( /\/?$/, '/' ) + path.replace( /^\//, '' );
	const options = {
		method,
		credentials: 'same-origin',
		headers: {
			'Content-Type': 'application/json',
			'X-WP-Nonce': BOOT.nonce,
		},
	};
	if ( body !== undefined ) {
		options.body = JSON.stringify( body );
	}
	const response = await fetch( url, options );
	if ( ! response.ok ) {
		const error = new Error(
			`REST ${ method } ${ path } → ${ response.status }`
		);
		error.status = response.status;
		try {
			error.data = await response.json();
		} catch ( e ) {
			// Ignore — response wasn't JSON.
		}
		throw error;
	}
	return response.json();
}

export const boot = BOOT;

export const api = {
	getSettings: () => request( 'settings' ),
	saveSettings: ( patch ) =>
		request( 'settings', { method: 'POST', body: patch } ),
	preview: ( data ) => request( 'preview', { method: 'POST', body: data } ),
	conflicts: () => request( 'conflicts' ),
	postTypes: () => request( 'post-types' ),
	getPostMeta: ( postId ) => request( `meta/${ postId }` ),
	savePostMeta: ( postId, data ) =>
		request( `meta/${ postId }`, { method: 'POST', body: data } ),
	regenerateStart: () => request( 'images/regenerate', { method: 'POST' } ),
	regenerateStatus: () => request( 'images/regenerate' ),
	resetSettings: () => request( 'settings/reset', { method: 'POST' } ),
	archive: {
		getTerm: ( tax, id ) => request( `archive-meta/term/${ tax }/${ id }` ),
		saveTerm: ( tax, id, body ) =>
			request( `archive-meta/term/${ tax }/${ id }`, {
				method: 'POST',
				body,
			} ),
		getUser: ( id ) => request( `archive-meta/user/${ id }` ),
		saveUser: ( id, body ) =>
			request( `archive-meta/user/${ id }`, { method: 'POST', body } ),
		listOverrides: () => request( 'archive-overrides' ),
	},
};
