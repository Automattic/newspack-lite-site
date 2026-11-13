/**
 * RSS page's Feeds tab.
 */

/**
 * WordPress dependencies.
 */
import { Button, Notice, ExternalLink } from '@wordpress/components';
import { __, _n, sprintf } from '@wordpress/i18n';
import { rss } from '@wordpress/icons';
import { EmptyState, Stack } from '@wordpress/ui';

/**
 * External dependencies.
 */
import Waiting from 'newspack-components/dist/esm/waiting';

/**
 * Internal dependencies.
 */
import { FeedsList } from '../components/FeedsList';
import { type Feed } from '../types/rss-feed-import';

const cronDisabled = window.newspackLiteSite?.cronDisabled ?? false;

export const feedsCountLabel = ( count: number, formatted: string ) =>
	/* translators: %s: number of RSS feeds. */
	sprintf( _n( '%s RSS feed', '%s RSS feeds', count, 'newspack-lite-site' ), formatted );

/**
 * The Feeds tab of the RSS page.
 *
 * Lists the scheduled feeds and filters them. The feed state and the add/edit drawer live on the RSS page.
 *
 * @param props           Component props.
 * @param props.feeds     The feeds.
 * @param props.isLoading Whether the feeds are still loading.
 * @param props.onAdd     Opens the drawer to add a feed.
 * @param props.onEdit    Opens the drawer to edit a feed.
 * @param props.onDelete  Deletes the given feeds.
 */
export const RssImportApp = ( {
	feeds,
	isLoading,
	onAdd,
	onEdit,
	onDelete,
}: {
	feeds: Feed[];
	isLoading: boolean;
	onAdd: () => void;
	onEdit: ( feed: Feed ) => void;
	onDelete: ( feedIds: string[] ) => Promise< boolean >;
} ) => {
	let content;
	if ( isLoading ) {
		content = (
			<div className="newspack-lite-loader">
				<Stack direction="column" align="center" gap="sm">
					<Waiting noMargin />
					<strong>{ __( 'Fetching…', 'newspack-lite-site' ) }</strong>
				</Stack>
			</div>
		);
	} else if ( 0 === feeds.length ) {
		content = (
			<Stack direction="column" align="center">
				<EmptyState.Root>
					<EmptyState.Icon icon={ rss } />
					<EmptyState.Title>{ __( 'No RSS feeds yet', 'newspack-lite-site' ) }</EmptyState.Title>
					<EmptyState.Description>
						{ __(
							"Import posts from another site's RSS feed on a schedule. Each new item is published on the Lite Site as an RSS entry.",
							'newspack-lite-site'
						) }
					</EmptyState.Description>
					<EmptyState.Actions>
						<Button variant="primary" onClick={ onAdd }>
							{ __( 'Add RSS Feed', 'newspack-lite-site' ) }
						</Button>
					</EmptyState.Actions>
				</EmptyState.Root>
			</Stack>
		);
	} else {
		content = <FeedsList feeds={ feeds } onEdit={ onEdit } onDelete={ onDelete } countLabel={ feedsCountLabel } />;
	}

	return (
		<div className="newspack-lite-rss-sections newspack-lite-feeds">
			{ cronDisabled && (
				<Notice status="warning" isDismissible={ false } spokenMessage="">
					{ __(
						"WP-Cron is turned off on this site, so RSS feeds import on schedule only when a server cron job runs WordPress scheduled events. If you're not sure one is set up, ask your host.",
						'newspack-lite-site'
					) }{ ' ' }
					<ExternalLink href="https://developer.wordpress.org/plugins/cron/">
						{ __( 'Learn more about WP-Cron', 'newspack-lite-site' ) }
					</ExternalLink>
				</Notice>
			) }
			{ content }
		</div>
	);
};
