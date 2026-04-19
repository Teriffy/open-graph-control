import { __ } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import {
	Button,
	Card,
	CardBody,
	Notice,
	SelectControl,
	Spinner,
	TextControl,
} from '@wordpress/components';

import { api } from '../../shared/api.js';

const CONTEXTS = [
	{ label: 'Singular post', value: 'singular' },
	{ label: 'Front page', value: 'front' },
	{ label: 'Blog index', value: 'blog' },
	{ label: 'Archive', value: 'archive' },
	{ label: 'Author', value: 'author' },
	{ label: 'Date', value: 'date' },
];

export default function Debug() {
	const [ postId, setPostId ] = useState( '' );
	const [ context, setContext ] = useState( 'front' );
	const [ state, setState ] = useState( { kind: 'idle' } );
	const [ result, setResult ] = useState( null );

	const run = async () => {
		setState( { kind: 'loading' } );
		try {
			const resp = await api.preview( {
				post_id: parseInt( postId, 10 ) || 0,
				context,
			} );
			setResult( resp );
			setState( { kind: 'ok' } );
		} catch ( err ) {
			setState( { kind: 'error', message: err.message } );
		}
	};

	return (
		<div className="ogc-section-debug">
			<h2>{ __( 'Debug / Test', 'open-graph-control' ) }</h2>
			<Card>
				<CardBody>
					<SelectControl
						label={ __( 'Context', 'open-graph-control' ) }
						value={ context }
						options={ CONTEXTS }
						onChange={ ( v ) => setContext( v ) }
					/>
					{ context === 'singular' && (
						<TextControl
							label={ __( 'Post ID', 'open-graph-control' ) }
							value={ postId }
							onChange={ ( v ) => setPostId( v ) }
							type="number"
						/>
					) }
					<Button variant="primary" onClick={ run }>
						{ __( 'Render tags', 'open-graph-control' ) }
					</Button>

					{ state.kind === 'loading' && <Spinner /> }
					{ state.kind === 'error' && (
						<Notice status="error" isDismissible={ false }>
							{ state.message }
						</Notice>
					) }
				</CardBody>
			</Card>

			{ state.kind === 'ok' && result && (
				<Card>
					<CardBody>
						<h3 style={ { marginTop: 0 } }>
							{ __( 'Emitted meta tags', 'open-graph-control' ) }
						</h3>
						<table className="wp-list-table widefat striped">
							<thead>
								<tr>
									<th style={ { width: '25%' } }>
										{ __( 'Key', 'open-graph-control' ) }
									</th>
									<th>
										{ __(
											'Content',
											'open-graph-control'
										) }
									</th>
								</tr>
							</thead>
							<tbody>
								{ Object.entries( result.tags || {} ).map(
									( [ key, value ] ) => (
										<tr key={ key }>
											<td>
												<code>{ key }</code>
											</td>
											<td>{ value }</td>
										</tr>
									)
								) }
							</tbody>
						</table>

						{ result.json_ld?.length > 0 && (
							<>
								<h3>
									{ __(
										'JSON-LD payload',
										'open-graph-control'
									) }
								</h3>
								<pre
									style={ {
										background: '#f0f0f1',
										padding: '0.75rem',
										overflow: 'auto',
										maxHeight: '20rem',
									} }
								>
									{ result.json_ld.join( '\n\n' ) }
								</pre>
							</>
						) }
					</CardBody>
				</Card>
			) }

			<Card>
				<CardBody>
					<h3 style={ { marginTop: 0 } }>
						{ __( 'External validators', 'open-graph-control' ) }
					</h3>
					<p>
						{ __(
							'Validate the rendered tags against the platform\u2019s own tools:',
							'open-graph-control'
						) }
					</p>
					<ul>
						<li>
							<a
								href="https://developers.facebook.com/tools/debug/"
								target="_blank"
								rel="noreferrer"
							>
								Facebook Sharing Debugger
							</a>
						</li>
						<li>
							<a
								href="https://cards-dev.twitter.com/validator"
								target="_blank"
								rel="noreferrer"
							>
								X / Twitter Card Validator
							</a>
						</li>
						<li>
							<a
								href="https://www.linkedin.com/post-inspector/inspect"
								target="_blank"
								rel="noreferrer"
							>
								LinkedIn Post Inspector
							</a>
						</li>
						<li>
							<a
								href="https://developers.pinterest.com/tools/url-debugger/"
								target="_blank"
								rel="noreferrer"
							>
								Pinterest Rich Pin Validator
							</a>
						</li>
					</ul>
				</CardBody>
			</Card>
		</div>
	);
}
