import { __ } from '@wordpress/i18n';
import {
	Button,
	Card,
	CardBody,
	CardHeader,
	SelectControl,
} from '@wordpress/components';
import { useState } from '@wordpress/element';

const FIELD_STEPS = {
	title: [
		'post_meta_override',
		'seo_plugin_title',
		'post_title',
		'site_name',
	],
	description: [
		'post_meta_override',
		'seo_plugin_desc',
		'post_excerpt',
		'post_content_trim',
		'site_description',
	],
	image: [
		'post_meta_override',
		'featured_image',
		'first_content_image',
		'first_block_image',
		'site_master_image',
	],
};

const STEP_LABELS = {
	post_meta_override: 'Post meta override',
	seo_plugin_title: 'SEO plugin title (via filter)',
	seo_plugin_desc: 'SEO plugin description (via filter)',
	post_title: 'Post title',
	post_excerpt: 'Post excerpt',
	post_content_trim: 'Trimmed post content (160 chars)',
	site_name: 'Site name (blog title)',
	site_description: 'Site description (tagline)',
	featured_image: 'Featured image',
	first_content_image: 'First <img> in content',
	first_block_image: 'First core/image block',
	site_master_image: 'Site master image',
};

function stepLabel( step ) {
	return STEP_LABELS[ step ] || step;
}

function Chain( { label, steps, available, onChange } ) {
	const [ toAdd, setToAdd ] = useState( '' );

	const missing = available.filter( ( step ) => ! steps.includes( step ) );
	const addOptions = [
		{
			label: __( '— select a step —', 'open-graph-control' ),
			value: '',
		},
		...missing.map( ( step ) => ( {
			label: stepLabel( step ),
			value: step,
		} ) ),
	];

	const addStep = () => {
		if ( ! toAdd ) {
			return;
		}
		onChange( [ ...steps, toAdd ] );
		setToAdd( '' );
	};

	const move = ( index, delta ) => {
		const next = [ ...steps ];
		const target = index + delta;
		if ( target < 0 || target >= next.length ) {
			return;
		}
		[ next[ index ], next[ target ] ] = [ next[ target ], next[ index ] ];
		onChange( next );
	};

	const toggle = ( index ) => {
		const next = [ ...steps ];
		next.splice( index, 1 );
		onChange( next );
	};

	return (
		<Card>
			<CardHeader>
				<strong>{ label }</strong>
			</CardHeader>
			<CardBody>
				<ol style={ { paddingLeft: '1.5rem' } }>
					{ steps.map( ( step, idx ) => (
						<li
							key={ `${ step }-${ idx }` }
							style={ {
								marginBottom: '0.25rem',
								display: 'flex',
								alignItems: 'center',
								gap: '0.5rem',
							} }
						>
							<code>{ step }</code>
							<span style={ { flex: 1, opacity: 0.7 } }>
								{ stepLabel( step ) }
							</span>
							<Button
								size="small"
								variant="tertiary"
								disabled={ idx === 0 }
								onClick={ () => move( idx, -1 ) }
							>
								↑
							</Button>
							<Button
								size="small"
								variant="tertiary"
								disabled={ idx === steps.length - 1 }
								onClick={ () => move( idx, 1 ) }
							>
								↓
							</Button>
							<Button
								size="small"
								variant="tertiary"
								isDestructive
								onClick={ () => toggle( idx ) }
							>
								{ __( 'Remove', 'open-graph-control' ) }
							</Button>
						</li>
					) ) }
				</ol>
				{ missing.length > 0 && (
					<div
						style={ {
							display: 'flex',
							alignItems: 'flex-end',
							gap: '0.5rem',
							marginTop: '0.5rem',
						} }
					>
						<div style={ { flex: 1 } }>
							<SelectControl
								label={ __( 'Add step', 'open-graph-control' ) }
								value={ toAdd }
								options={ addOptions }
								onChange={ ( v ) => setToAdd( v ) }
							/>
						</div>
						<Button
							variant="secondary"
							onClick={ addStep }
							disabled={ ! toAdd }
						>
							{ __( 'Add', 'open-graph-control' ) }
						</Button>
					</div>
				) }
				<p style={ { fontSize: '0.85em', opacity: 0.7 } }>
					{ __(
						'First non-empty step wins. Removed steps can be added back from the dropdown above.',
						'open-graph-control'
					) }
				</p>
			</CardBody>
		</Card>
	);
}

export default function FallbackChains( { settings, onChange } ) {
	const chains = settings?.fallback_chains || {};

	const setChain = ( field, steps ) => {
		onChange( { fallback_chains: { [ field ]: steps } } );
	};

	return (
		<div className="ogc-section-fallback-chains">
			<h2>{ __( 'Fallback chains', 'open-graph-control' ) }</h2>
			<p>
				{ __(
					'Order determines which source the resolver tries first for each field. The resolver takes the first step that returns a non-empty value.',
					'open-graph-control'
				) }
			</p>

			<Chain
				label={ __( 'Title', 'open-graph-control' ) }
				steps={ chains.title || [] }
				available={ FIELD_STEPS.title }
				onChange={ ( steps ) => setChain( 'title', steps ) }
			/>

			<Chain
				label={ __( 'Description', 'open-graph-control' ) }
				steps={ chains.description || [] }
				available={ FIELD_STEPS.description }
				onChange={ ( steps ) => setChain( 'description', steps ) }
			/>

			<Chain
				label={ __( 'Image', 'open-graph-control' ) }
				steps={ chains.image || [] }
				available={ FIELD_STEPS.image }
				onChange={ ( steps ) => setChain( 'image', steps ) }
			/>
		</div>
	);
}
