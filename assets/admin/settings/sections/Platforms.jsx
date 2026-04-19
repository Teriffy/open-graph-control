import { __ } from '@wordpress/i18n';
import {
	Card,
	CardBody,
	CardHeader,
	SelectControl,
	TextControl,
	ToggleControl,
} from '@wordpress/components';

const PLATFORM_LABELS = {
	facebook: 'Facebook',
	twitter: 'X / Twitter',
	linkedin: 'LinkedIn',
	imessage: 'iMessage (iOS)',
	threads: 'Threads',
	mastodon: 'Mastodon',
	bluesky: 'Bluesky',
	whatsapp: 'WhatsApp',
	discord: 'Discord',
	pinterest: 'Pinterest',
	telegram: 'Telegram',
	slack: 'Slack',
};

const TWITTER_CARDS = [
	{ label: 'summary_large_image', value: 'summary_large_image' },
	{ label: 'summary', value: 'summary' },
];

const PINTEREST_TYPES = [
	{ label: 'article', value: 'article' },
	{ label: 'product', value: 'product' },
	{ label: 'recipe', value: 'recipe' },
];

export default function Platforms( { settings, onChange } ) {
	const platforms = settings?.platforms || {};

	const updatePlatform = ( slug, patch ) => {
		onChange( { platforms: { [ slug ]: patch } } );
	};

	return (
		<div className="ogc-section-platforms">
			<h2>{ __( 'Platforms', 'open-graph-control' ) }</h2>
			{ Object.keys( PLATFORM_LABELS ).map( ( slug ) => {
				const config = platforms[ slug ] || { enabled: false };
				return (
					<Card key={ slug } className="ogc-platform-card">
						<CardHeader>
							<strong>{ PLATFORM_LABELS[ slug ] }</strong>
						</CardHeader>
						<CardBody>
							<ToggleControl
								label={ __( 'Enabled', 'open-graph-control' ) }
								checked={ !! config.enabled }
								onChange={ ( enabled ) =>
									updatePlatform( slug, { enabled } )
								}
							/>

							{ slug === 'facebook' && (
								<TextControl
									label={ __(
										'Facebook App ID (optional)',
										'open-graph-control'
									) }
									help={ __(
										'If set, emits a fb:app_id meta tag.',
										'open-graph-control'
									) }
									value={ config.fb_app_id || '' }
									onChange={ ( v ) =>
										updatePlatform( slug, { fb_app_id: v } )
									}
								/>
							) }

							{ slug === 'twitter' && (
								<>
									<SelectControl
										label={ __(
											'Card type',
											'open-graph-control'
										) }
										value={
											config.card || 'summary_large_image'
										}
										options={ TWITTER_CARDS }
										onChange={ ( v ) =>
											updatePlatform( slug, { card: v } )
										}
									/>
									<TextControl
										label={ __(
											'Site handle',
											'open-graph-control'
										) }
										help={ __(
											'e.g. @example',
											'open-graph-control'
										) }
										value={ config.site || '' }
										onChange={ ( v ) =>
											updatePlatform( slug, { site: v } )
										}
									/>
									<TextControl
										label={ __(
											'Default creator handle',
											'open-graph-control'
										) }
										value={ config.creator || '' }
										onChange={ ( v ) =>
											updatePlatform( slug, {
												creator: v,
											} )
										}
									/>
								</>
							) }

							{ slug === 'mastodon' && (
								<TextControl
									label={ __(
										'fediverse:creator',
										'open-graph-control'
									) }
									help={ __(
										'e.g. @me@mastodon.social',
										'open-graph-control'
									) }
									value={ config.fediverse_creator || '' }
									onChange={ ( v ) =>
										updatePlatform( slug, {
											fediverse_creator: v,
										} )
									}
								/>
							) }

							{ slug === 'pinterest' && (
								<SelectControl
									label={ __(
										'Rich Pin schema',
										'open-graph-control'
									) }
									value={ config.rich_pins_type || 'article' }
									options={ PINTEREST_TYPES }
									onChange={ ( v ) =>
										updatePlatform( slug, {
											rich_pins_type: v,
										} )
									}
								/>
							) }

							{ slug === 'whatsapp' && (
								<TextControl
									label={ __(
										'Max image size warning (KB)',
										'open-graph-control'
									) }
									type="number"
									value={ String(
										config.max_image_kb ?? 280
									) }
									onChange={ ( v ) =>
										updatePlatform( slug, {
											max_image_kb:
												parseInt( v, 10 ) || 280,
										} )
									}
								/>
							) }

							{ slug === 'imessage' && (
								<ToggleControl
									label={ __(
										'Prefer square image',
										'open-graph-control'
									) }
									help={ __(
										'Renders large-bubble rich preview with a 600×600 crop.',
										'open-graph-control'
									) }
									checked={ !! config.prefer_square }
									onChange={ ( v ) =>
										updatePlatform( slug, {
											prefer_square: v,
										} )
									}
								/>
							) }
						</CardBody>
					</Card>
				);
			} ) }
		</div>
	);
}
