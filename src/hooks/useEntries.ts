/**
 * RSS entries data hook.
 */

/**
 * WordPress dependencies.
 */
import { useState, useEffect, useCallback, useRef } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { __, _n, sprintf } from '@wordpress/i18n';
import { type Filter, type View } from '@wordpress/dataviews';

/**
 * Internal dependencies.
 */
import { type EntriesNotice, type EntryMutation, type RssEntry } from '../types/rss-entries';

const ENTRIES_PATH = '/wp/v2/nls_rss_entry';
const FIELDS = 'id,title,date,status,author,categories,tags,link,feed_id,feed_label,source_url,author_name,author_avatar_urls,lite_url';
const SEARCH_DEBOUNCE_MS = 300;
const SORT_FIELDS = [ 'date', 'title', 'author' ];

function getFilterValues( filters: Filter[] | undefined, field: string ): string[] {
	const filter = filters?.find( item => item.field === field );
	if ( ! filter || filter.value === undefined || filter.value === '' ) {
		return [];
	}
	return ( Array.isArray( filter.value ) ? filter.value : [ filter.value ] ).map( String );
}

function buildQuery( view: View, search: string ): string {
	const params = new URLSearchParams();
	const statuses = getFilterValues( view.filters, 'status' );
	const authors = getFilterValues( view.filters, 'author' );
	const categories = getFilterValues( view.filters, 'categories' );
	const tags = getFilterValues( view.filters, 'tags' );
	const feed = getFilterValues( view.filters, 'feed' )[ 0 ];
	const orderby = SORT_FIELDS.includes( view.sort?.field ?? '' ) ? view.sort?.field : 'date';

	params.set( 'context', 'edit' );
	params.set( '_fields', FIELDS );
	params.set( 'page', String( view.page ?? 1 ) );
	params.set( 'per_page', String( view.perPage ?? 20 ) );
	params.set( 'orderby', orderby as string );
	params.set( 'order', view.sort?.direction ?? 'desc' );
	params.set( 'status', statuses.length ? statuses.join( ',' ) : 'any' );
	if ( search ) {
		params.set( 'search', search );
	}
	if ( authors.length ) {
		params.set( 'author', authors.join( ',' ) );
	}
	if ( categories.length ) {
		params.set( 'categories', categories.join( ',' ) );
	}
	if ( tags.length ) {
		params.set( 'tags', tags.join( ',' ) );
	}
	if ( feed ) {
		params.set( 'feed', feed );
	}
	return params.toString();
}

/**
 * The confirmation shown after entries were trashed, restored or deleted.
 *
 * @param mutation The mutation that was applied.
 * @param count    Number of entries it applied to.
 */
export function getMutationMessage( mutation: EntryMutation, count: number ): string {
	switch ( mutation ) {
		case 'trash':
			return sprintf(
				/* translators: %d: number of entries */
				_n( '%d entry moved to the trash.', '%d entries moved to the trash.', count, 'newspack-lite-site' ),
				count
			);
		case 'restore':
			return sprintf(
				/* translators: %d: number of entries */
				_n( '%d entry restored.', '%d entries restored.', count, 'newspack-lite-site' ),
				count
			);
		default:
			return sprintf(
				/* translators: %d: number of entries */
				_n( '%d entry permanently deleted.', '%d entries permanently deleted.', count, 'newspack-lite-site' ),
				count
			);
	}
}

async function getErrorMessage( err: unknown, fallback: string ): Promise< string > {
	if ( err instanceof Response ) {
		try {
			const body = await err.json();
			return body?.message || fallback;
		} catch {
			return fallback;
		}
	}
	return ( err as Error )?.message || fallback;
}

/**
 * Loads RSS entries for the given DataViews view and exposes bulk mutations.
 *
 * Fetches a server-paginated page whenever the view changes, with search input
 * debounced and stale responses ignored. Mutations run in parallel, then the
 * current page is fetched again.
 *
 * @param view Current DataViews view.
 */
