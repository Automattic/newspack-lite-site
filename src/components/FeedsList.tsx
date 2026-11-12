/**
 * Scheduled feeds table component.
 */

/**
 * WordPress dependencies.
 */
import { useMemo, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { filterSortAndPaginate, type Action, type RenderModalProps, type View } from '@wordpress/dataviews';

/**
 * Internal dependencies.
 */
import { InlineFiltersDataViews } from './InlineFiltersDataViews';
import { type CountLabel } from './CountFooter';
import { DeleteFeedModal } from './DeleteFeedModal';
import { type Feed, type FeedsListProps } from '../types/rss-feed-import';
import { DEFAULT_VIEW, FEED_FIELDS } from '../utils/feeds-dataview';

/**
 * Renders the scheduled RSS feeds table with sortable columns.
 *
 * Each row has Edit and Delete buttons, the feed URL opens the editor, and Delete also works on several selected rows.
 */
export const FeedsList = ( { feeds, onEdit, onDelete, countLabel }: FeedsListProps & { countLabel?: CountLabel } ) => {
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
				modalSize: 'small',
				modalHeader: ( items: Feed[] ) =>
					1 === items.length
						? __( 'Delete RSS Feed', 'newspack-lite-site' )
						: sprintf(
								/* translators: %d: number of RSS feeds. */
								_n( 'Delete %d RSS Feed', 'Delete %d RSS Feeds', items.length, 'newspack-lite-site' ),
								items.length
							),
				isPrimary: true,
				isDestructive: true,
				supportsBulk: true,
				RenderModal: ( props: RenderModalProps< Feed > ) => (
					<DeleteFeedModal { ...props } onConfirm={ items => onDelete( items.map( item => item.id ) ) } />
				),
			},
		],
		[ onEdit, onDelete ]
	);

	const { data: processedData, paginationInfo } = useMemo( () => filterSortAndPaginate( feeds, view, FEED_FIELDS ), [ feeds, view ] );

	const autoColumns = 1 + ( view.fields ?? [] ).filter( id => 'status' !== id ).length;

	return (
		<div className="newspack-lite-feeds-table" style={ { '--newspack-lite-feeds-columns': autoColumns } as React.CSSProperties }>
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
				countLabel={ countLabel }
			/>
		</div>
	);
};
