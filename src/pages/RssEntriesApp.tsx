/**
 * RSS Entries page root component.
 */

/**
 * WordPress dependencies.
 */
import { useCallback, useEffect, useMemo, useRef, useState } from '@wordpress/element';
import { Button, Notice, Snackbar } from '@wordpress/components';
import { __, _n, sprintf } from '@wordpress/i18n';
import { rss } from '@wordpress/icons';
import { type Action, type RenderModalProps, type View } from '@wordpress/dataviews';
import { Stack } from '@wordpress/ui';

/**
 * External dependencies.
 */
import Page from 'newspack-components/dist/esm/page';
import { EmptyState } from 'newspack-components/dist/esm/empty-state';
import Waiting from 'newspack-components/dist/esm/waiting';

/**
 * Internal dependencies.
 */
import { getMutationMessage, useEntries } from '../hooks/useEntries';
import { InlineFiltersDataViews } from '../components/InlineFiltersDataViews';
import { DeleteEntriesModal } from '../components/DeleteEntriesModal';
import { DEFAULT_VIEW, getEntryFields } from '../utils/entries-dataview';
import { formatCount } from '../utils/format-count';
import { type EntryMutation, type RssEntry } from '../types/rss-entries';

const countLabel = ( count: number, formatted: string ) =>
	/* translators: %s: number of RSS entries. */
	sprintf( _n( '%s RSS entry', '%s RSS entries', count, 'newspack-lite-site' ), formatted );

const rssEntries = window.newspackLiteSite?.rssEntries;

const RESULT_ARGS: Record< string, EntryMutation > = { trashed: 'trash', untrashed: 'restore', deleted: 'delete' };

/**
 * Read the result of an action taken on another screen, such as trashing an entry in the editor.
 */
function getForwardedResult(): { mutation: EntryMutation; count: number; ids: number[] } | null {
	const params = new URLSearchParams( window.location.search );
	const ids = ( params.get( 'ids' ) ?? '' )
		.split( ',' )
		.map( Number )
		.filter( id => Number.isInteger( id ) && id > 0 );

	for ( const [ key, mutation ] of Object.entries( RESULT_ARGS ) ) {
		const count = parseInt( params.get( key ) ?? '', 10 );
		if ( count > 0 ) {
			return { mutation, count: ids.length || count, ids };
		}
	}
	return null;
}

/**
 * Remove the forwarded result from the URL, so reloading the page doesn't repeat its notice.
 */
function clearForwardedResult() {
	const url = new URL( window.location.href );
	const keys = [ ...Object.keys( RESULT_ARGS ), 'ids' ];
	if ( keys.some( key => url.searchParams.has( key ) ) ) {
		keys.forEach( key => url.searchParams.delete( key ) );
		window.history.replaceState( window.history.state, '', url.toString() );
	}
}

function getInitialView(): View {
	const params = new URLSearchParams( window.location.search );
	const status = params.get( 'post_status' );
	const search = params.get( 's' );
	const author = params.get( 'author' );
	const filters: NonNullable< View[ 'filters' ] > = [];

	if ( status && 'all' !== status ) {
		filters.push( { field: 'status', operator: 'isAny', value: [ status ] } );
	}
	if ( author ) {
		filters.push( { field: 'author', operator: 'isAny', value: [ author ] } );
	}

	return { ...DEFAULT_VIEW, search: search ?? '', filters };
}

/**
 * Root component for the RSS Entries page.
 *
 * Lists imported RSS entries in a server-paginated table with filters and bulk actions.
 */
