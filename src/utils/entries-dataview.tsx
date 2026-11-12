/**
 * DataViews configuration for the RSS Entries table.
 */

/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import { dateI18n, getDate, getSettings } from '@wordpress/date';
import { decodeEntities } from '@wordpress/html-entities';
import { type Field, type View } from '@wordpress/dataviews';

/**
 * External dependencies.
 */
import StatusIndicator from 'newspack-components/dist/esm/status-indicator';

/**
 * Internal dependencies.
 */
import { type EntryStatus, type RssEntry } from '../types/rss-entries';
import { getCategoryLabel } from './formatters';
import { UserRow } from '../components/UserRow';

/**
 * Default view configuration for the RSS Entries table.
 */
export const DEFAULT_VIEW: View = {
	type: 'table',
	perPage: 20,
	page: 1,
	sort: { field: 'date', direction: 'desc' },
	titleField: 'title',
	descriptionField: 'feed',
	fields: [ 'status', 'author', 'categories', 'date' ],
	filters: [],
	search: '',
};

/**
 * Labels and indicator statuses for each post status.
 */
export function getStatusOptions(): Array< { value: EntryStatus; label: string; indicator: string } > {
	return [
		{ value: 'publish', label: __( 'Published', 'newspack-lite-site' ), indicator: 'active' },
		{ value: 'draft', label: __( 'Draft', 'newspack-lite-site' ), indicator: 'draft' },
		{ value: 'pending', label: __( 'Pending', 'newspack-lite-site' ), indicator: 'pending' },
		{ value: 'future', label: __( 'Scheduled', 'newspack-lite-site' ), indicator: 'scheduled' },
		{ value: 'private', label: __( 'Private', 'newspack-lite-site' ), indicator: 'private' },
		{ value: 'trash', label: __( 'Trash', 'newspack-lite-site' ), indicator: 'trash' },
	];
}

/**
 * Build the field definitions for the RSS Entries table.
 *
 * @param editUrl URL of the admin post editor screen.
 */
export function getEntryFields( editUrl: string ): Field< RssEntry >[] {
	const data = window.newspackLiteSite;
	const feeds = data?.rssEntries?.feeds ?? [];
	const tags = data?.rssEntries?.tags ?? [];
	const authors = data?.authors ?? [];
	const categories = data?.categories ?? [];
	const statuses = getStatusOptions();

	return [
		{
			id: 'title',
			label: __( 'Title', 'newspack-lite-site' ),
			enableSorting: true,
			enableGlobalSearch: true,
			enableHiding: false,
			getValue: ( { item } ) => decodeEntities( item.title?.raw ?? '' ),
			render: ( { item } ) => {
				const title = decodeEntities( item.title?.raw ?? '' ) || __( '(no title)', 'newspack-lite-site' );
				if ( 'trash' === item.status ) {
					return <strong>{ title }</strong>;
				}
				return (
					<strong>
						<a href={ `${ editUrl }?post=${ item.id }&action=edit` }>{ title }</a>
					</strong>
				);
			},
		},
		{
			id: 'feed',
			label: __( 'Feed', 'newspack-lite-site' ),
			enableSorting: false,
			elements: feeds,
			filterBy: { operators: [ 'is' ], isPrimary: true },
			getValue: ( { item } ) => item.feed_id,
			render: ( { item } ) => item.feed_label || '—',
		},
		{
			id: 'author',
			label: __( 'Author', 'newspack-lite-site' ),
			enableSorting: true,
			elements: authors,
			filterBy: { operators: [ 'isAny' ], isPrimary: false },
			getValue: ( { item } ) => String( item.author ),
			render: ( { item } ) => ( item.author_name ? <UserRow label={ item.author_name } avatarUrls={ item.author_avatar_urls } /> : '—' ),
		},
		{
			id: 'categories',
			label: __( 'Categories', 'newspack-lite-site' ),
			enableSorting: false,
			elements: categories.map( cat => ( { value: String( cat.id ), label: getCategoryLabel( cat ) } ) ),
			filterBy: { operators: [ 'isAny' ], isPrimary: false },
			getValue: ( { item } ) => ( item.categories ?? [] ).map( String ),
			render: ( { item } ) => {
				const names = ( item.categories ?? [] ).map( id => categories.find( cat => cat.id === id )?.name ).filter( Boolean );
				return names.length ? names.join( ', ' ) : '—';
			},
		},
		{
			id: 'tags',
			label: __( 'Tags', 'newspack-lite-site' ),
			enableSorting: false,
			elements: tags,
			filterBy: { operators: [ 'isAny' ], isPrimary: false },
			getValue: ( { item } ) => ( item.tags ?? [] ).map( String ),
			render: ( { item } ) => {
				const names = ( item.tags ?? [] ).map( id => tags.find( tag => tag.value === String( id ) )?.label ).filter( Boolean );
				return names.length ? names.join( ', ' ) : '—';
			},
		},
		{
			id: 'status',
			label: __( 'Status', 'newspack-lite-site' ),
			enableSorting: false,
			elements: statuses.map( ( { value, label } ) => ( { value, label } ) ),
			filterBy: { operators: [ 'isAny' ], isPrimary: true },
			getValue: ( { item } ) => item.status,
			render: ( { item } ) => {
				const match = statuses.find( status => status.value === item.status );
				return <StatusIndicator status={ match?.indicator ?? 'draft' }>{ match?.label ?? item.status }</StatusIndicator>;
			},
		},
		{
			id: 'date',
			label: __( 'Date', 'newspack-lite-site' ),
			enableSorting: true,
			getValue: ( { item } ) => item.date,
			render: ( { item } ) => {
				const { formats } = getSettings();
				return <span className="newspack-dataviews__date">{ dateI18n( `${ formats.date } ${ formats.time }`, getDate( item.date ) ) }</span>;
			},
		},
	];
}
