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
import { SettingsApp } from '../components/SettingsApp';
import { RssImportApp } from '../components/RssImportApp';

const el = document.getElementById( 'newspack-lite-app' );

if ( el ) {
	const { page } = el.dataset;

	if ( page === 'settings' ) {
		createRoot( el ).render( <SettingsApp /> );
	} else if ( page === 'rss-feed-import' ) {
		createRoot( el ).render( <RssImportApp /> );
	}
}
