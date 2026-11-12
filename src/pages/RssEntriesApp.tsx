/**
 * RSS page's Entries tab.
 */

/**
 * WordPress dependencies.
 */
import { useCallback, useEffect, useMemo, useRef, useState } from '@wordpress/element';
import { Button, Notice, Snackbar } from '@wordpress/components';
import { __, _n, sprintf } from '@wordpress/i18n';
import { type Action, type RenderModalProps, type View } from '@wordpress/dataviews';
import { Link, Stack, Text } from '@wordpress/ui';

/**
 * External dependencies.
 */
import { type MouseEvent } from 'react';
import Waiting from 'newspack-components/dist/esm/waiting';

/**
 * Internal dependencies.
 */
import { getMutationMessage, useEntries } from '../hooks/useEntries';
import { InlineFiltersDataViews } from '../components/InlineFiltersDataViews';
import { DeleteEntriesModal } from '../components/DeleteEntriesModal';
import { DEFAULT_VIEW, getEntryFields } from '../utils/entries-dataview';
import { formatCount } from '../utils/format-count';
import { type RssEntry } from '../types/rss-entries';
import { type RssTabProps } from '../types/rss';

const countLabel = ( count: number, formatted: string ) =>
	/* translators: %s: number of RSS entries. */
	sprintf( _n( '%s RSS entry', '%s RSS entries', count, 'newspack-lite-site' ), formatted );

const rssEntries = window.newspackLiteSite?.rssEntries;

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
 * The Entries tab of the RSS page.
 *
 * Lists imported RSS entries in a server-paginated table with filters and bulk actions.
 * The list loads again whenever the tab comes back into view, since imports run in the background.
 *
 * @param props                Component props.
 * @param props.isActive       Whether this tab is the one shown.
 * @param props.canManageFeeds Whether the current user can open the Feeds tab.
 * @param props.onHeaderChange Called with the page header parts this tab sets.
 * @param props.onGoToFeeds    Called when the link to the Feeds tab is followed.
 */
export const RssEntriesApp = ( {
	isActive = true,
	canManageFeeds = false,
	onHeaderChange,
	onGoToFeeds,
}: RssTabProps & { canManageFeeds?: boolean; onGoToFeeds?: () => void } ) => {
	const [ view, setView ] = useState< View >( getInitialView );
	const setPage = useCallback( ( page: number ) => setView( current => ( { ...current, page } ) ), [] );
	const { entries, totalItems, totalPages, isLoading, isFetching, isEmpty, loadError, notice, showNotice, dismissNotice, mutate, reload } =
		useEntries( view, setPage );
	const forwardedResult = useRef( rssEntries?.result ?? null );
	const wasActive = useRef( isActive );

	useEffect( () => {
		if ( isActive && ! wasActive.current ) {
			reload();
		}
		wasActive.current = isActive;
	}, [ isActive, reload ] );

	useEffect( () => {
		const result = forwardedResult.current;
		forwardedResult.current = null;
		if ( ! result ) {
			return;
		}
		const canUndo = 'trash' === result.action && result.ids.length > 0;
		showNotice(
			getMutationMessage( result.action, result.count ),
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
	const addUrl = rssEntries?.addUrl ?? '';
	const showCount = ! isLoading && ! isEmpty && ! loadError;
	const fields = useMemo( () => getEntryFields( editUrl ), [ editUrl ] );

	const headerActions = useMemo(
		() =>
			showCount && addUrl ? (
				<Button variant="primary" href={ addUrl }>
					{ __( 'Add RSS Entry', 'newspack-lite-site' ) }
				</Button>
			) : undefined,
		[ showCount, addUrl ]
	);

	useEffect( () => {
		onHeaderChange?.( {
			count: showCount ? totalItems : undefined,
			countLabel: showCount ? countLabel( totalItems, formatCount( totalItems ) ) : undefined,
			actions: headerActions,
		} );
	}, [ showCount, totalItems, headerActions, onHeaderChange ] );

	const goToFeeds = useCallback(
		( event: MouseEvent< HTMLAnchorElement > ) => {
			if ( ! event.metaKey && ! event.ctrlKey && ! event.shiftKey && ! event.altKey ) {
				onGoToFeeds?.();
			}
		},
		[ onGoToFeeds ]
	);

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
			<Text render={ <p /> } variant="body-md" className="newspack-lite-entries-message">
				{ __( 'Entries appear here after the first import.', 'newspack-lite-site' ) }
				{ canManageFeeds && (
					<>
						{ ' ' }
						<Link href="#/feeds" onClick={ goToFeeds }>
							{ __( 'Go to Feeds', 'newspack-lite-site' ) }
						</Link>
					</>
				) }
			</Text>
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
		<>
			<div className="newspack-lite-rss-sections">{ content }</div>
			{ notice && (
				<div className={ `newspack-lite-snackbar-container newspack-lite-snackbar-container--${ notice.status }` }>
					<Snackbar
						key={ notice.key }
						actions={ notice.actions }
						onRemove={ dismissNotice }
						politeness={ 'error' === notice.status ? 'assertive' : 'polite' }
					>
						{ notice.message }
					</Snackbar>
				</div>
			) }
		</>
	);
};
