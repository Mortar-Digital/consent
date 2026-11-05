import { Card, CardBody } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/** A settings group: heading and explanation beside its controls (stacked on narrow screens). */
export function Section({ title, description, aside, wide, children }) {
	return (
		<section className={'mortar-consent-section' + (wide ? ' mortar-consent-section--wide' : '')}>
			<div className="mortar-consent-section__intro">
				<h2>
					{title}
					{aside && ' '}
					{aside}
				</h2>
				{description && <p>{description}</p>}
			</div>
			<div className="mortar-consent-section__body">{children}</div>
		</section>
	);
}

/** A card of stacked form fields. */
export function Fields({ children }) {
	return (
		<Card>
			<CardBody className="mortar-consent-fields">{children}</CardBody>
		</Card>
	);
}

/** A small status label; tone is success, warning, error or info. */
export function Badge({ tone, children }) {
	return <span className={'mortar-consent-badge' + (tone ? ` mortar-consent-badge--${tone}` : '')}>{children}</span>;
}

/** A table row spanning every column, for "nothing here yet" messages. */
export function EmptyRow({ columns, children }) {
	return (
		<tr>
			<td colSpan={columns}>
				<p className="mortar-consent-empty">{children}</p>
			</td>
		</tr>
	);
}

/** The visually hidden header of a table's action column. */
export function ActionHeader() {
	return (
		<th className="mortar-consent-table__action">
			<span className="screen-reader-text">{__('Actions', 'mortar-consent')}</span>
		</th>
	);
}
