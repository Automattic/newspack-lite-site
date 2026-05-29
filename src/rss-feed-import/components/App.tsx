/**
 * RSS Feed Import page root component.
 */

/**
 * WordPress dependencies.
 */
import { Notice, Snackbar, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import { useFeeds } from '../hooks/useFeeds';
import { FeedForm } from './FeedForm';
import { FeedsList } from './FeedsList';

/**
 * Root component for the RSS Feed Import page.
 *
 * Loads feeds on mount and coordinates the Add Feed form and feeds table.
 */
export const App = () => {
	const {
		feeds,
		isLoading,
		isAdding,
		actionInProgress,
		notice,
		addFeed,
		feedAction,
	} = useFeeds();

	const cronDisabled =
		window.NewspackLiteSiteRssImport?.cronDisabled ?? false;

	if ( isLoading ) {
		return <Spinner />;
	}

	return (
		<div className="newspack-lite-settings-wrap">
			{ cronDisabled && (
				<Notice status="warning" isDismissible={ false }>
					{ __(
						'WP-Cron is disabled. Automatic imports require a server cron job.',
						'newspack-lite-site'
					) }
				</Notice>
			) }

			{ notice && (
				<div
					className={ `newspack-lite-snackbar-container newspack-lite-snackbar-container--${ notice.status }` }
				>
					<Snackbar>{ notice.message }</Snackbar>
				</div>
			) }

			<div className="newspack-lite-rss-sections">
				<FeedForm onAdd={ addFeed } isAdding={ isAdding } />
				<FeedsList
					feeds={ feeds }
					actionInProgress={ actionInProgress }
					onAction={ feedAction }
				/>
			</div>
		</div>
	);
};
