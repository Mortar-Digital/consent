import { Button, Card, CardBody, Notice, Panel, PanelBody, TextControl, TextareaControl } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';

import { ActionHeader, Badge, EmptyRow, Section } from '../components/layout.js';
import { useSettings } from '../context.js';
import { isCategoryId, withoutCategory } from '../settings.js';

export default function CategoriesTab() {
	const { draft, isLocked, lockHelp } = useSettings();
	const locked = isLocked('categories');

	return (
		<>
			{locked && (
				<Notice status="info" isDismissible={false}>
					{lockHelp('categories')}
				</Notice>
			)}
			<Section
				title={__('Categories', 'mortar-consent')}
				description={__(
					'Categories appear in the preferences dialog. Cookies listed here appear in the Cookie declaration block, and are deleted when a visitor refuses or withdraws that category.',
					'mortar-consent',
				)}
			>
				<Panel className="mortar-consent-categories">
					{draft.categories.map((category, index) => (
						<CategoryPanel key={category.id} category={category} index={index} locked={locked} />
					))}
				</Panel>
			</Section>
			{!locked && <AddCategory />}
		</>
	);
}

function CategoryPanel({ category, index, locked }) {
	const { draft, set, setDraft } = useSettings();
	const update = (patch) =>
		set(
			'categories',
			draft.categories.map((c, i) => (i === index ? { ...c, ...patch } : c)),
		);

	return (
		<PanelBody title={<CategoryTitle category={category} />} initialOpen={false}>
			<TextControl
				label={__('Name', 'mortar-consent')}
				value={category.label}
				disabled={locked}
				onChange={(label) => update({ label })}
				__nextHasNoMarginBottom
				__next40pxDefaultSize
			/>
			<TextareaControl
				label={__('Description', 'mortar-consent')}
				value={category.description}
				rows={3}
				disabled={locked}
				onChange={(description) => update({ description })}
				__nextHasNoMarginBottom
			/>
			{category.required && (
				<p className="description">
					{__('Strictly necessary: always on, visitors cannot switch it off.', 'mortar-consent')}
				</p>
			)}
			<CookieTable cookies={category.cookies} locked={locked} onChange={(cookies) => update({ cookies })} />
			<div className="mortar-consent-actions">
				<Button
					variant="secondary"
					disabled={locked}
					__next40pxDefaultSize
					onClick={() =>
						update({ cookies: [...category.cookies, { name: '', provider: '', purpose: '', duration: '' }] })
					}
				>
					{__('Add cookie', 'mortar-consent')}
				</Button>
				{!category.required && (
					<Button
						variant="tertiary"
						isDestructive
						disabled={locked}
						__next40pxDefaultSize
						onClick={() => setDraft((current) => withoutCategory(current, category.id))}
					>
						{__('Remove category', 'mortar-consent')}
					</Button>
				)}
			</div>
		</PanelBody>
	);
}

function CategoryTitle({ category }) {
	return (
		<span className="mortar-consent-category-title">
			{category.label || category.id} <code>{category.id}</code>{' '}
			{category.required && (
				<>
					<Badge tone="success">{__('Always on', 'mortar-consent')}</Badge>{' '}
				</>
			)}
			<span className="mortar-consent-category-title__meta">
				{sprintf(
					/* translators: %d: number of cookies */
					_n('%d cookie', '%d cookies', category.cookies.length, 'mortar-consent'),
					category.cookies.length,
				)}
			</span>
		</span>
	);
}

const COOKIE_FIELDS = [
	['name', __('Cookie name', 'mortar-consent'), '_ga_*'],
	['provider', __('Provider', 'mortar-consent'), __('This site', 'mortar-consent')],
	['purpose', __('Purpose', 'mortar-consent'), ''],
	['duration', __('Duration', 'mortar-consent'), __('1 year', 'mortar-consent')],
];

/** The cookies one category sets, as an editable table. */
function CookieTable({ cookies, locked, onChange }) {
	const updateCookie = (index, patch) => onChange(cookies.map((c, i) => (i === index ? { ...c, ...patch } : c)));

	return (
		<div className="mortar-consent-table-wrap">
			<table className="mortar-consent-table mortar-consent-table--edit">
				<thead>
					<tr>
						{COOKIE_FIELDS.map(([field, label]) => (
							<th key={field} scope="col">
								{label}
							</th>
						))}
						<ActionHeader />
					</tr>
				</thead>
				<tbody>
					{cookies.length === 0 ? (
						<EmptyRow columns={5}>
							{__('No cookies listed yet. End a name with * to match a prefix, for example _ga_*.', 'mortar-consent')}
						</EmptyRow>
					) : (
						cookies.map((cookie, index) => (
							<tr key={index}>
								{COOKIE_FIELDS.map(([field, label, placeholder]) => (
									<td key={field} className={field === 'name' ? 'mortar-consent-mono' : undefined}>
										<TextControl
											label={label}
											hideLabelFromVision
											value={cookie[field]}
											placeholder={placeholder}
											disabled={locked}
											onChange={(value) => updateCookie(index, { [field]: value })}
											__nextHasNoMarginBottom
											__next40pxDefaultSize
										/>
									</td>
								))}
								<td className="mortar-consent-table__action">
									<Button
										variant="tertiary"
										isDestructive
										size="compact"
										disabled={locked}
										aria-label={sprintf(
											/* translators: %s: cookie name, or its position in the list */
											__('Remove cookie %s', 'mortar-consent'),
											cookie.name || index + 1,
										)}
										onClick={() => onChange(cookies.filter((_, i) => i !== index))}
									>
										{__('Remove', 'mortar-consent')}
									</Button>
								</td>
							</tr>
						))
					)}
				</tbody>
			</table>
		</div>
	);
}

function AddCategory() {
	const { draft, set } = useSettings();
	const [newId, setNewId] = useState('');
	const taken = draft.categories.some((c) => c.id === newId);
	const valid = isCategoryId(newId);

	const add = (event) => {
		event.preventDefault();

		if (valid && !taken) {
			set('categories', [
				...draft.categories,
				{ id: newId, label: newId, description: '', required: false, cookies: [] },
			]);
			setNewId('');
		}
	};

	return (
		<Section
			title={__('Add a category', 'mortar-consent')}
			description={__(
				'For example "social" for share buttons. Then tick it for the scripts and embeds it covers.',
				'mortar-consent',
			)}
		>
			<Card>
				<CardBody>
					<form className="mortar-consent-inline-form" onSubmit={add}>
						<TextControl
							label={__('ID', 'mortar-consent')}
							help={
								taken
									? __('A category with this ID already exists.', 'mortar-consent')
									: __('Lowercase letters, digits, - or _. It cannot be changed later.', 'mortar-consent')
							}
							value={newId}
							onChange={(value) => setNewId(value.trim().toLowerCase())}
							__nextHasNoMarginBottom
							__next40pxDefaultSize
						/>
						<Button
							variant="secondary"
							type="submit"
							disabled={!valid || taken}
							accessibleWhenDisabled
							__next40pxDefaultSize
						>
							{__('Add category', 'mortar-consent')}
						</Button>
					</form>
				</CardBody>
			</Card>
		</Section>
	);
}
