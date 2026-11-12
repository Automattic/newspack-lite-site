/**
 * State for the RSS page's Feeds tab and its drawer.
 */

/**
 * WordPress dependencies.
 */
import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import { useFeeds } from './useFeeds';
import { type Feed } from '../types/rss-feed-import';

/**
 * Holds the feed list and the add/edit drawer for the Feeds tab.
 *
 * The RSS page keeps this above its page header, because the header changes shape when the tabs first appear,
 * which would otherwise reset the tab's content mid-add.
 *
 * @param enabled Whether the current user can manage feeds. When false, nothing is loaded.
 */
export function useFeedsTab( enabled: boolean ) {
	const [ isDrawerOpen, setIsDrawerOpen ] = useState( false );
	const feedsApi = useFeeds( isDrawerOpen, enabled );
	const { feeds, isLoading, showNotice, clearSaveError } = feedsApi;
	const [ drawerFeedId, setDrawerFeedId ] = useState< string | null >( null );
	const lastDrawerFeed = useRef< Feed | null >( null );
	const found = drawerFeedId ? feeds.find( item => item.id === drawerFeedId ) : undefined;
	if ( found ) {
		lastDrawerFeed.current = found;
	} else if ( ! drawerFeedId ) {
		lastDrawerFeed.current = null;
	}
	const drawerFeed = drawerFeedId ? lastDrawerFeed.current : null;
	const isFeedMissing = isDrawerOpen && null !== drawerFeedId && ! isLoading && ! found;

	useEffect( () => {
		if ( isFeedMissing ) {
			setIsDrawerOpen( false );
			showNotice( __( 'This RSS feed no longer exists.', 'newspack-lite-site' ), 'error' );
		}
	}, [ isFeedMissing, showNotice ] );

	const openAdd = useCallback( () => {
		clearSaveError();
		setDrawerFeedId( null );
		setIsDrawerOpen( true );
	}, [ clearSaveError ] );

	const openEdit = useCallback(
		( feed: Feed ) => {
			clearSaveError();
			setDrawerFeedId( feed.id );
			setIsDrawerOpen( true );
		},
		[ clearSaveError ]
	);

	const closeDrawer = useCallback( () => setIsDrawerOpen( false ), [] );

	return { ...feedsApi, isDrawerOpen, drawerFeed, openAdd, openEdit, closeDrawer };
}
