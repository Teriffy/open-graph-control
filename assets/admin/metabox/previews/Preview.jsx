import { __ } from '@wordpress/i18n';
import { SelectControl } from '@wordpress/components';
import { useState } from '@wordpress/element';

import './cards.scss';

function domain( url ) {
	try {
		return new URL( url ).hostname;
	} catch ( e ) {
		return 'example.com';
	}
}

const PLATFORM_OPTIONS = [
	{ label: 'Facebook', value: 'facebook' },
	{ label: 'X / Twitter', value: 'twitter' },
	{ label: 'LinkedIn', value: 'linkedin' },
	{ label: 'iMessage', value: 'imessage' },
	{ label: 'Threads', value: 'threads' },
	{ label: 'Mastodon', value: 'mastodon' },
	{ label: 'Bluesky', value: 'bluesky' },
	{ label: 'WhatsApp', value: 'whatsapp' },
	{ label: 'Discord', value: 'discord' },
	{ label: 'Pinterest', value: 'pinterest' },
	{ label: 'Telegram', value: 'telegram' },
	{ label: 'Slack', value: 'slack' },
];

function bgStyle( image ) {
	if ( ! image ) {
		return {};
	}
	return { backgroundImage: `url(${ image })` };
}

function Card( { platform, title, description, image, siteName, url } ) {
	const host = domain( url );

	switch ( platform ) {
		case 'facebook':
			return (
				<div className="ogc-preview-card is-facebook">
					<div
						className="ogc-preview-img"
						style={ bgStyle( image ) }
					/>
					<div className="ogc-preview-body">
						<div className="ogc-preview-site">{ host }</div>
						<div className="ogc-preview-title">{ title }</div>
						<div className="ogc-preview-desc">{ description }</div>
					</div>
				</div>
			);
		case 'twitter':
			return (
				<div className="ogc-preview-card is-twitter">
					<div className="ogc-preview-img" style={ bgStyle( image ) }>
						<div className="ogc-preview-domain-overlay">
							{ host }
						</div>
					</div>
					<div className="ogc-preview-body">
						<div className="ogc-preview-title">{ title }</div>
					</div>
				</div>
			);
		case 'linkedin':
			return (
				<div className="ogc-preview-card is-linkedin">
					<div
						className="ogc-preview-img"
						style={ bgStyle( image ) }
					/>
					<div className="ogc-preview-body">
						<div className="ogc-preview-title">{ title }</div>
						<div className="ogc-preview-site">{ host }</div>
					</div>
				</div>
			);
		case 'imessage':
			return (
				<div className="ogc-preview-card is-imessage">
					<div className="ogc-preview-inner">
						<div
							className="ogc-preview-img"
							style={ bgStyle( image ) }
						/>
						<div className="ogc-preview-body">
							<div className="ogc-preview-title">{ title }</div>
							<div className="ogc-preview-site">{ host }</div>
						</div>
					</div>
				</div>
			);
		case 'mastodon':
			return (
				<div className="ogc-preview-card is-mastodon">
					<div
						className="ogc-preview-img"
						style={ bgStyle( image ) }
					/>
					<div className="ogc-preview-body">
						<div className="ogc-preview-site">{ host }</div>
						<div className="ogc-preview-title">{ title }</div>
						<div className="ogc-preview-desc">{ description }</div>
					</div>
				</div>
			);
		case 'pinterest':
			return (
				<div className="ogc-preview-card is-pinterest">
					<div
						className="ogc-preview-img"
						style={ bgStyle( image ) }
					/>
					<div className="ogc-preview-body">
						<div className="ogc-preview-title">{ title }</div>
						<div className="ogc-preview-site">{ host }</div>
					</div>
				</div>
			);
		case 'discord':
			return (
				<div className="ogc-preview-card is-discord">
					<div className="ogc-preview-site">{ siteName || host }</div>
					<div className="ogc-preview-title">{ title }</div>
					<div className="ogc-preview-desc">{ description }</div>
					<div
						className="ogc-preview-img"
						style={ bgStyle( image ) }
					/>
				</div>
			);
		case 'threads':
			return (
				<div className="ogc-preview-card is-threads">
					<div
						className="ogc-preview-img"
						style={ bgStyle( image ) }
					/>
					<div className="ogc-preview-body">
						<div className="ogc-preview-title">{ title }</div>
						<div className="ogc-preview-site">{ host }</div>
					</div>
				</div>
			);
		case 'bluesky':
			return (
				<div className="ogc-preview-card is-bluesky">
					<div
						className="ogc-preview-img"
						style={ bgStyle( image ) }
					/>
					<div className="ogc-preview-body">
						<div className="ogc-preview-site">{ host }</div>
						<div className="ogc-preview-title">{ title }</div>
					</div>
				</div>
			);
		case 'whatsapp':
			return (
				<div className="ogc-preview-card is-whatsapp">
					<div className="ogc-preview-inner">
						<div
							className="ogc-preview-img"
							style={ bgStyle( image ) }
						/>
						<div className="ogc-preview-body">
							<div className="ogc-preview-title">{ title }</div>
							<div className="ogc-preview-desc">
								{ description }
							</div>
							<div className="ogc-preview-site">{ host }</div>
						</div>
					</div>
				</div>
			);
		case 'telegram':
			return (
				<div className="ogc-preview-card is-telegram">
					<div
						className="ogc-preview-img"
						style={ bgStyle( image ) }
					/>
					<div className="ogc-preview-body">
						<div className="ogc-preview-title">
							{ siteName || host }
						</div>
						<div className="ogc-preview-site">{ host }</div>
						<div className="ogc-preview-desc">{ title }</div>
					</div>
				</div>
			);
		case 'slack':
			return (
				<div className="ogc-preview-card is-slack">
					<div className="ogc-preview-site">{ siteName || host }</div>
					<div className="ogc-preview-title">{ title }</div>
					<div className="ogc-preview-desc">{ description }</div>
					<div
						className="ogc-preview-img"
						style={ bgStyle( image ) }
					/>
				</div>
			);
		default:
			return null;
	}
}

export default function Preview( props ) {
	const [ active, setActive ] = useState( 'facebook' );
	const activeLabel =
		PLATFORM_OPTIONS.find( ( o ) => o.value === active )?.label || active;
	return (
		<div className="ogc-preview">
			<SelectControl
				label={ __( 'Preview', 'open-graph-control' ) }
				value={ active }
				options={ PLATFORM_OPTIONS }
				onChange={ ( v ) => setActive( v ) }
			/>
			<div
				aria-live="polite"
				aria-atomic="true"
				aria-label={ `${ activeLabel } preview` }
			>
				<Card platform={ active } { ...props } />
			</div>
		</div>
	);
}
