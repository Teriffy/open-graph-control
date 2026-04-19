import { useEffect, useState, useMemo } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import {
	Button,
	Card,
	CardBody,
	Notice,
	SelectControl,
	Spinner,
	TabPanel,
	TextControl,
	TextareaControl,
} from '@wordpress/components';

import { api } from '../shared/api.js';
import Preview from './previews/Preview.jsx';

const LIMITS = {
	title: { warn: 60, error: 90 },
	description: { warn: 140, error: 200 },
};

function status( text, kind ) {
	if ( ! text ) {
		return 'info';
	}
	const length = text.length;
	const limits = LIMITS[ kind ];
	if ( ! limits ) {
		return 'info';
	}
	if ( length > limits.error ) {
		return 'error';
	}
	if ( length > limits.warn ) {
		return 'warn';
	}
	return 'ok';
}

function CharCount( { value, kind } ) {
	const severity = status( value, kind );
	const limit = LIMITS[ kind ]?.error;
	let color = '#50575e';
	if ( severity === 'error' ) {
		color = '#d63638';
	} else if ( severity === 'warn' ) {
		color = '#dba617';
	}
	return (
		<span style={ { color, fontSize: '0.85em' } }>
			{ sprintf(
				/* translators: 1: length, 2: recommended upper limit. */
				__( '%1$d / %2$d characters', 'open-graph-control' ),
				value ? value.length : 0,
				limit
			) }
		</span>
	);
}

/**
 * @param {Object}   props
 * @param {Object}   props.meta     Current _ogc_meta state.
 * @param {Function} props.onChange Patch handler.
 */
function BaseTab( { meta, onChange } ) {
	return (
		<>
			<TextControl
				label={ __( 'Title override', 'open-graph-control' ) }
				help={ <CharCount value={ meta.title || '' } kind="title" /> }
				value={ meta.title || '' }
				onChange={ ( v ) => onChange( { title: v } ) }
			/>
			<TextareaControl
				label={ __( 'Description override', 'open-graph-control' ) }
				help={
					<CharCount
						value={ meta.description || '' }
						kind="description"
					/>
				}
				value={ meta.description || '' }
				onChange={ ( v ) => onChange( { description: v } ) }
			/>
			<TextControl
				label={ __(
					'Image attachment ID (optional)',
					'open-graph-control'
				) }
				help={ __(
					'Enter an attachment ID. Leave blank to use the featured image / fallback chain.',
					'open-graph-control'
				) }
				type="number"
				value={ String( meta.image_id || '' ) }
				onChange={ ( v ) =>
					onChange( { image_id: parseInt( v, 10 ) || 0 } )
				}
			/>
			<SelectControl
				label={ __( 'og:type override', 'open-graph-control' ) }
				value={ meta.type || '' }
				options={ [
					{
						label: __( '(use default)', 'open-graph-control' ),
						value: '',
					},
					{ label: 'article', value: 'article' },
					{ label: 'website', value: 'website' },
					{ label: 'product', value: 'product' },
					{ label: 'profile', value: 'profile' },
				] }
				onChange={ ( v ) => onChange( { type: v } ) }
			/>
		</>
	);
}

function PlatformTab( { slug, meta, onChange } ) {
	const platforms = meta.platforms || {};
	const entry = platforms[ slug ] || {};

	const update = ( patch ) => {
		onChange( {
			platforms: {
				...platforms,
				[ slug ]: { ...entry, ...patch },
			},
		} );
	};

	return (
		<>
			<TextControl
				label={ __( 'Title override', 'open-graph-control' ) }
				value={ entry.title || '' }
				onChange={ ( v ) => update( { title: v } ) }
			/>
			<TextareaControl
				label={ __( 'Description override', 'open-graph-control' ) }
				value={ entry.description || '' }
				onChange={ ( v ) => update( { description: v } ) }
			/>
			<TextControl
				label={ __(
					'Image attachment ID (optional)',
					'open-graph-control'
				) }
				type="number"
				value={ String( entry.image_id || '' ) }
				onChange={ ( v ) =>
					update( { image_id: parseInt( v, 10 ) || 0 } )
				}
			/>
			{ slug === 'twitter' && (
				<SelectControl
					label={ __( 'Card override', 'open-graph-control' ) }
					value={ entry.card || '' }
					options={ [
						{
							label: __( '(use default)', 'open-graph-control' ),
							value: '',
						},
						{
							label: 'summary_large_image',
							value: 'summary_large_image',
						},
						{ label: 'summary', value: 'summary' },
					] }
					onChange={ ( v ) => update( { card: v } ) }
				/>
			) }
			{ slug === 'pinterest' && (
				<SelectControl
					label={ __(
						'Rich Pin schema override',
						'open-graph-control'
					) }
					value={ entry.rich_pins_type || '' }
					options={ [
						{
							label: __( '(use default)', 'open-graph-control' ),
							value: '',
						},
						{ label: 'article', value: 'article' },
						{ label: 'product', value: 'product' },
						{ label: 'recipe', value: 'recipe' },
					] }
					onChange={ ( v ) => update( { rich_pins_type: v } ) }
				/>
			) }
		</>
	);
}

