/**
 * TypeScript types for the RSS Feed Import page.
 */

/**
 * WordPress dependencies.
 */
import { type RenderModalProps } from '@wordpress/dataviews';

/**
 * The result object stored after each feed import attempt.
 */
export interface LastResult {
	imported?: number;
	failed?: number;
	up_to_date?: boolean;
	error?: string;
}

/**
 * A single RSS feed record as returned by the REST API.
 */
export interface Feed {
	id: string;
	feed_url: string;
	interval: string;
	interval_label: string;
	author_id: number;
	author_name: string;
	status: 'active' | 'paused';
	last_run: number | null;
	last_result: LastResult | null;
	next_run: number | null;
}

/**
 * The actions that can be performed on a feed.
 */
export type FeedAction = 'pause' | 'resume' | 'delete';

/**
 * A transient notice message shown to the user after an action completes.
 */
export interface Notice {
	message: string;
	status: 'success' | 'error';
}

/**
 * Props for the FeedsList component.
 */
export interface FeedsListProps {
	feeds: Feed[];
	actionInProgress: string | null;
	onAction: ( feedId: string, action: FeedAction ) => void;
}

/**
 * Props shared by the Pause and Delete feed confirmation modals.
 */
export interface FeedModalProps extends RenderModalProps< Feed > {
	onAction: ( feedId: string, action: FeedAction ) => void;
}

/**
 * Payload for adding a new RSS feed.
 */
export interface NewFeedData {
	feed_url: string;
	interval: string;
	author_id: number;
}

/**
 * Internal form state managed by the Add Feed DataForm.
 */
export interface FeedFormData {
	feed_url: string;
	interval: string;
	author_id: string;
}

/**
 * Props for the FeedForm component.
 */
export interface FeedFormProps {
	onAdd: ( data: NewFeedData ) => Promise< boolean >;
	isAdding: boolean;
}
