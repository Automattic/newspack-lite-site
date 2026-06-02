/**
 * DataForm configuration for the RSS Feed Import add-feed form.
 */

/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import { type Field } from '@wordpress/dataviews';

/**
 * Internal dependencies.
 */
import { type FeedFormData } from '../types/rss-feed-import';

const intervals = window.newspackLiteSite?.intervals ?? [];
const authors = window.newspackLiteSite?.authors ?? [];

/**
 * Default empty state for the Add Feed DataForm.
 */
export const DEFAULT_FEED: FeedFormData = {
	feed_url: '',
	interval: intervals[ 0 ]?.value ?? 'daily',
	author_id: String( authors[ 0 ]?.value ?? '' ),
};

/**
 * Field definitions for the Add Feed DataForm.
 */
export const FEED_FORM_FIELDS: Field< FeedFormData >[] = [
	{
		id: 'feed_url',
		label: __( 'Feed URL', 'newspack-lite-site' ),
		type: 'url',
	},
	{
		id: 'interval',
		label: __( 'Frequency', 'newspack-lite-site' ),
		elements: intervals,
	},
	{
		id: 'author_id',
		label: __( 'Author', 'newspack-lite-site' ),
		elements: authors,
	},
];
