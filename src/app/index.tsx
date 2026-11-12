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
import { RssEntriesApp } from '../pages/RssEntriesApp';
import '../style.scss';

const el = document.getElementById( 'newspack-lite-app' );

if ( el ) {
	const { page } = el.dataset;

	let App: typeof SettingsApp | typeof RssImportApp | typeof RssEntriesApp | null = null;

	if ( page === 'settings' ) {
		App = SettingsApp;
	} else if ( page === 'rss-feed-import' ) {
		App = RssImportApp;
	} else if ( page === 'rss-entries' ) {
		App = RssEntriesApp;
	}

	if ( App ) {
		createRoot( el ).render( <App /> );
	}
}