export function useEntries( view: View ) {
	const [ entries, setEntries ] = useState< RssEntry[] >( [] );
	const [ totalItems, setTotalItems ] = useState( 0 );
	const [ totalPages, setTotalPages ] = useState( 0 );
	const [ isLoading, setIsLoading ] = useState( true );
	const [ isFetching, setIsFetching ] = useState( true );
	const [ isEmpty, setIsEmpty ] = useState( false );
	const [ notice, setNotice ] = useState< EntriesNotice | null >( null );
	const [ reloadKey, setReloadKey ] = useState( 0 );
	const [ search, setSearch ] = useState( view.search ?? '' );

	const requestId = useRef( 0 );
	const isFirstLoad = useRef( true );
	const noticeTimer = useRef< ReturnType< typeof setTimeout > | undefined >( undefined );

	const dismissNotice = useCallback( () => {
		clearTimeout( noticeTimer.current );
		setNotice( null );
	}, [] );

	const showNotice = useCallback( ( message: string, status: EntriesNotice[ 'status' ] = 'success', actions?: EntriesNotice[ 'actions' ] ) => {
		clearTimeout( noticeTimer.current );
		setNotice( { message, status, actions } );
		noticeTimer.current = setTimeout( () => setNotice( null ), 'error' === status || actions?.length ? 6000 : 3000 );
	}, [] );

	useEffect( () => {
		const timeout = setTimeout( () => setSearch( view.search ?? '' ), SEARCH_DEBOUNCE_MS );
		return () => clearTimeout( timeout );
	}, [ view.search ] );

	const query = buildQuery( view, search );

	useEffect( () => {
		const id = ++requestId.current;
		const isUnfiltered = ! search && ! view.filters?.length;
		setIsFetching( true );

		apiFetch< Response, false >( { path: `${ ENTRIES_PATH }?${ query }`, parse: false } )
			.then( async response => {
				const data: RssEntry[] = await response.json();
				if ( id !== requestId.current ) {
					return;
				}
				const total = parseInt( response.headers.get( 'X-WP-Total' ) ?? '0', 10 );
				setEntries( data );
				setTotalItems( total );
				setTotalPages( parseInt( response.headers.get( 'X-WP-TotalPages' ) ?? '0', 10 ) );
				if ( isFirstLoad.current ) {
					setIsEmpty( isUnfiltered && 0 === total );
				}
			} )
			.catch( async err => {
				const message = await getErrorMessage( err, __( "Couldn't load RSS entries. Reload the page to try again.", 'newspack-lite-site' ) );
				if ( id === requestId.current ) {
					showNotice( message, 'error' );
				}
			} )
			.finally( () => {
				if ( id === requestId.current ) {
					isFirstLoad.current = false;
					setIsLoading( false );
					setIsFetching( false );
				}
			} );
	}, [ query, reloadKey ] );

	useEffect( () => () => clearTimeout( noticeTimer.current ), [] );

	const mutate = useCallback(
		async ( items: Array< Pick< RssEntry, 'id' > >, mutation: EntryMutation ) => {
			setIsFetching( true );

			const results = await Promise.allSettled(
				items.map( item => {
					if ( 'restore' === mutation ) {
						return apiFetch( {
							path: `${ ENTRIES_PATH }/${ item.id }`,
							method: 'POST',
							data: { status: 'draft' },
						} );
					}
					return apiFetch( {
						path: `${ ENTRIES_PATH }/${ item.id }${ 'delete' === mutation ? '?force=true' : '' }`,
						method: 'DELETE',
					} );
				} )
			);

			const succeeded = results.filter( result => 'fulfilled' === result.status ).length;
			const failed = items.length - succeeded;

			if ( succeeded > 0 ) {
				showNotice( getMutationMessage( mutation, succeeded ) );
			}

			if ( failed > 0 ) {
				const failureMessages = {
					trash: sprintf(
						/* translators: 1: number of entries that failed, 2: total number of entries */
						_n(
							"%1$d of %2$d entry couldn't be moved to the trash.",
							"%1$d of %2$d entries couldn't be moved to the trash.",
							items.length,
							'newspack-lite-site'
						),
						failed,
						items.length
					),
					restore: sprintf(
						/* translators: 1: number of entries that failed, 2: total number of entries */
						_n(
							"%1$d of %2$d entry couldn't be restored.",
							"%1$d of %2$d entries couldn't be restored.",
							items.length,
							'newspack-lite-site'
						),
						failed,
						items.length
					),
					delete: sprintf(
						/* translators: 1: number of entries that failed, 2: total number of entries */
						_n(
							"%1$d of %2$d entry couldn't be deleted.",
							"%1$d of %2$d entries couldn't be deleted.",
							items.length,
							'newspack-lite-site'
						),
						failed,
						items.length
					),
				};
				showNotice( failureMessages[ mutation ], 'error' );
			}

			setReloadKey( key => key + 1 );
		},
		[ showNotice ]
	);

	return { entries, totalItems, totalPages, isLoading, isFetching, isEmpty, notice, showNotice, dismissNotice, mutate };
}
