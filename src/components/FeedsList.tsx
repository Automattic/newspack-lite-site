/**
 * Scheduled feeds table component.
 */

/**
 * WordPress dependencies.
 */
import { useMemo, useState } from '@wordpress/element';
import { Spinner } from '@wordpress/components';
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
import { type Feed, type FeedsListProps } from '../types/rss-feed-import';
import { DEFAULT_VIEW, FEED_FIELDS } from '../utils/feeds-dataview';
import { PauseFeedModal } from './PauseFeedModal';
import { DeleteFeedModal } from './DeleteFeedModal';

/**
 * Renders the scheduled RSS feeds table with sortable columns and row actions.
 *
 * Pause and Delete show a confirmation modal before acting.
 * Resume fires immediately.
 */
export const FeedsList = ( {
	feeds,
	actionInProgress,
	onAction,
}: FeedsListProps ) => {
	const [ view, setView ] = useState< View >( DEFAULT_VIEW );

	const actions: Action< Feed >[] = useMemo(
		() => [
			{
				id: 'pause-feed',
				label: __( 'Pause', 'newspack-lite-site' ),
				isEligible: ( item: Feed ) =>
					'active' === item.status && actionInProgress !== item.id,
				RenderModal: ( props: RenderModalProps< Feed > ) => (
					<PauseFeedModal { ...props } onAction={ onAction } />
				),
			},
			{
				id: 'resume-feed',
				label: __( 'Resume', 'newspack-lite-site' ),
				isEligible: ( item: Feed ) =>
					'paused' === item.status && actionInProgress !== item.id,
				callback: ( items: Feed[] ) => {
					onAction( items[ 0 ].id, 'resume' );
				},
			},
			{
				id: 'delete-feed',
				label: __( 'Delete', 'newspack-lite-site' ),
				isDestructive: true,
				isEligible: ( item: Feed ) => actionInProgress !== item.id,
				RenderModal: ( props: RenderModalProps< Feed > ) => (
					<DeleteFeedModal { ...props } onAction={ onAction } />
				),
			},
		],
		[ actionInProgress, onAction ]
	);

	const { data: processedData, paginationInfo } = useMemo(
		() => filterSortAndPaginate( feeds, view, FEED_FIELDS ),
		[ feeds, view ]
	);

	return (
		<div className="newspack-lite-section">
			<div className="newspack-lite-section-header">
				<h3>{ __( 'Scheduled RSS Feeds', 'newspack-lite-site' ) }</h3>
			</div>

			{ actionInProgress && <Spinner /> }

			{ feeds.length === 0 && (
				<p className="newspack-lite-empty-message">
					{ __(
						'No RSS feeds configured. Add one above.',
						'newspack-lite-site'
					) }
				</p>
			) }

			{ feeds.length > 0 && (
				<DataViews
					data={ processedData }
					fields={ FEED_FIELDS }
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
