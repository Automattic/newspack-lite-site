/**
 * RSS feeds data hook.
 */

/**
 * WordPress dependencies.
 */
import { useState, useEffect, useCallback, useRef } from '@wordpress/element';
import apiFetch, { type APIFetchOptions } from '@wordpress/api-fetch';
import { __, _n, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import { revealEntriesMenu } from '../utils/entries-menu';
import { formatLastResult } from '../utils/formatters';
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
	const [ isImporting, setIsImporting ] = useState( false );
	const [ saveError, setSaveError ] = useState< string | null >( null );
	const [ notice, setNotice ] = useState< Notice | null >( null );

	const requestCounter = useRef( 0 );
	const appliedRequest = useRef( 0 );

	const applyFeeds = useCallback( ( requestId: number, data: Feed[] ) => {
		if ( requestId > appliedRequest.current ) {
			appliedRequest.current = requestId;
			setFeeds( data );
		}
	}, [] );

	const isDrawerOpenRef = useRef( isDrawerOpen );
	isDrawerOpenRef.current = isDrawerOpen;

	const showNotice = useCallback( ( message: string, status: 'success' | 'error' = 'success' ) => {
		setNotice( { message, status } );
		const timeout = 'error' === status ? 6000 : 3000;
		setTimeout( () => setNotice( null ), timeout );
	}, [] );

	useEffect( () => {
		let cancelled = false;

		const requestId = ++requestCounter.current;

		apiFetch< Feed[] >( { path: FEEDS_PATH } )
			.then( data => {
				if ( ! cancelled ) {
					applyFeeds( requestId, data );
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

			const requestId = ++requestCounter.current;

			try {
				const updated = await apiFetch< Feed[] >( request );
				applyFeeds( requestId, updated );
				if ( updated.length > 0 ) {
					revealEntriesMenu();
				}
				showNotice( successMessage );
				return true;
			} catch ( err ) {
				const message = ( err as Error ).message || fallbackError;
				setSaveError( message );
				if ( notifyError || ! isDrawerOpenRef.current ) {
					showNotice( message, 'error' );
				}
				const refetchId = ++requestCounter.current;
				apiFetch< Feed[] >( { path: FEEDS_PATH } )
					.then( data => applyFeeds( refetchId, data ) )
					.catch( () => {} );
				return false;
			} finally {
				setIsSaving( false );
			}
		},
		[ showNotice, applyFeeds ]
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

	const deleteFeeds = useCallback(
		( feedIds: string[] ) =>
			mutate(
				{ path: `${ FEEDS_PATH }/delete`, method: 'POST', data: { ids: feedIds } },
				1 === feedIds.length
					? __( 'RSS feed deleted.', 'newspack-lite-site' )
					: sprintf(
							/* translators: %d: number of RSS feeds deleted. */
							_n( '%d RSS feed deleted.', '%d RSS feeds deleted.', feedIds.length, 'newspack-lite-site' ),
							feedIds.length
						),
				1 === feedIds.length
					? __( "Couldn't delete the RSS feed. Try again.", 'newspack-lite-site' )
					: __( "Couldn't delete the RSS feeds. Try again.", 'newspack-lite-site' ),
				true
			),
		[ mutate ]
	);

	const importFeed = useCallback(
		async ( feedId: string ) => {
			setIsImporting( true );
			setSaveError( null );
			const requestId = ++requestCounter.current;
			let failure: string | null = null;
			let succeeded = false;
			try {
				const updated = await apiFetch< Feed[] >( {
					path: `${ FEEDS_PATH }/${ feedId }/action`,
					method: 'POST',
					data: { action: 'import' },
				} );
				applyFeeds( requestId, updated );
				const result = updated.find( item => item.id === feedId )?.last_result ?? null;
				if ( result?.error ) {
					failure = result.error;
				} else {
					succeeded = true;
					if ( updated.length > 0 ) {
						revealEntriesMenu();
					}
					showNotice( formatLastResult( result ) || __( 'RSS feed imported.', 'newspack-lite-site' ) );
				}
			} catch ( err ) {
				const message = ( err as Error ).message || __( "Couldn't import the RSS feed. Try again.", 'newspack-lite-site' );
				failure = message;
				if ( isDrawerOpenRef.current ) {
					setSaveError( message );
					failure = null;
				}
			} finally {
				setIsImporting( false );
			}

			if ( failure && ! isDrawerOpenRef.current ) {
				showNotice( failure, 'error' );
			}

			if ( ! succeeded || appliedRequest.current !== requestId ) {
				const refetchId = ++requestCounter.current;
				apiFetch< Feed[] >( { path: FEEDS_PATH } )
					.then( data => applyFeeds( refetchId, data ) )
					.catch( () => {} );
			}

			return succeeded;
		},
		[ showNotice, applyFeeds ]
	);

	const clearSaveError = useCallback( () => setSaveError( null ), [] );

	return {
		feeds,
		isLoading,
		isSaving,
		saveError,
		notice,
		showNotice,
		addFeed,
		updateFeed,
		deleteFeeds,
		importFeed,
		isImporting,
		clearSaveError,
	};
}
