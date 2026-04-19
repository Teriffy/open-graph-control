import { useEffect, useMemo, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	Notice,
	SelectControl,
	Spinner,
	TextControl,
} from '@wordpress/components';

import { api } from '../../shared/api.js';

/**
 * Flatten the REST payload ({ terms, users }) into a single row list so the
 * table, search, and kind filter can all operate on one array.
 *
 * @param {?{terms:Array, users:Array}} data Raw response from the endpoint.
 * @return {Array} Normalised rows with kind/editUrl pre-computed.
 */
function buildRows( data ) {
	if ( ! data ) {
		return [];
	}
	const terms = ( data.terms || [] ).map( ( t ) => ( {
		...t,
		kind: t.tax,
		displayKind: t.tax,
		editUrl: `edit-tags.php?taxonomy=${ encodeURIComponent(
			t.tax
		) }&tag_ID=${ t.term_id }`,
	} ) );
	const users = ( data.users || [] ).map( ( u ) => ( {
		...u,
		kind: 'user',
		displayKind: 'user',
		editUrl: `user-edit.php?user_id=${ u.user_id }`,
	} ) );
	return [ ...terms, ...users ];
}

export default function ArchiveOverrides() {
	const [ data, setData ] = useState( null );
	const [ error, setError ] = useState( null );
	const [ search, setSearch ] = useState( '' );
	const [ kind, setKind ] = useState( 'all' );

	useEffect( () => {
		api.archive
			.listOverrides()
			.then( setData )
			.catch( ( err ) => setError( err.message ) );
	}, [] );

	const rows = useMemo( () => buildRows( data ), [ data ] );

	const kindOptions = useMemo(
		() => [
			{ label: __( 'All kinds', 'open-graph-control' ), value: 'all' },
			...Array.from( new Set( rows.map( ( r ) => r.kind ) ) ).map(
				( k ) => ( { label: k, value: k } )
			),
		],
		[ rows ]
	);

	const filtered = useMemo( () => {
		const needle = search.trim().toLowerCase();
		return rows.filter( ( r ) => {
			if ( kind !== 'all' && r.kind !== kind ) {
				return false;
			}
			if ( needle && ! r.name.toLowerCase().includes( needle ) ) {
				return false;
			}
			return true;
		} );
	}, [ rows, kind, search ] );

	if ( error ) {
		return (
			<div className="ogc-section-archive-overrides">
				<h2>{ __( 'Archive overrides', 'open-graph-control' ) }</h2>
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			</div>
		);
	}

	if ( ! data ) {
		return (
			<div className="ogc-section-archive-overrides">
				<h2>{ __( 'Archive overrides', 'open-graph-control' ) }</h2>
				<Spinner />
			</div>
		);
	}

	return (
		<div className="ogc-section-archive-overrides">
			<h2>{ __( 'Archive overrides', 'open-graph-control' ) }</h2>
			<p>
				{ __(
					'Every category, tag, custom taxonomy term, or author with an Open Graph override set. Follow “Edit →” to jump to the native WordPress edit screen.',
					'open-graph-control'
				) }
			</p>

			{ rows.length === 0 && (
				<p className="ogc-muted">
					{ __(
						'No archives configured yet. Set one up on a category, tag, or author edit screen.',
						'open-graph-control'
					) }{ ' ' }
					<a href="edit-tags.php?taxonomy=category">
						{ __(
							'Open the Categories screen →',
							'open-graph-control'
						) }
					</a>
				</p>
			) }

			{ rows.length > 0 && (
				<>
					<div
						className="ogc-toolbar"
						style={ { alignItems: 'flex-end' } }
					>
						<TextControl
							label={ __(
								'Search by name',
								'open-graph-control'
							) }
							value={ search }
							onChange={ setSearch }
						/>
						<SelectControl
							label={ __( 'Kind', 'open-graph-control' ) }
							value={ kind }
							options={ kindOptions }
							onChange={ setKind }
						/>
					</div>

					<table className="wp-list-table widefat striped">
						<thead>
							<tr>
								<th>{ __( 'Kind', 'open-graph-control' ) }</th>
								<th>{ __( 'Name', 'open-graph-control' ) }</th>
								<th>
									{ __( 'Fields set', 'open-graph-control' ) }
								</th>
								<th>
									{ __( 'Action', 'open-graph-control' ) }
								</th>
							</tr>
						</thead>
						<tbody>
							{ filtered.length === 0 && (
								<tr>
									<td colSpan={ 4 } className="ogc-muted">
										{ __(
											'No overrides match the current filters.',
											'open-graph-control'
										) }
									</td>
								</tr>
							) }
							{ filtered.map( ( row ) => {
								const rowKey =
									row.kind === 'user'
										? `user-${ row.user_id }`
										: `${ row.tax }-${ row.term_id }`;
								return (
									<tr key={ rowKey }>
										<td>
											<code>{ row.displayKind }</code>
										</td>
										<td>{ row.name }</td>
										<td>
											{ ( row.fields_set || [] ).map(
												( f ) => (
													<span
														key={ f }
														className={ `ogc-pill${
															f === 'exclude'
																? ' ogc-pill--error'
																: ''
														}` }
													>
														{ f }
													</span>
												)
											) }
										</td>
										<td>
											<a href={ row.editUrl }>
												{ __(
													'Edit →',
													'open-graph-control'
												) }
											</a>
										</td>
									</tr>
								);
							} ) }
						</tbody>
					</table>
				</>
			) }
		</div>
	);
}
