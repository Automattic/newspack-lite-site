/**
 * DataForm configuration for the RSS Feeds feed drawer.
 */

/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import {
	TextareaControl,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalToggleGroupControl as ToggleGroupControl,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalToggleGroupControlOption as ToggleGroupControlOption,
} from '@wordpress/components';
import { type DataFormControlProps, type Field } from '@wordpress/dataviews';

/**
 * Internal dependencies.
 */
import { type FeedFormData } from '../types/rss-feed-import';

const intervals = window.newspackLiteSite?.intervals ?? [];
const authors = window.newspackLiteSite?.authors ?? [];

/**
 * Default empty state for the Add RSS Feed drawer.
 */
export const DEFAULT_FEED: FeedFormData = {
	feed_url: '',
	interval: intervals[ 0 ]?.value ?? 'daily',
	author_id: String( authors[ 0 ]?.value ?? '' ),
	status: 'active',
};

const authorValues = authors.map( author => String( author.value ) );

/**
 * Whether an author ID belongs to one of the authors who can be assigned to a feed.
 *
 * @param authorId The author ID as a string.
 */
export const isKnownAuthor = ( authorId: string ) => authorValues.includes( authorId );

const AUTHOR_PLACEHOLDER = { value: '', label: __( 'Choose an author', 'newspack-lite-site' ), disabled: true };

const FeedStatusEdit = ( { data, field, onChange }: DataFormControlProps< FeedFormData > ) => (
	<ToggleGroupControl
		label={ field.label }
		help={ field.description }
		value={ data.status }
		onChange={ value => onChange( { status: value as FeedFormData[ 'status' ] } ) }
		isBlock
		__nextHasNoMarginBottom
		__next40pxDefaultSize
	>
		<ToggleGroupControlOption value="active" label={ __( 'Active', 'newspack-lite-site' ) } />
		<ToggleGroupControlOption value="paused" label={ __( 'Inactive', 'newspack-lite-site' ) } />
	</ToggleGroupControl>
);

/**
 * Field definitions for the feed drawer DataForm.
 *
 * In edit mode the URL is the feed's identity, so its textarea is disabled.
 *
 * @param isEdit      Whether an existing feed is being edited.
 * @param needsAuthor Whether the feed's author is no longer available, so the author field starts on a placeholder.
 */
export function getFeedFormFields( isEdit: boolean, needsAuthor = false ): Field< FeedFormData >[] {
	const FeedUrlEdit = ( { data, field, onChange }: DataFormControlProps< FeedFormData > ) => (
		<TextareaControl
			label={ field.label }
			value={ data.feed_url }
			onChange={ value => onChange( { feed_url: value.replace( /[\r\n]+/g, '' ) } ) }
			rows={ 3 }
			disabled={ isEdit }
			className="newspack-lite-code-textarea"
			__nextHasNoMarginBottom
		/>
	);

	return [
		{
			id: 'feed_url',
			label: __( 'RSS Feed URL', 'newspack-lite-site' ),
			Edit: FeedUrlEdit,
		},
		{
			id: 'interval',
			label: __( 'Frequency', 'newspack-lite-site' ),
			description: __( 'How often new items are imported from this RSS feed.', 'newspack-lite-site' ),
			elements: intervals,
		},
		{
			id: 'author_id',
			label: __( 'Author', 'newspack-lite-site' ),
			description: __( 'Imported entries are published under this author.', 'newspack-lite-site' ),
			elements: needsAuthor ? [ AUTHOR_PLACEHOLDER, ...authors ] : authors,
			...( needsAuthor ? { isValid: { required: true } } : {} ),
		},
		{
			id: 'status',
			label: __( 'Status', 'newspack-lite-site' ),
			description: __(
				"Inactive feeds stop importing until you set them back to Active. An import that's already running will finish.",
				'newspack-lite-site'
			),
			Edit: FeedStatusEdit,
		},
	];
}
