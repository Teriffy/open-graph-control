const root = window.OGC_BOOT?.apiUrl + 'field-sources/';
const headers = {
	'Content-Type': 'application/json',
	'X-WP-Nonce': window.OGC_BOOT?.nonce,
};

export const api = {
	getMapping: () => fetch( root + 'mapping', { headers } ).then( ( r ) => r.json() ),
	putMapping: ( m ) =>
		fetch( root + 'mapping', {
			method: 'PUT',
			headers,
			body: JSON.stringify( m ),
		} ).then( ( r ) => r.json() ),
	getFields: ( plugin, postType ) =>
		fetch( `${ root }${ plugin }/fields?post_type=${ encodeURIComponent( postType ) }`, { headers } ).then( ( r ) => r.json() ),
};
