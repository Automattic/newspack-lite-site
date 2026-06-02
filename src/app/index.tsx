/**
 * Plugin admin entry point.
 */

/**
 * WordPress dependencies.
 */
import { createRoot } from '@wordpress/element';

/**
 * Internal dependencies.
 */
import { SettingsApp } from '../pages/SettingsApp';
import { RssImportApp } from '../pages/RssImportApp';
import '../style.scss';

const el = document.getElementById( 'newspack-lite-app' );

if ( el ) {
	const { page } = el.dataset;

	let App: typeof SettingsApp | typeof RssImportApp | null = null;

	if ( page === 'settings' ) {
		App = SettingsApp;
	} else if ( page === 'rss-feed-import' ) {
		App = RssImportApp;
	}

	if ( App ) {
		createRoot( el ).render( <App /> );
	}
}
