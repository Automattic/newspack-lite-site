/**
 * Scheduled feeds table component.
 */

/**
 * WordPress dependencies.
 */
import { useMemo, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { filterSortAndPaginate, type Action, type RenderModalProps, type View } from '@wordpress/dataviews';

/**
 * Internal dependencies.
 */
import { InlineFiltersDataViews } from './InlineFiltersDataViews';
import { DeleteFeedModal } from './DeleteFeedModal';
import { type Feed, type FeedsListProps } from '../types/rss-feed-import';
import { DEFAULT_VIEW, FEED_FIELDS } from '../utils/feeds-dataview';

/**
 * Renders the scheduled RSS feeds table with sortable columns.
 *
 * Each row has Edit and Delete buttons, and the feed URL opens the editor.
 */
export const FeedsList = ( { feeds, onEdit, onDelete }: FeedsListProps ) => {
	const [ view, setView ] = useState< View >( DEFAULT_VIEW );

	const actions: Action< Feed >[] = useMemo(
		() => [
			{
				id: 'edit-feed',
				label: __( 'Edit', 'newspack-lite-site' ),
				isPrimary: true,
				callback: ( items: Feed[] ) => onEdit( items[ 0 ] ),
			},
			{
				id: 'delete-feed',
				label: __( 'Delete', 'newspack-lite-site' ),
				isPrimary: true,
				isDestructive: true,
				RenderModal: ( props: RenderModalProps< Feed > ) => <DeleteFeedModal { ...props } onConfirm={ items => onDelete( items[ 0 ].id ) } />,
			},
		],
		[ onEdit, onDelete ]
	);

	const { data: processedData, paginationInfo } = useMemo( () => filterSortAndPaginate( feeds, view, FEED_FIELDS ), [ feeds, view ] );

	return (
		<InlineFiltersDataViews
			data={ processedData }
			fields={ FEED_FIELDS }
			view={ view }
			onChangeView={ setView }
			getItemId={ ( item: Feed ) => item.id }
			paginationInfo={ paginationInfo }
			actions={ actions }
			onClickItem={ onEdit }
			isItemClickable={ () => true }
			defaultLayouts={ { table: {} } }
		/>
	);
};
