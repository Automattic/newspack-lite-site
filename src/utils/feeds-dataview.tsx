/**
 * DataViews configuration for the RSS Feeds feeds table.
 */

/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import { Badge, Stack } from '@wordpress/ui';
import { type Field } from '@wordpress/dataviews';

/**
 * External dependencies.
 */
import TooltipBadge from 'newspack-components/dist/esm/tooltip-badge';

/**
 * Internal dependencies.
 */
import { type Feed } from '../types/rss-feed-import';
import { formatLastResult, formatUnixTimestamp } from './formatters';
import { UserRow } from '../components/UserRow';

const intervals = window.newspackLiteSite?.intervals ?? [];
const authors = window.newspackLiteSite?.authors ?? [];
const authorLabels = new Map( authors.map( author => [ String( author.value ), author.label ] ) );
const authorLabel = ( authorId: string ) => authorLabels.get( authorId ) ?? authorId;

type DisplayStatus = 'active' | 'inactive' | 'error';

const getDisplayStatus = ( feed: Feed ): DisplayStatus => {
	if ( 'active' !== feed.status ) {
		return 'inactive';
	}
	return feed.last_result?.error ? 'error' : 'active';
};

/**
 * Default view configuration for the DataViews feeds table.
 */
export const DEFAULT_VIEW = {
	type: 'table' as const,
	perPage: 10,
	page: 1,
	sort: { field: 'feed_url', direction: 'asc' as const },
	titleField: 'feed_url',
	fields: [ 'status', 'interval', 'author_id', 'last_run', 'next_run' ],
	filters: [],
	search: '',
};

/**
 * Field definitions for the DataViews RSS feeds table.
 */
export const FEED_FIELDS: Field< Feed >[] = [
	{
		id: 'feed_url',
		label: __( 'RSS Feed', 'newspack-lite-site' ),
		enableSorting: true,
		enableGlobalSearch: true,
		render: ( { item } ) => (
			<strong className="newspack-lite-feed-url" title={ item.feed_url }>
				{ item.feed_url }
			</strong>
		),
	},
	{
		id: 'status',
		label: __( 'Status', 'newspack-lite-site' ),
		enableSorting: true,
		elements: [
			{ value: 'active', label: __( 'Active', 'newspack-lite-site' ) },
			{ value: 'inactive', label: __( 'Inactive', 'newspack-lite-site' ) },
			{ value: 'error', label: __( 'Error', 'newspack-lite-site' ) },
		],
		filterBy: { operators: [ 'isAny' ], isPrimary: true },
		getValue: ( { item } ) => getDisplayStatus( item ),
		render: ( { item } ) => {
			const status = getDisplayStatus( item );
			if ( 'error' === status ) {
				return (
					<span className="newspack-status-badge">
						<TooltipBadge
							label={ __( 'Error', 'newspack-lite-site' ) }
							intent="high"
							tooltip={
								<>
									{ item.last_result?.error }
									<br />
									{ __( 'Edit the feed for options.', 'newspack-lite-site' ) }
								</>
							}
						/>
					</span>
				);
			}
			return 'active' === status ? (
				<Badge className="newspack-status-badge" intent="stable">
					{ __( 'Active', 'newspack-lite-site' ) }
				</Badge>
			) : (
				<Badge className="newspack-status-badge" intent="draft">
					{ __( 'Inactive', 'newspack-lite-site' ) }
				</Badge>
			);
		},
	},
	{
		id: 'interval',
		label: __( 'Frequency', 'newspack-lite-site' ),
		enableSorting: true,
		elements: intervals,
		filterBy: { operators: [ 'isAny' ], isPrimary: true },
		getValue: ( { item } ) => item.interval,
		render: ( { item } ) => item.interval_label,
	},
	{
		id: 'author_id',
		label: __( 'Author', 'newspack-lite-site' ),
		enableSorting: true,
		elements: authors,
		filterBy: { operators: [ 'isAny' ], isPrimary: true },
		getValue: ( { item } ) => String( item.author_id ),
		sort: ( a: string, b: string, direction ) => ( 'asc' === direction ? 1 : -1 ) * authorLabel( a ).localeCompare( authorLabel( b ) ),
		render: ( { item } ) => ( item.author_name ? <UserRow label={ item.author_name } avatarUrls={ item.author_avatar_urls } /> : '—' ),
	},
	{
		id: 'author_name',
		label: __( 'Author Name', 'newspack-lite-site' ),
		enableSorting: false,
		enableHiding: false,
		enableGlobalSearch: true,
		getValue: ( { item } ) => item.author_name,
	},
	{
		id: 'last_run',
		label: __( 'Last Run', 'newspack-lite-site' ),
		enableSorting: true,
		render: ( { item } ) => {
			if ( ! item.last_run ) {
				return __( 'Never', 'newspack-lite-site' );
			}
			const result = item.last_result;
			return (
				<Stack render={ <span /> } direction="column" gap="xs">
					<span>{ formatUnixTimestamp( item.last_run ) }</span>
					{ result && ! result.error && <span className="newspack-lite-feed-result">{ formatLastResult( result ) }</span> }
				</Stack>
			);
		},
	},
	{
		id: 'next_run',
		label: __( 'Next Run', 'newspack-lite-site' ),
		enableSorting: true,
		render: ( { item } ) => ( item.next_run ? formatUnixTimestamp( item.next_run ) : '—' ),
	},
];