export default function MetaBoxApp( { postId } ) {
	const [ meta, setMeta ] = useState( null );
	const [ settings, setSettings ] = useState( null );
	const [ saveState, setSaveState ] = useState( { kind: 'idle' } );

	useEffect( () => {
		Promise.all( [ api.getPostMeta( postId ), api.getSettings() ] )
			.then( ( [ m, s ] ) => {
				setMeta( m );
				setSettings( s );
			} )
			.catch( ( err ) =>
				setSaveState( { kind: 'error', message: err.message } )
			);
	}, [ postId ] );

	const enabledPlatforms = useMemo( () => {
		if ( ! settings ) {
			return [];
		}
		return Object.entries( settings.platforms || {} )
			.filter( ( [ , config ] ) => config?.enabled )
			.map( ( [ slug ] ) => slug );
	}, [ settings ] );

	const previewProps = useMemo( () => {
		return {
			title: meta?.title || __( 'Your post title', 'open-graph-control' ),
			description:
				meta?.description ||
				__(
					'Your description will appear here.',
					'open-graph-control'
				),
			image: '',
			siteName: settings?.site?.name || '',
			url: 'https://example.com/your-post/',
		};
	}, [ meta, settings ] );

	if ( ! meta || ! settings ) {
		return <Spinner />;
	}

	const applyBase = ( patch ) => {
		setMeta( ( prev ) => ( { ...prev, ...patch } ) );
	};

	const applyPlatforms = ( patch ) => {
		setMeta( ( prev ) => ( { ...prev, ...patch } ) );
	};

	const save = async () => {
		setSaveState( { kind: 'saving' } );
		try {
			const next = await api.savePostMeta( postId, meta );
			setMeta( next );
			setSaveState( { kind: 'saved' } );
		} catch ( err ) {
			setSaveState( { kind: 'error', message: err.message } );
		}
	};

	const tabs = [
		{ name: 'base', title: __( 'Base', 'open-graph-control' ) },
		{ name: 'twitter', title: __( 'X / Twitter', 'open-graph-control' ) },
		{ name: 'pinterest', title: __( 'Pinterest', 'open-graph-control' ) },
		{
			name: 'overrides',
			title: __( 'Per-platform', 'open-graph-control' ),
		},
	];

	return (
		<div
			className="ogc-metabox"
			style={ { display: 'flex', gap: '1.5rem', padding: '0.5rem' } }
		>
			<div style={ { flex: 1, minWidth: 0 } }>
				<TabPanel className="ogc-metabox-tabs" tabs={ tabs }>
					{ ( tab ) => {
						if ( tab.name === 'base' ) {
							return (
								<BaseTab meta={ meta } onChange={ applyBase } />
							);
						}
						if ( tab.name === 'twitter' ) {
							return (
								<PlatformTab
									slug="twitter"
									meta={ meta }
									onChange={ applyPlatforms }
								/>
							);
						}
						if ( tab.name === 'pinterest' ) {
							return (
								<PlatformTab
									slug="pinterest"
									meta={ meta }
									onChange={ applyPlatforms }
								/>
							);
						}
						return (
							<>
								<p style={ { opacity: 0.7 } }>
									{ __(
										'Override title / description / image for a specific platform only. Leave a field blank to inherit from the Base tab.',
										'open-graph-control'
									) }
								</p>
								{ enabledPlatforms
									.filter(
										( slug ) =>
											! [
												'twitter',
												'pinterest',
											].includes( slug )
									)
									.map( ( slug ) => (
										<Card key={ slug }>
											<CardBody>
												<strong>{ slug }</strong>
												<PlatformTab
													slug={ slug }
													meta={ meta }
													onChange={ applyPlatforms }
												/>
											</CardBody>
										</Card>
									) ) }
							</>
						);
					} }
				</TabPanel>

				<div style={ { marginTop: '1rem' } }>
					<Button
						variant="primary"
						onClick={ save }
						disabled={ saveState.kind === 'saving' }
					>
						{ saveState.kind === 'saving'
							? __( 'Saving…', 'open-graph-control' )
							: __( 'Save overrides', 'open-graph-control' ) }
					</Button>
					{ saveState.kind === 'saved' && (
						<span
							style={ {
								marginLeft: '1rem',
								color: '#00a32a',
							} }
						>
							{ __( 'Saved.', 'open-graph-control' ) }
						</span>
					) }
					{ saveState.kind === 'error' && (
						<Notice status="error" isDismissible={ false }>
							{ saveState.message }
						</Notice>
					) }
				</div>
			</div>

			<div style={ { width: '320px', flexShrink: 0 } }>
				<Preview { ...previewProps } />
			</div>
		</div>
	);
}
