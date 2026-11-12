/**
 * RSS feeds data hook.
 */

/**
 * WordPress dependencies.
 */
import { useState, useEffect, useCallback, useRef } from '@wordpress/element';
import apiFetch, { type APIFetchOptions } from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import { type Feed, type FeedChanges, type NewFeedData, type Notice } from '../types/rss-feed-import';

const FEEDS_PATH = '/newspack-lite-site/v1/rss-feeds';

/**
 * Manages RSS feed state and all REST API interactions for the RSS Import page.
 *
 * Fetches the RSS feed list on mount. Each mutation (add, update, delete)
 * updates local state from the server's response to stay in sync, and resolves
 * to whether it succeeded so the caller knows if it can close its drawer. A failure
 * is also shown as a notice when the drawer is no longer open to show it.
 */
export function useFeeds( isDrawerOpen = false ) {
	const [ feeds, setFeeds ] = useState< Feed[] >( [] );
	const [ isLoading, setIsLoading ] = useState( true );
	const [ isSaving, setIsSaving ] = useState( false );
	const [ saveError, setSaveError ] = useState< string | null >( null );
	const [ notice, setNotice ] = useState< Notice | null >( null );

	const isDrawerOpenRef = useRef( isDrawerOpen );
	isDrawerOpenRef.current = isDrawerOpen;

	const showNotice = useCallback( ( message: string, status: 'success' | 'error' = 'success' ) => {
		setNotice( { message, status } );
		const timeout = 'error' === status ? 6000 : 3000;
		setTimeout( () => setNotice( null ), timeout );
	}, [] );

	useEffect( () => {
		let cancelled = false;

		apiFetch< Feed[] >( { path: FEEDS_PATH } )
			.then( data => {
				if ( ! cancelled ) {
					setFeeds( data );
					setIsLoading( false );
				}
			} )
			.catch( ( err: Error ) => {
				if ( ! cancelled ) {
					showNotice( err.message ?? __( "Couldn't load RSS feeds. Reload the page to try again.", 'newspack-lite-site' ), 'error' );
					setIsLoading( false );
				}
			} );

		return () => {
			cancelled = true;
		};
	}, [] );

	const mutate = useCallback(
		async ( request: APIFetchOptions< true >, successMessage: string, fallbackError: string, notifyError = false ) => {
			setIsSaving( true );
			setSaveError( null );

			try {
				const updated = await apiFetch< Feed[] >( request );
				setFeeds( updated );
				showNotice( successMessage );
				return true;
			} catch ( err ) {
				const message = ( err as Error ).message || fallbackError;
				setSaveError( message );
				if ( notifyError || ! isDrawerOpenRef.current ) {
					showNotice( message, 'error' );
				}
				return false;
			} finally {
				setIsSaving( false );
			}
		},
		[ showNotice ]
	);

	const addFeed = useCallback(
		( data: NewFeedData ) =>
			mutate(
				{ path: FEEDS_PATH, method: 'POST', data },
				__( 'RSS feed added.', 'newspack-lite-site' ),
				__( "Couldn't add the RSS feed. Try again.", 'newspack-lite-site' )
			),
		[ mutate ]
	);

	const updateFeed = useCallback(
		( feedId: string, changes: FeedChanges ) =>
			mutate(
				{ path: `${ FEEDS_PATH }/${ feedId }`, method: 'PATCH', data: changes },
				__( 'RSS feed updated.', 'newspack-lite-site' ),
				__( "Couldn't update the RSS feed. Try again.", 'newspack-lite-site' )
			),
		[ mutate ]
	);

	const deleteFeed = useCallback(
		( feedId: string ) =>
			mutate(
				{ path: `${ FEEDS_PATH }/${ feedId }/action`, method: 'POST', data: { action: 'delete' } },
				__( 'RSS feed deleted.', 'newspack-lite-site' ),
				__( "Couldn't delete the RSS feed. Try again.", 'newspack-lite-site' ),
				true
			),
		[ mutate ]
	);

	const clearSaveError = useCallback( () => setSaveError( null ), [] );

	return {
		feeds,
		isLoading,
		isSaving,
		saveError,
		notice,
		addFeed,
		updateFeed,
		deleteFeed,
		clearSaveError,
	};
}