export const RssEntriesApp = () => {
	const [ view, setView ] = useState< View >( getInitialView );
	const setPage = useCallback( ( page: number ) => setView( current => ( { ...current, page } ) ), [] );
	const { entries, totalItems, totalPages, isLoading, isFetching, isEmpty, loadError, notice, showNotice, dismissNotice, mutate } = useEntries(
		view,
		setPage
	);
	const forwardedResult = useRef( getForwardedResult() );

	useEffect( () => {
		const result = forwardedResult.current;
		forwardedResult.current = null;
		clearForwardedResult();
		if ( ! result ) {
			return;
		}
		const canUndo = 'trash' === result.mutation && result.ids.length > 0;
		showNotice(
			getMutationMessage( result.mutation, result.count ),
			'success',
			canUndo
				? [
						{
							label: __( 'Undo', 'newspack-lite-site' ),
							onClick: () =>
								mutate(
									result.ids.map( id => ( { id } ) ),
									'restore'
								),
						},
					]
				: undefined
		);
	}, [ showNotice, mutate ] );

	const editUrl = rssEntries?.editUrl ?? '';
	const importUrl = rssEntries?.importUrl ?? '';
	const addUrl = rssEntries?.addUrl ?? '';
	const showCount = ! isLoading && ! isEmpty && ! loadError;
	const fields = useMemo( () => getEntryFields( editUrl ), [ editUrl ] );

	const actions: Action< RssEntry >[] = useMemo(
		() => [
			{
				id: 'edit-entry',
				label: __( 'Edit', 'newspack-lite-site' ),
				isPrimary: true,
				isEligible: item => 'trash' !== item.status,
				callback: items => {
					window.location.href = `${ editUrl }?post=${ items[ 0 ].id }&action=edit`;
				},
			},
			{
				id: 'view-lite-entry',
				label: __( 'View on Lite Site', 'newspack-lite-site' ),
				isEligible: item => !! item.lite_url,
				callback: items => {
					window.open( items[ 0 ].lite_url, '_blank', 'noopener,noreferrer' );
				},
			},
			{
				id: 'view-original-entry',
				label: __( 'View Original', 'newspack-lite-site' ),
				isEligible: item => !! item.source_url,
				callback: items => {
					window.open( items[ 0 ].source_url, '_blank', 'noopener,noreferrer' );
				},
			},
			{
				id: 'trash-entry',
				label: __( 'Move to Trash', 'newspack-lite-site' ),
				supportsBulk: true,
				isEligible: item => 'trash' !== item.status,
				callback: async ( items, { onActionPerformed } ) => {
					await mutate( items, 'trash' );
					onActionPerformed?.( items );
				},
			},
			{
				id: 'restore-entry',
				label: __( 'Restore', 'newspack-lite-site' ),
				supportsBulk: true,
				isEligible: item => 'trash' === item.status,
				callback: async ( items, { onActionPerformed } ) => {
					await mutate( items, 'restore' );
					onActionPerformed?.( items );
				},
			},
			{
				id: 'delete-entry',
				label: __( 'Delete Permanently', 'newspack-lite-site' ),
				isDestructive: true,
				supportsBulk: true,
				isEligible: item => 'trash' === item.status,
				RenderModal: ( props: RenderModalProps< RssEntry > ) => (
					<DeleteEntriesModal { ...props } onConfirm={ items => mutate( items, 'delete' ) } />
				),
			},
		],
		[ editUrl, mutate ]
	);

	const paginationInfo = useMemo( () => ( { totalItems, totalPages } ), [ totalItems, totalPages ] );

	let content;
	if ( isLoading ) {
		content = (
			<div className="newspack-lite-loader">
				<Stack direction="column" align="center" gap="sm">
					<Waiting noMargin />
					<strong>{ __( 'Fetching…', 'newspack-lite-site' ) }</strong>
				</Stack>
			</div>
		);
	} else if ( loadError ) {
		content = (
			<Notice status="error" isDismissible={ false } politeness="polite">
				{ loadError }
			</Notice>
		);
	} else if ( isEmpty ) {
		content = (
			<EmptyState.Root>
				<EmptyState.Header
					icon={ rss }
					title={ __( 'No RSS entries yet', 'newspack-lite-site' ) }
					description={ __( 'Entries appear here after an RSS feed import runs.', 'newspack-lite-site' ) }
				/>
				{ importUrl && (
					<EmptyState.Actions>
						<Button variant="primary" href={ importUrl }>
							{ __( 'Go to RSS Feed Import', 'newspack-lite-site' ) }
						</Button>
					</EmptyState.Actions>
				) }
			</EmptyState.Root>
		);
	} else {
		content = (
			<InlineFiltersDataViews
				data={ entries }
				fields={ fields }
				view={ view }
				onChangeView={ setView }
				getItemId={ ( item: RssEntry ) => String( item.id ) }
				paginationInfo={ paginationInfo }
				actions={ actions }
				defaultLayouts={ { table: {} } }
				isLoading={ isFetching }
				countLabel={ countLabel }
			/>
		);
	}

	return (
		<div className="wrap">
			<Page
				breadcrumbItems={ [
					{ label: 'Lite Site' },
					{
						label: __( 'RSS Entries', 'newspack-lite-site' ),
						count: showCount ? totalItems : undefined,
						countLabel: showCount ? countLabel( totalItems, formatCount( totalItems ) ) : undefined,
					},
				] }
				actions={
					showCount &&
					addUrl && (
						<Button variant="primary" href={ addUrl }>
							{ __( 'Add RSS Entry', 'newspack-lite-site' ) }
						</Button>
					)
				}
			>
				<div className="newspack-lite-rss-sections">{ content }</div>
			</Page>
			{ notice && (
				<div className={ `newspack-lite-snackbar-container newspack-lite-snackbar-container--${ notice.status }` }>
					<Snackbar actions={ notice.actions } onRemove={ dismissNotice } politeness={ 'error' === notice.status ? 'assertive' : 'polite' }>
						{ notice.message }
					</Snackbar>
				</div>
			) }
		</div>
	);
};
