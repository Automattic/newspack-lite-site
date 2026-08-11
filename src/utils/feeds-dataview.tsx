/**
 * DataViews configuration for the RSS Feed Import feeds table.
 */

/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import { type Feed } from '../types/rss-feed-import';
import { formatLastResult, formatUnixTimestamp } from './formatters';

/**
 * Default view configuration for the DataViews feeds table.
 */
export const DEFAULT_VIEW = {
	type: 'table' as const,
	perPage: 10,
	page: 1,
	sort: { field: 'feed_url', direction: 'asc' as const },
	fields: [ 'feed_url', 'status', 'interval_label', 'author_name', 'last_run', 'next_run' ],
	filters: [],
	search: '',
};

/**
 * Field definitions for the DataViews RSS feeds table.
 */
export const FEED_FIELDS = [
	{
		id: 'feed_url',
		label: __( 'RSS Feed URL', 'newspack-lite-site' ),
		enableSorting: true,
		enableGlobalSearch: true,
		render: ( { item }: { item: Feed } ) => <strong>{ item.feed_url }</strong>,
	},
	{
		id: 'status',
		label: __( 'Status', 'newspack-lite-site' ),
		enableSorting: true,
		render: ( { item }: { item: Feed } ) => (
			<span className={ `newspack-lite-feed-status--${ item.status }` }>
				{ 'active' === item.status ? __( 'Active', 'newspack-lite-site' ) : __( 'Paused', 'newspack-lite-site' ) }
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
					{ formatUnixTimestamp( item.last_run ) }
					{ formatLastResult( item.last_result ) }
				</>
			);
		},
	},
	{
		id: 'next_run',
		label: __( 'Next Run', 'newspack-lite-site' ),
		enableSorting: true,
		render: ( { item }: { item: Feed } ) => ( item.next_run ? formatUnixTimestamp( item.next_run ) : '—' ),
	},
];
