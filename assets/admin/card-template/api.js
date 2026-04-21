const root = ( window.OGC_BOOT?.apiUrl || '/wp-json/open-graph-control/v1/' ) + 'og-card/';
const headers = () => ( {
	'Content-Type': 'application/json',
	'X-WP-Nonce': window.OGC_BOOT?.nonce || '',
} );

export const api = {
	get: () => fetch( root + 'template', { headers: headers() } ).then( ( r ) => r.json() ),
	put: ( t ) =>
		fetch( root + 'template', {
			method: 'PUT',
			headers: headers(),
			body: JSON.stringify( t ),
		} ).then( ( r ) => r.json() ),
	preview: ( body ) =>
		fetch( root + 'preview', {
			method: 'POST',
			headers: headers(),
			body: JSON.stringify( body ),
		} ).then( ( r ) => r.json() ),
	regenAll: () =>
		fetch( root + 'regenerate', {
			method: 'POST',
			headers: headers(),
			body: JSON.stringify( { scope: 'all' } ),
		} ).then( ( r ) => r.json() ),
};
