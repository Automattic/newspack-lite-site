/**
 * RSS Feed Import page root component.
 */

/**
 * WordPress dependencies.
 */
import { Notice, Snackbar, Spinner, ExternalLink } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * External dependencies.
 */
import Page from 'newspack-components/dist/esm/page';

/**
 * Internal dependencies.
 */
import { useFeeds } from '../hooks/useFeeds';
import { FeedForm } from '../components/FeedForm';
import { FeedsList } from '../components/FeedsList';

const cronDisabled = window.newspackLiteSite?.cronDisabled ?? false;

/**
 * Root component for the RSS Feed Import page.
 *
 * Loads feeds on mount and coordinates the Add Feed form and feeds table.
 */
export const RssImportApp = () => {
	const { feeds, isLoading, isAdding, actionInProgress, notice, addFeed, feedAction } = useFeeds();

	return (
		<div className="wrap">
			<Page
				breadcrumbItems={ [ { label: __( 'Lite Site', 'newspack-lite-site' ) }, { label: __( 'RSS Feed Import', 'newspack-lite-site' ) } ] }
			>
				<div className="newspack-lite-rss-sections">
					{ cronDisabled && (
						<Notice status="warning" isDismissible={ false }>
							{ __(
								'WP-Cron is disabled on this site. RSS feed imports will not run automatically unless a server-level cron job is configured to trigger WordPress scheduled events.',
								'newspack-lite-site'
							) }{ ' ' }
							<ExternalLink href="https://developer.wordpress.org/plugins/cron/">
								{ __( 'Learn more about WordPress Cron', 'newspack-lite-site' ) }
							</ExternalLink>
						</Notice>
					) }
					{ isLoading ? (
						<Spinner />
					) : (
						<>
							<FeedForm onAdd={ addFeed } isAdding={ isAdding } />
							<FeedsList feeds={ feeds } actionInProgress={ actionInProgress } onAction={ feedAction } />
						</>
					) }
				</div>
			</Page>
			{ notice && (
				<div className={ `newspack-lite-snackbar-container newspack-lite-snackbar-container--${ notice.status }` }>
					<Snackbar>{ notice.message }</Snackbar>
				</div>
			) }
		</div>
	);
};
