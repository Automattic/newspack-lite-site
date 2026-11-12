/**
 * TypeScript types for the RSS Feeds page.
 */

/**
 * The result object stored after each RSS feed import attempt.
 */
export interface LastResult {
	imported?: number;
	failed?: number;
	up_to_date?: boolean;
	note?: string;
	error?: string;
	detail?: string;
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
	author_avatar_urls?: Record< string, string >;
	status: 'active' | 'paused';
	last_run: number | null;
	last_result: LastResult | null;
	next_run: number | null;
}

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
	onEdit: ( feed: Feed ) => void;
	onDelete: ( feedIds: string[] ) => Promise< boolean >;
}

/**
 * Payload for adding a new RSS feed.
 */
export interface NewFeedData {
	feed_url: string;
	interval: string;
	author_id: number;
	status: 'active' | 'paused';
}

/**
 * The fields of an RSS feed that can be changed after it is added.
 */
export interface FeedChanges {
	interval?: string;
	author_id?: number;
	status?: 'active' | 'paused';
}

/**
 * Internal form state managed by the feed drawer DataForm.
 */
export interface FeedFormData {
	feed_url: string;
	interval: string;
	author_id: string;
	status: 'active' | 'paused';
}

/**
 * Props for the FeedDrawer component.
 */
export interface FeedDrawerProps {
	isOpen: boolean;
	feed: Feed | null;
	isSaving: boolean;
	error: string | null;
	onClose: () => void;
	onAdd: ( data: NewFeedData ) => Promise< boolean >;
	onUpdate: ( feedId: string, changes: FeedChanges ) => Promise< boolean >;
	onClearError: () => void;
	isImporting: boolean;
	onImport: ( feedId: string ) => Promise< boolean >;
}
