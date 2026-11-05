import { createContext, useContext } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

const SettingsContext = createContext(null);

export const SettingsProvider = SettingsContext.Provider;

/**
 * The form state shared by every tab:
 *
 * - draft: the settings being edited
 * - data: the server payload (effective, defaults, locked, textKeys)
 * - set(key, value) / setDraft(updater): change the draft
 * - isLocked(key): whether code overrides own the setting
 * - lockHelp(key, help): the help text, or a "set in code" note when locked
 */
export function useSettings() {
	return useContext(SettingsContext);
}

/** Builds the context value from the app's state. */
export function settingsValue(draft, data, setDraft) {
	const isLocked = (key) => data.locked.includes(key);

	return {
		draft,
		data,
		setDraft,
		set: (key, value) => setDraft((current) => ({ ...current, [key]: value })),
		isLocked,
		lockHelp: (key, help) =>
			isLocked(key) ? __('Set in code: change it in the site configuration.', 'mortar-consent') : help,
	};
}
