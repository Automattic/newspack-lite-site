/**
 * Settings & Appearance page entry point.
 */

/**
 * WordPress dependencies.
 */
import { createRoot, StrictMode } from '@wordpress/element';

/**
 * Internal dependencies.
 */
import './style.scss';
import { App } from './components/App';

const el = document.getElementById( 'newspack-lite-settings-app' );
if ( el ) {
	createRoot( el ).render(
		<StrictMode>
			<App />
		</StrictMode>
	);
}
