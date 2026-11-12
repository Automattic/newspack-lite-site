/**
 * RSS page's Feeds tab.
 */

/**
 * WordPress dependencies.
 */
import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
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
import { type RssTabProps } from '../types/rss';

const cronDisabled = window.newspackLiteSite?.cronDisabled ?? false;

const countLabel = ( count: number, formatted: string ) =>
	/* translators: %s: number of RSS feeds. */
	sprintf( _n( '%s RSS feed', '%s RSS feeds', count, 'newspack-lite-site' ), formatted );

/**
 * The Feeds tab of the RSS page.
 *
 * Lists the scheduled feeds, filters them, and opens a drawer to add or edit one. Delete is a row action.
 *
 * @param props                  Component props.
 * @param props.tabbedNavigation The RSS page tabs, when shown.
 * @param props.isActive         Whether this tab is the one shown.
 * @param props.onFeedsChange    Called with the number of feeds once they load and after each change.
 */
export const RssImportApp = ( { tabbedNavigation, isActive = true, onFeedsChange }: RssTabProps & { onFeedsChange?: ( count: number ) => void } ) => {
	const [ isDrawerOpen, setIsDrawerOpen ] = useState( false );
	const { feeds, isLoading, isSaving, saveError, notice, showNotice, addFeed, updateFeed, deleteFeeds, importFeed, isImporting, clearSaveError } =
		useFeeds( isDrawerOpen );
	const [ drawerFeedId, setDrawerFeedId ] = useState< string | null >( null );
	const lastDrawerFeed = useRef< Feed | null >( null );
	const found = drawerFeedId ? feeds.find( item => item.id === drawerFeedId ) : undefined;
	if ( found ) {
		lastDrawerFeed.current = found;
	} else if ( ! drawerFeedId ) {
		lastDrawerFeed.current = null;
	}
	const drawerFeed = drawerFeedId ? lastDrawerFeed.current : null;
	const isFeedMissing = isDrawerOpen && null !== drawerFeedId && ! isLoading && ! found;

	useEffect( () => {
		if ( isFeedMissing ) {
			setIsDrawerOpen( false );
			showNotice( __( 'This RSS feed no longer exists.', 'newspack-lite-site' ), 'error' );
		}
	}, [ isFeedMissing, showNotice ] );

	const openAdd = useCallback( () => {
		clearSaveError();
		setDrawerFeedId( null );
		setIsDrawerOpen( true );
	}, [ clearSaveError ] );

	const openEdit = useCallback(
		( feed: Feed ) => {
			clearSaveError();
			setDrawerFeedId( feed.id );
			setIsDrawerOpen( true );
		},
		[ clearSaveError ]
	);

	const closeDrawer = useCallback( () => setIsDrawerOpen( false ), [] );

	useEffect( () => {
		if ( ! isActive ) {
			setIsDrawerOpen( false );
		}
	}, [ isActive ] );

	useEffect( () => {
		if ( ! isLoading ) {
			onFeedsChange?.( feeds.length );
		}
	}, [ isLoading, feeds.length, onFeedsChange ] );

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
		content = <FeedsList feeds={ feeds } onEdit={ openEdit } onDelete={ deleteFeeds } countLabel={ countLabel } />;
	}

	return (
		<div className="wrap">
			<Page
				subTitle={ __( 'Set which RSS feeds to import and how often each one runs.', 'newspack-lite-site' ) }
				tabbedNavigation={ tabbedNavigation }
				breadcrumbItems={ [
					{ label: 'Lite Site' },
					{ label: __( 'RSS', 'newspack-lite-site' ) },
					{
						label: __( 'Feeds', 'newspack-lite-site' ),
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
				isImporting={ isImporting }
				onImport={ importFeed }
			/>
			{ notice && (
				<div className={ `newspack-lite-snackbar-container newspack-lite-snackbar-container--${ notice.status }` }>
					<Snackbar>{ notice.message }</Snackbar>
				</div>
			) }
		</div>
	);
};
