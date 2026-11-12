/**
 * TypeScript types for the RSS Entries page.
 */

/**
 * WordPress dependencies.
 */
import { type RenderModalProps } from '@wordpress/dataviews';

/**
 * Post statuses an RSS entry can have.
 */
export type EntryStatus = 'publish' | 'draft' | 'pending' | 'future' | 'private' | 'trash';

/**
 * A single RSS entry as returned by the REST API.
 */
export interface RssEntry {
	id: number;
	title: { raw: string };
	date: string;
	status: EntryStatus;
	author: number;
	categories: number[];
	tags: number[];
	link: string;
	feed_id: string;
	feed_label: string;
	source_url: string;
	author_name: string;
	author_avatar_urls?: Record< string, string >;
	lite_url: string;
}

/**
 * Data localized to the RSS Entries page.
 */
export interface RssEntriesData {
	feeds: Array< { value: string; label: string } >;
	tags: Array< { value: string; label: string } >;
	editUrl: string;
	addUrl: string;
	importUrl: string;
	liteEnabled: boolean;
	result: { action: EntryMutation; count: number; ids: number[] } | null;
}

/**
 * A snackbar notice on the RSS Entries page.
 */
export interface EntriesNotice {
	message: string;
	status: 'success' | 'error';
	actions?: Array< { label: string; onClick: () => void } >;
}

/**
 * The mutations that can be applied to RSS entries.
 */
export type EntryMutation = 'trash' | 'restore' | 'delete';

/**
 * Props for the permanent deletion confirmation modal.
 */
export interface DeleteEntriesModalProps extends RenderModalProps< RssEntry > {
	onConfirm: ( items: RssEntry[] ) => Promise< void >;
}
