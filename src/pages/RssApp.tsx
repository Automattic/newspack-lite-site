/**
 * RSS page root component.
 */

/**
 * WordPress dependencies.
 */
import { useCallback, useEffect, useMemo, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * External dependencies.
 */
import Router from 'newspack-components/dist/esm/proxied-imports/router';
import Page from 'newspack-components/dist/esm/page';
import TabbedNavigation from 'newspack-components/dist/esm/tabbed-navigation';

/**
 * Internal dependencies.
 */
import { RssEntriesApp } from './RssEntriesApp';
import { RssImportApp } from './RssImportApp';
import { type RssTab, type RssTabHeader } from '../types/rss';

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

function getTabText( tab: RssTab ) {
	return 'entries' === tab
		? {
				label: __( 'Entries', 'newspack-lite-site' ),
				subTitle: __( 'Review, edit and remove the posts imported from RSS feeds.', 'newspack-lite-site' ),
			}
		: {
				label: __( 'Feeds', 'newspack-lite-site' ),
				subTitle: __( 'Set which RSS feeds to import and how often each one runs.', 'newspack-lite-site' ),
			};
}

/**
 * Routes between the Entries and Feeds tabs under one page header.
 *
 * Each tab mounts the first time it opens and stays mounted, so switching back keeps its list as it was.
 * The tabs are links in one persistent bar, so the focused tab stays focused when it is activated.
 */
const RssScreen = () => {
	const history = useHistory();
	const { pathname } = useLocation();
	const wrapRef = useRef< HTMLDivElement >( null );
	const [ hasFeeds, setHasFeeds ] = useState( rss?.hasFeeds ?? false );
	const showTabs = canManageFeeds && ( hasFeeds || hasEntries );
	const tab = getTab( pathname, showTabs );
	const [ visited, setVisited ] = useState< RssTab[] >( () => [ tab ] );

	const [ headers, setHeaders ] = useState< Partial< Record< RssTab, RssTabHeader > > >( {} );
	const onEntriesHeader = useCallback( ( header: RssTabHeader ) => setHeaders( current => ( { ...current, entries: header } ) ), [] );
	const onFeedsHeader = useCallback( ( header: RssTabHeader ) => setHeaders( current => ( { ...current, feeds: header } ) ), [] );
	const onFeedsChange = useCallback( ( count: number ) => setHasFeeds( count > 0 ), [] );

	const focusFeedsTab = useRef( false );
	const onGoToFeeds = useCallback( () => {
		focusFeedsTab.current = true;
	}, [] );

	useEffect( () => {
		if ( `/${ tab }` !== pathname ) {
			history.replace( `/${ tab }` );
		}
		setVisited( current => ( current.includes( tab ) ? current : [ ...current, tab ] ) );
		if ( 'feeds' === tab && focusFeedsTab.current ) {
			focusFeedsTab.current = false;
			wrapRef.current?.querySelector< HTMLElement >( '.newspack-tabbed-navigation a[href="#/feeds"]' )?.focus();
		}
	}, [ tab, pathname, history ] );

	const tabbedNavigation = useMemo(
		() =>
			showTabs ? (
				<TabbedNavigation
					items={ [
						{ label: __( 'Entries', 'newspack-lite-site' ), href: '#/entries', selected: 'entries' === tab },
						{ label: __( 'Feeds', 'newspack-lite-site' ), href: '#/feeds', selected: 'feeds' === tab },
					] }
				/>
			) : undefined,
		[ showTabs, tab ]
	);

	const { label, subTitle } = getTabText( tab );
	const header = headers[ tab ] ?? {};

	return (
		<div className="wrap" ref={ wrapRef }>
			<Page
				subTitle={ subTitle }
				tabbedNavigation={ tabbedNavigation }
				breadcrumbItems={ [
					{ label: 'Lite Site' },
					{ label: __( 'RSS', 'newspack-lite-site' ) },
					{ label, count: header.count, countLabel: header.countLabel },
				] }
				actions={ header.actions }
			>
				{ visited.includes( 'entries' ) && (
					<div hidden={ 'entries' !== tab }>
						<RssEntriesApp
							isActive={ 'entries' === tab }
							canManageFeeds={ canManageFeeds }
							onHeaderChange={ onEntriesHeader }
							onGoToFeeds={ onGoToFeeds }
						/>
					</div>
				) }
				{ canManageFeeds && visited.includes( 'feeds' ) && (
					<div hidden={ 'feeds' !== tab }>
						<RssImportApp isActive={ 'feeds' === tab } onHeaderChange={ onFeedsHeader } onFeedsChange={ onFeedsChange } />
					</div>
				) }
			</Page>
		</div>
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
