import apiFetch from '@wordpress/api-fetch';
import { Button, Notice, Spinner, TextControl } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

import { Badge, EmptyRow, Section } from '../components/layout.js';
import { useSettings } from '../context.js';
import { METHOD_LABELS, METHOD_TONES } from '../labels.js';

const RECORDS_PATH = '/mortar-consent/v1/records';

/** Set by AdminPage before the script loads. */
const exportUrl = (window.mortarConsentAdmin || {}).exportUrl;

export default function LogTab() {
	const { draft } = useSettings();
	const [result, setResult] = useState(null);
	const [searched, setSearched] = useState('');
	const [error, setError] = useState(null);
	const [loading, setLoading] = useState(false);

	const fetchRecords = (consentId) => {
		setError(null);
		setLoading(true);
		apiFetch({ path: RECORDS_PATH + (consentId ? `?consent_id=${encodeURIComponent(consentId)}` : '') })
			.then((payload) => {
				setResult(payload);
				setSearched(consentId);
			})
			.catch((e) => setError(e.message))
			.finally(() => setLoading(false));
	};

	useEffect(() => fetchRecords(''), []);

	return (
		<>
			{!draft.log_enabled && (
				<Notice status="warning" isDismissible={false}>
					{__('Logging is switched off, so new choices are not recorded. Turn it on under General.', 'mortar-consent')}
				</Notice>
			)}
			{error && (
				<Notice status="error" isDismissible={false}>
					{error}
				</Notice>
			)}
			<Section wide title={__('Last 30 days', 'mortar-consent')}>
				{result ? (
					<Summary summary={result.summary} />
				) : (
					<p className="mortar-consent-loading">
						<Spinner />
						{__('Loading records…', 'mortar-consent')}
					</p>
				)}
			</Section>
			<Section
				wide
				title={__('Records', 'mortar-consent')}
				description={__('Newest first. Download the CSV for the full log.', 'mortar-consent')}
			>
				<div className="mortar-consent-toolbar">
					<Search loading={loading} searched={searched} onSearch={fetchRecords} />
					<Button variant="secondary" href={exportUrl} __next40pxDefaultSize>
						{__('Download CSV', 'mortar-consent')}
					</Button>
				</div>
				{result && <Records records={result.records} searched={searched} />}
			</Section>
		</>
	);
}

/** Totals per method, with each method's share. */
function Summary({ summary }) {
	const total = Object.values(summary).reduce((sum, n) => sum + n, 0);
	const methods = [...new Set([...Object.keys(METHOD_LABELS), ...Object.keys(summary)])];

	return (
		<dl className="mortar-consent-stats">
			<div className="mortar-consent-stat">
				<dt>{__('Choices recorded', 'mortar-consent')}</dt>
				<dd>{total.toLocaleString()}</dd>
			</div>
			{methods.map((method) => {
				const n = summary[method] || 0;

				return (
					<div key={method} className="mortar-consent-stat">
						<dt>{METHOD_LABELS[method] || method}</dt>
						<dd>
							{n.toLocaleString()}
							{total > 0 && <span className="mortar-consent-stat__share">{`${Math.round((n / total) * 100)}%`}</span>}
						</dd>
					</div>
				);
			})}
		</dl>
	);
}

function Search({ loading, searched, onSearch }) {
	const [query, setQuery] = useState('');

	return (
		<form
			role="search"
			onSubmit={(event) => {
				event.preventDefault();
				onSearch(query.trim().toLowerCase());
			}}
		>
			<TextControl
				label={__('Find a consent ID', 'mortar-consent')}
				help={__('Visitors see their ID in the preferences dialog.', 'mortar-consent')}
				value={query}
				onChange={setQuery}
				__nextHasNoMarginBottom
				__next40pxDefaultSize
			/>
			<Button variant="secondary" type="submit" isBusy={loading} __next40pxDefaultSize>
				{__('Search', 'mortar-consent')}
			</Button>
			{searched && (
				<Button
					variant="tertiary"
					__next40pxDefaultSize
					onClick={() => {
						setQuery('');
						onSearch('');
					}}
				>
					{__('Clear', 'mortar-consent')}
				</Button>
			)}
		</form>
	);
}

function Records({ records, searched }) {
	return (
		<div className="mortar-consent-table-wrap">
			<table className="mortar-consent-table">
				<thead>
					<tr>
						<th scope="col">{__('Time (UTC)', 'mortar-consent')}</th>
						<th scope="col">{__('Consent ID', 'mortar-consent')}</th>
						<th scope="col">{__('Choice', 'mortar-consent')}</th>
						<th scope="col">{__('Allowed', 'mortar-consent')}</th>
						<th scope="col">{__('Policy', 'mortar-consent')}</th>
					</tr>
				</thead>
				<tbody>
					{records.length === 0 ? (
						<EmptyRow columns={5}>
							{searched
								? __('No records for that consent ID.', 'mortar-consent')
								: __('No records yet.', 'mortar-consent')}
						</EmptyRow>
					) : (
						records.map((record) => (
							<tr key={record.id}>
								<td className="mortar-consent-log-time">{record.createdAt.replace('T', ' ').replace('Z', '')}</td>
								<td>
									<code>{record.consentId}</code>
								</td>
								<td>
									<Badge tone={METHOD_TONES[record.method]}>{METHOD_LABELS[record.method] || record.method}</Badge>
								</td>
								<td>{record.granted.join(', ') || '—'}</td>
								<td>
									{sprintf(
										/* translators: %d: cookie policy version number, shown as v1, v2… */
										__('v%d', 'mortar-consent'),
										record.policyVersion,
									)}
								</td>
							</tr>
						))
					)}
				</tbody>
			</table>
		</div>
	);
}
