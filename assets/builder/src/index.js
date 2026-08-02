import { createRoot, render } from '@wordpress/element';

import App from './App';
import './style.css';

const mountPoint = document.getElementById('dragwyb-af-builder-root');

if (mountPoint) {
	// createRoot is only available in the @wordpress/element versions that
	// ship with React 18 (WP 6.2+). Older WordPress installs still expose
	// @wordpress/element's legacy `render`, which this plugin's "Requires
	// at least: 5.8" promise needs to keep working on.
	if (typeof createRoot === 'function') {
		createRoot(mountPoint).render(<App />);
	} else {
		render(<App />, mountPoint);
	}
}
