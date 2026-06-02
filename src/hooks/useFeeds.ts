/**
 * RSS feeds data hook.
 */

/**
 * WordPress dependencies.
 */
import { useState, useEffect, useCallback } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import {
	type Feed,
	type FeedAction,
	type NewFeedData,
	type Notice,
} from '../types/rss-feed-import';

const FEEDS_PATH = '/newspack-lite-site/v1/rss-feeds';

/**
 * Manages RSS feed state and all REST API interactions for the RSS Import page.
 *
 * Fetches the feed list on mount. Each mutation (add, pause, resume, delete)
 * updates local state from the server's response to stay in sync.
 */
export function useFeeds() {
	const [ feeds, setFeeds ] = useState< Feed[] >( [] );
	const [ isLoading, setIsLoading ] = useState( true );
	const [ isAdding, setIsAdding ] = useState( false );
	const [ actionInProgress, setActionInProgress ] = useState< string | null >(
		null
	);
	const [ notice, setNotice ] = useState< Notice | null >( null );

	const showNotice = useCallback(
		( message: string, status: 'success' | 'error' = 'success' ) => {
			setNotice( { message, status } );
			const timeout = 'error' === status ? 6000 : 3000;
			setTimeout( () => setNotice( null ), timeout );
		},
		[]
	);

	useEffect( () => {
		let cancelled = false;

		apiFetch< Feed[] >( { path: FEEDS_PATH } )
			.then( ( data ) => {
				if ( ! cancelled ) {
					setFeeds( data );
					setIsLoading( false );
				}
			} )
			.catch( ( err: Error ) => {
				if ( ! cancelled ) {
					showNotice(
						err.message ??
							__( 'Failed to load feeds.', 'newspack-lite-site' ),
						'error'
					);
					setIsLoading( false );
				}
			} );

		return () => {
			cancelled = true;
		};
	}, [] );

	const addFeed = useCallback(
		async ( data: NewFeedData ) => {
			setIsAdding( true );

			try {
				const updated = await apiFetch< Feed[] >( {
					path: FEEDS_PATH,
					method: 'POST',
					data,
				} );
				setFeeds( updated );
				showNotice( __( 'Feed added.', 'newspack-lite-site' ) );
				return true;
			} catch ( err ) {
				showNotice(
					( err as Error ).message ??
						__( 'Failed to add feed.', 'newspack-lite-site' ),
					'error'
				);
				return false;
			} finally {
				setIsAdding( false );
			}
		},
		[ showNotice ]
	);

	const feedAction = useCallback(
		async ( feedId: string, action: FeedAction ) => {
			setActionInProgress( feedId );

			try {
				const updated = await apiFetch< Feed[] >( {
					path: `${ FEEDS_PATH }/${ feedId }/action`,
					method: 'POST',
					data: { action },
				} );
				setFeeds( updated );

				const messages: Record< FeedAction, string > = {
					pause: __( 'Feed paused.', 'newspack-lite-site' ),
					resume: __( 'Feed resumed.', 'newspack-lite-site' ),
					delete: __( 'Feed deleted.', 'newspack-lite-site' ),
				};
				showNotice(
					messages[ action ] ??
						__( 'Feed updated.', 'newspack-lite-site' )
				);
			} catch ( err ) {
				showNotice(
					( err as Error ).message ??
						__( 'Failed to update feed.', 'newspack-lite-site' ),
					'error'
				);
			} finally {
				setActionInProgress( null );
			}
		},
		[ showNotice ]
	);

	return {
		feeds,
		isLoading,
		isAdding,
		actionInProgress,
		notice,
		addFeed,
		feedAction,
	};
}
