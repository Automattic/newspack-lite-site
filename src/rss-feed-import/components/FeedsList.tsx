/**
 * Scheduled feeds table component.
 */

/**
 * WordPress dependencies.
 */
import { useState } from '@wordpress/element';
import { Button, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import {
	DataViews,
	filterSortAndPaginate,
	type Action,
	type RenderModalProps,
	type View,
} from '@wordpress/dataviews';

/**
 * Internal dependencies.
 */
import { type Feed, type FeedAction, type LastResult } from '../hooks/useFeeds';

/**
 * Format a last_result object into a human-readable summary string.
 */
function formatLastResult( result: LastResult | null ): string {
	if ( ! result ) {
		return '';
	}
	if ( result.error ) {
		return ` — ${ __( 'Error', 'newspack-lite-site' ) }: ${ result.error }`;
	}
	const imported = result.imported ?? 0;
	const failed = result.failed ?? 0;
	const upToDate = result.up_to_date ?? false;

	if ( upToDate && 0 === imported ) {
		return ` — ${ __( 'Up to date', 'newspack-lite-site' ) }`;
	}
	if ( upToDate ) {
		/* translators: %d: number of posts imported */
		return ` — ${ imported } ${ __(
			'imported, up to date',
			'newspack-lite-site'
		) }`;
	}
	if ( failed > 0 ) {
		/* translators: 1: imported count, 2: failed count */
		return ` — ${ imported } ${ __(
			'imported',
			'newspack-lite-site'
		) }, ${ failed } ${ __( 'failed', 'newspack-lite-site' ) }`;
	}
	/* translators: %d: number of posts imported */
	return ` — ${ imported } ${ __( 'imported', 'newspack-lite-site' ) }`;
}

const DEFAULT_VIEW = {
	type: 'table' as const,
	perPage: 10,
	page: 1,
	sort: { field: 'feed_url', direction: 'asc' as const },
	fields: [
		'feed_url',
		'status',
		'interval_label',
		'author_name',
		'last_run',
		'next_run',
	],
	filters: [],
	search: '',
};

interface Props {
	feeds: Feed[];
	actionInProgress: string | null;
	onAction: ( feedId: string, action: FeedAction ) => void;
}

/**
 * Renders the scheduled feeds table with sortable columns and row actions.
 *
 * Pause and Delete show a confirmation modal before acting. Resume fires immediately.
 */
export const FeedsList = ( { feeds, actionInProgress, onAction }: Props ) => {
	const [ view, setView ] = useState< View >( DEFAULT_VIEW );

	const fields = [
		{
			id: 'feed_url',
			label: __( 'Feed URL', 'newspack-lite-site' ),
			enableSorting: true,
			enableGlobalSearch: true,
			render: ( { item }: { item: Feed } ) => (
				<strong>{ item.feed_url }</strong>
			),
		},
		{
			id: 'status',
			label: __( 'Status', 'newspack-lite-site' ),
			enableSorting: true,
			render: ( { item }: { item: Feed } ) => (
				<span
					className={ `newspack-lite-feed-status--${ item.status }` }
				>
					{ 'active' === item.status
						? __( 'Active', 'newspack-lite-site' )
						: __( 'Paused', 'newspack-lite-site' ) }
				</span>
			),
		},
		{
			id: 'interval_label',
			label: __( 'Frequency', 'newspack-lite-site' ),
			enableSorting: true,
			render: ( { item }: { item: Feed } ) => item.interval_label,
		},
		{
			id: 'author_name',
			label: __( 'Author', 'newspack-lite-site' ),
			enableSorting: true,
			enableGlobalSearch: true,
			render: ( { item }: { item: Feed } ) => item.author_name || '—',
		},
		{
			id: 'last_run',
			label: __( 'Last Run', 'newspack-lite-site' ),
			enableSorting: true,
			render: ( { item }: { item: Feed } ) => {
				if ( ! item.last_run ) {
					return <em>{ __( 'Never', 'newspack-lite-site' ) }</em>;
				}
				return (
					<>
						{ new Date( item.last_run * 1000 ).toLocaleString() }
						{ formatLastResult( item.last_result ) }
					</>
				);
			},
		},
		{
			id: 'next_run',
			label: __( 'Next Run', 'newspack-lite-site' ),
			enableSorting: true,
			render: ( { item }: { item: Feed } ) =>
				item.next_run
					? new Date( item.next_run * 1000 ).toLocaleString()
					: '—',
		},
	];

	const actions: Action< Feed >[] = [
		{
			id: 'pause-feed',
			label: __( 'Pause', 'newspack-lite-site' ),
			isPrimary: false,
			isEligible: ( item: Feed ) =>
				'active' === item.status && actionInProgress !== item.id,
			RenderModal: ( {
				items,
				closeModal,
				onActionPerformed,
			}: RenderModalProps< Feed > ) => {
				const item = items[ 0 ];
				return (
					<>
						<p>
							{ __(
								'Pausing will prevent future imports. Any import currently in progress will finish.',
								'newspack-lite-site'
							) }
						</p>
						<p>
							<strong>{ item.feed_url }</strong>
						</p>
						<div className="newspack-lite-modal-actions">
							<Button
								variant="secondary"
								onClick={ () => closeModal?.() }
							>
								{ __( 'Cancel', 'newspack-lite-site' ) }
							</Button>
							<Button
								variant="primary"
								onClick={ () => {
									onAction( item.id, 'pause' );
									onActionPerformed?.( items );
									closeModal?.();
								} }
							>
								{ __( 'Pause', 'newspack-lite-site' ) }
							</Button>
						</div>
					</>
				);
			},
		},
		{
			id: 'resume-feed',
			label: __( 'Resume', 'newspack-lite-site' ),
			isPrimary: false,
			isEligible: ( item: Feed ) =>
				'paused' === item.status && actionInProgress !== item.id,
			callback: ( items: Feed[] ) => {
				onAction( items[ 0 ].id, 'resume' );
			},
		},
		{
			id: 'delete-feed',
			label: __( 'Delete', 'newspack-lite-site' ),
			isPrimary: false,
			isDestructive: true,
			isEligible: ( item: Feed ) => actionInProgress !== item.id,
			RenderModal: ( {
				items,
				closeModal,
				onActionPerformed,
			}: RenderModalProps< Feed > ) => {
				const item = items[ 0 ];
				return (
					<>
						<p>
							{ __(
								'Are you sure you want to delete this feed? This action cannot be undone.',
								'newspack-lite-site'
							) }
						</p>
						<p>
							<strong>{ item.feed_url }</strong>
						</p>
						<div className="newspack-lite-modal-actions">
							<Button
								variant="secondary"
								onClick={ () => closeModal?.() }
							>
								{ __( 'Cancel', 'newspack-lite-site' ) }
							</Button>
							<Button
								variant="primary"
								isDestructive
								onClick={ () => {
									onAction( item.id, 'delete' );
									onActionPerformed?.( items );
									closeModal?.();
								} }
							>
								{ __( 'Delete', 'newspack-lite-site' ) }
							</Button>
						</div>
					</>
				);
			},
		},
	];

	const { data: processedData, paginationInfo } = filterSortAndPaginate(
		feeds,
		view,
		fields
	);

	return (
		<div className="newspack-lite-section">
			<div className="newspack-lite-section-header">
				<h3>{ __( 'Scheduled Feeds', 'newspack-lite-site' ) }</h3>
			</div>

			{ actionInProgress && <Spinner /> }

			{ feeds.length === 0 && (
				<p className="newspack-lite-empty-message">
					{ __(
						'No feeds configured. Add one above.',
						'newspack-lite-site'
					) }
				</p>
			) }

			{ feeds.length > 0 && (
				<DataViews
					data={ processedData }
					fields={ fields }
					view={ view }
					onChangeView={ setView }
					getItemId={ ( item: Feed ) => item.id }
					paginationInfo={ paginationInfo }
					actions={ actions }
					defaultLayouts={ { table: {} } }
				/>
			) }
		</div>
	);
};
