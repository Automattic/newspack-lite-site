/**
 * RSS page root component.
 */

/**
 * WordPress dependencies.
 */
import { useCallback, useEffect, useMemo, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * External dependencies.
 */
import Router from 'newspack-components/dist/esm/proxied-imports/router';
import TabbedNavigation from 'newspack-components/dist/esm/tabbed-navigation';

/**
 * Internal dependencies.
 */
import { RssEntriesApp } from './RssEntriesApp';
import { RssImportApp } from './RssImportApp';
import { type RssTab } from '../types/rss';

const { HashRouter, useHistory, useLocation } = Router;

const rss = window.newspackLiteSite?.rss;
const canManageFeeds = rss?.canManageFeeds ?? false;
const hasEntries = ( rss?.hasEntries ?? false ) || !! window.newspackLiteSite?.rssEntries?.result;
const defaultTab: RssTab = rss?.defaultTab ?? 'entries';

function getTab( pathname: string, showTabs: boolean ): RssTab {
	if ( ! canManageFeeds ) {
		return 'entries';
	}
	if ( ! showTabs ) {
		return 'feeds';
	}
	if ( '/entries' === pathname || '/feeds' === pathname ) {
		return pathname.slice( 1 ) as RssTab;
	}
	return defaultTab;
}

/**
 * Routes between the Entries and Feeds tabs.
 *
 * Each tab mounts the first time it opens and stays mounted, so switching back keeps its list as it was.
 */
const RssScreen = () => {
	const history = useHistory();
	const { pathname } = useLocation();
	const [ hasFeeds, setHasFeeds ] = useState( rss?.hasFeeds ?? false );
	const showTabs = canManageFeeds && ( hasFeeds || hasEntries );
	const tab = getTab( pathname, showTabs );
	const [ visited, setVisited ] = useState< RssTab[] >( () => [ tab ] );
	const onFeedsChange = useCallback( ( count: number ) => setHasFeeds( count > 0 ), [] );

	useEffect( () => {
		if ( `/${ tab }` !== pathname ) {
			history.replace( `/${ tab }` );
		}
		setVisited( current => ( current.includes( tab ) ? current : [ ...current, tab ] ) );
	}, [ tab, pathname, history ] );

	const tabbedNavigation = useMemo(
		() =>
			showTabs ? (
				<TabbedNavigation
					items={ [
						{ label: __( 'Entries', 'newspack-lite-site' ), path: '/entries' },
						{ label: __( 'Feeds', 'newspack-lite-site' ), path: '/feeds' },
					] }
				/>
			) : undefined,
		[ showTabs ]
	);

	return (
		<>
			{ visited.includes( 'entries' ) && (
				<div hidden={ 'entries' !== tab }>
					<RssEntriesApp tabbedNavigation={ tabbedNavigation } isActive={ 'entries' === tab } canManageFeeds={ canManageFeeds } />
				</div>
			) }
			{ canManageFeeds && visited.includes( 'feeds' ) && (
				<div hidden={ 'feeds' !== tab }>
					<RssImportApp tabbedNavigation={ tabbedNavigation } isActive={ 'feeds' === tab } onFeedsChange={ onFeedsChange } />
				</div>
			) }
		</>
	);
};

/**
 * Root component for the RSS page, with Entries and Feeds tabs routed by the URL hash.
 */
export const RssApp = () => (
	<HashRouter>
		<RssScreen />
	</HashRouter>
);
