// Settings → Cookie consent. Built by `pnpm build` into assets/admin/.
import { createRoot } from '@wordpress/element';

import App from './App.js';

const root = document.getElementById('mortar-consent-admin');

if (root) {
	createRoot(root).render(<App />);
}
