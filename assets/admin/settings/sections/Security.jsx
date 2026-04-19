import { __ } from '@wordpress/i18n';
import { Card, CardBody, CardHeader } from '@wordpress/components';

const DEFENSES = [
	{
		label: 'Capability-gated REST',
		detail: 'Every endpoint requires manage_options (site settings) or edit_post per post ID (meta box). No anonymous or subscriber-level write path.',
	},
	{
		label: 'Nonce enforcement',
		detail: 'Admin-post actions use check_admin_referer; REST relies on WordPress core X-WP-Nonce for cookie-authenticated requests.',
	},
	{
		label: 'Output escaping at the edge',
		detail: 'esc_attr on every meta tag attribute, esc_url_raw on every URL, esc_html on admin surfaces.',
	},
	{
		label: 'JSON-LD tag-breakout proof',
		detail: 'Pinterest Rich Pins payload encoded with JSON_HEX_TAG + str_replace("</","<\\/") in the renderer. No post title or author name can close the wrapping <script> tag.',
	},
	{
		label: 'Allowlist-filtered post meta',
		detail: 'Only title, description, image_id, type, platforms, exclude can be written to _ogc_meta. Arbitrary keys are dropped.',
	},
	{
		label: 'Safe URL schemes only',
		detail: 'Image URLs extracted from post content are filtered through wp_allowed_protocols(). javascript: / data: / vbscript: are rejected before reaching any tag output.',
	},
	{
		label: 'Rate-limited previews',
		detail: 'The /preview REST endpoint is capped at 20 calls/minute per user to prevent ad-hoc heavy-tag rendering.',
	},
	{
		label: 'No external calls',
		detail: 'The plugin never issues outbound HTTP requests, never loads third-party assets, and never ships telemetry. Everything runs locally inside your WordPress install.',
	},
];

const OWASP = [
	[
		'A01 Broken Access Control',
		'Capability + per-object checks on every write path',
	],
	[
		'A03 Injection (SQL / XSS)',
		'WP APIs only (no raw $wpdb); output escaping + JSON_HEX_TAG',
	],
	[
		'A04 Insecure Design',
		'Deep-merge schema, allowlist-filtered meta, no mass-assignment',
	],
	[
		'A05 Security Misconfiguration',
		'Safe defaults, strict mode opt-in, no debug output in production',
	],
	[ 'A06 Vulnerable Components', 'Dependabot + composer audit gated in CI' ],
	[
		'A08 Data Integrity',
		'Import/export signed with schema version; downgrade rejected',
	],
	[ 'A10 SSRF', 'Plugin does not issue outbound HTTP requests' ],
];

const AUDIT_LOG = [
	{
		date: '2026-04-19',
		ref: 'd330319',
		url: 'https://github.com/Teriffy/open-graph-control/commit/d330319',
		title: 'Stored XSS via JSON-LD <script> breakout',
		severity: 'High',
		detail: 'Pinterest Rich Pins payload could be closed early through an attacker-controlled string (post title, author name). Fixed with JSON_HEX_TAG flags plus a defense-in-depth renderer pass. Regression tests added.',
	},
];

export default function Security() {
	return (
		<div className="ogc-section-security">
			<h2>{ __( 'Security', 'open-graph-control' ) }</h2>

			<Card>
				<CardHeader>
					{ __( 'No data leaves your server', 'open-graph-control' ) }
				</CardHeader>
				<CardBody>
					<p className="ogc-security__lead">
						{ __(
							'Open Graph Control never calls an external API, never phones home, and never ships telemetry. Everything you see in the tags is resolved and rendered inside your WordPress install.',
							'open-graph-control'
						) }
					</p>
				</CardBody>
			</Card>

			<Card>
				<CardHeader>
					{ __( 'Layered defenses', 'open-graph-control' ) }
				</CardHeader>
				<CardBody>
					<ul className="ogc-security__list">
						{ DEFENSES.map( ( { label, detail } ) => (
							<li
								key={ label }
								className="ogc-security__list-item"
							>
								<strong>{ label }.</strong> { detail }
							</li>
						) ) }
					</ul>
				</CardBody>
			</Card>

			<Card>
				<CardHeader>
					{ __(
						'OWASP Top 10 (2021) coverage',
						'open-graph-control'
					) }
				</CardHeader>
				<CardBody>
					<table className="wp-list-table widefat striped">
						<thead>
							<tr>
								<th className="ogc-security__owasp-risk">
									{ __( 'Risk', 'open-graph-control' ) }
								</th>
								<th>
									{ __(
										'How we handle it',
										'open-graph-control'
									) }
								</th>
							</tr>
						</thead>
						<tbody>
							{ OWASP.map( ( [ risk, handling ] ) => (
								<tr key={ risk }>
									<td>
										<code>{ risk }</code>
									</td>
									<td>{ handling }</td>
								</tr>
							) ) }
						</tbody>
					</table>
					<p className="ogc-muted ogc-security__footnote">
						{ __(
							'Not applicable to this plugin: A02 Cryptographic Failures, A07 Auth (delegated to WP core), A09 Logging (delegated to host).',
							'open-graph-control'
						) }
					</p>
				</CardBody>
			</Card>

			<Card>
				<CardHeader>
					{ __( 'Audit trail', 'open-graph-control' ) }
				</CardHeader>
				<CardBody>
					<ul className="ogc-security__list">
						{ AUDIT_LOG.map( ( entry ) => (
							<li
								key={ entry.ref }
								className="ogc-security__list-item"
							>
								<code>{ entry.date }</code> —{ ' ' }
								<strong>{ entry.title }</strong>{ ' ' }
								<span className="ogc-security-badge">
									{ entry.severity }
								</span>
								<br />
								{ entry.detail }{ ' ' }
								<a
									href={ entry.url }
									target="_blank"
									rel="noreferrer"
								>
									{ entry.ref }
								</a>
							</li>
						) ) }
					</ul>
				</CardBody>
			</Card>

			<Card>
				<CardHeader>
					{ __( 'Responsible disclosure', 'open-graph-control' ) }
				</CardHeader>
				<CardBody>
					<p className="ogc-security__lead">
						{ __(
							'Found a vulnerability? Please don\u2019t open a public issue. Use the private advisory form on GitHub or email the address in SECURITY.md.',
							'open-graph-control'
						) }
					</p>
					<p className="ogc-security__links">
						<a
							href="https://github.com/Teriffy/open-graph-control/security/advisories/new"
							target="_blank"
							rel="noreferrer"
						>
							{ __(
								'Open a private security advisory →',
								'open-graph-control'
							) }
						</a>
						<br />
						<a
							href="https://github.com/Teriffy/open-graph-control/blob/main/SECURITY.md"
							target="_blank"
							rel="noreferrer"
						>
							{ __(
								'Read the full security policy →',
								'open-graph-control'
							) }
						</a>
					</p>
					<p className="ogc-muted ogc-security__footnote">
						{ __(
							'Response SLA: 3 business days. Fix SLA: 30 days for confirmed valid reports. Credit given in release notes unless you prefer anonymity.',
							'open-graph-control'
						) }
					</p>
				</CardBody>
			</Card>
		</div>
	);
}
