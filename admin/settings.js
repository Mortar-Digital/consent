// Pure helpers for the settings screen's form state. No WordPress imports, so
// they run under node --test (tests/js/admin-settings.test.mjs).

/** Settings as the server returns them → editable form state. */
export function toDraft(effective) {
	return {
		...effective,
		scripts: Object.entries(effective.scripts || {}).map(([handle, categories]) => ({
			handle,
			categories: Array.isArray(categories) ? categories : [categories],
		})),
	};
}

/** Form state → the settings object the server validates. Blank script handles are dropped. */
export function fromDraft(draft) {
	const scripts = {};

	for (const row of draft.scripts) {
		const handle = row.handle.trim();

		if (handle !== '') {
			scripts[handle] = row.categories;
		}
	}

	return { ...draft, scripts };
}

/** Removes a deleted category from every setting that refers to it. */
export function withoutCategory(draft, id) {
	const strip = (map) =>
		Object.fromEntries(
			Object.entries(map || {}).map(([key, ids]) => [key, (Array.isArray(ids) ? ids : [ids]).filter((x) => x !== id)]),
		);

	return {
		...draft,
		categories: draft.categories.filter((c) => c.id !== id),
		scripts: draft.scripts.map((row) => ({ ...row, categories: row.categories.filter((x) => x !== id) })),
		consent_mode: { ...draft.consent_mode, signals: strip(draft.consent_mode.signals) },
		wp_consent_api: strip(draft.wp_consent_api),
		embeds: {
			category: draft.embeds.category === id ? '' : draft.embeds.category,
			providers: Object.fromEntries(
				Object.entries(draft.embeds.providers || {}).map(([key, value]) => [key, value === id ? '' : value]),
			),
		},
		gpc_categories: draft.gpc_categories.filter((x) => x !== id),
	};
}

/** Category ids as the server accepts them. */
export const isCategoryId = (id) => /^[a-z][a-z0-9_-]{0,31}$/.test(id);

/** A number field's value, or 0 when it is not a number. */
export function toInt(value) {
	const n = parseInt(value, 10);

	return Number.isNaN(n) ? 0 : n;
}

/** Adds or removes an id from a list, as a checkbox does. */
export const toggle = (ids, id, on) => (on ? [...ids, id] : ids.filter((x) => x !== id));
