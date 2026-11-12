/**
 * RSS Feeds page root component.
 */

/**
 * WordPress dependencies.
 */
import { useCallback, useState } from '@wordpress/element';
import { Button, Notice, Snackbar, ExternalLink } from '@wordpress/components';
import { __, _n, sprintf } from '@wordpress/i18n';
import { rss } from '@wordpress/icons';
import { Stack } from '@wordpress/ui';

/**
 * External dependencies.
 */
import Page from 'newspack-components/dist/esm/page';
import { EmptyState } from 'newspack-components/dist/esm/empty-state';
import Waiting from 'newspack-components/dist/esm/waiting';

/**
 * Internal dependencies.
 */
import { useFeeds } from '../hooks/useFeeds';
import { FeedDrawer } from '../components/FeedDrawer';
import { FeedsList } from '../components/FeedsList';
import { formatCount } from '../utils/format-count';
import { type Feed } from '../types/rss-feed-import';

const cronDisabled = window.newspackLiteSite?.cronDisabled ?? false;

const countLabel = ( count: number, formatted: string ) =>
	/* translators: %s: number of RSS feeds. */
	sprintf( _n( '%s RSS feed', '%s RSS feeds', count, 'newspack-lite-site' ), formatted );

/**
 * Root component for the RSS Feeds page.
 *
 * Lists the scheduled feeds, filters them, and opens a drawer to add or edit one. Delete is a row action.
 */
export const RssImportApp = () => {
	const [ isDrawerOpen, setIsDrawerOpen ] = useState( false );
	const { feeds, isLoading, isSaving, saveError, notice, addFeed, updateFeed, deleteFeed, clearSaveError } = useFeeds( isDrawerOpen );
	const [ drawerFeed, setDrawerFeed ] = useState< Feed | null >( null );

	const openAdd = useCallback( () => {
		clearSaveError();
		setDrawerFeed( null );
		setIsDrawerOpen( true );
	}, [ clearSaveError ] );

	const openEdit = useCallback(
		( feed: Feed ) => {
			clearSaveError();
			setDrawerFeed( feed );
			setIsDrawerOpen( true );
		},
		[ clearSaveError ]
	);

	const closeDrawer = useCallback( () => setIsDrawerOpen( false ), [] );

	const showCount = ! isLoading && feeds.length > 0;

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
			<EmptyState.Root>
				<EmptyState.Header
					icon={ rss }
					title={ __( 'No RSS feeds yet', 'newspack-lite-site' ) }
					description={ __(
						"Import posts from another site's RSS feed on a schedule. Each new item is published on the Lite Site as an RSS entry.",
						'newspack-lite-site'
					) }
				/>
				<EmptyState.Actions>
					<Button variant="primary" onClick={ openAdd }>
						{ __( 'Add RSS Feed', 'newspack-lite-site' ) }
					</Button>
				</EmptyState.Actions>
			</EmptyState.Root>
		);
	} else {
		content = <FeedsList feeds={ feeds } onEdit={ openEdit } onDelete={ deleteFeed } />;
	}

	return (
		<div className="wrap">
			<Page
				subTitle={ __( 'Set which RSS feeds to import and how often each one runs.', 'newspack-lite-site' ) }
				breadcrumbItems={ [
					{ label: 'Lite Site' },
					{
						label: __( 'RSS Feeds', 'newspack-lite-site' ),
						count: showCount ? feeds.length : undefined,
						countLabel: showCount ? countLabel( feeds.length, formatCount( feeds.length ) ) : undefined,
					},
				] }
				actions={
					showCount && (
						<Button variant="primary" onClick={ openAdd }>
							{ __( 'Add RSS Feed', 'newspack-lite-site' ) }
						</Button>
					)
				}
			>
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
			</Page>
			<FeedDrawer
				isOpen={ isDrawerOpen }
				feed={ drawerFeed }
				isSaving={ isSaving }
				error={ saveError }
				onClose={ closeDrawer }
				onAdd={ addFeed }
				onUpdate={ updateFeed }
				onClearError={ clearSaveError }
			/>
			{ notice && (
				<div className={ `newspack-lite-snackbar-container newspack-lite-snackbar-container--${ notice.status }` }>
					<Snackbar>{ notice.message }</Snackbar>
				</div>
			) }
		</div>
	);
};
