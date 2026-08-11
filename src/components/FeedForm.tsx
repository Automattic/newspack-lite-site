/**
 * Add Feed form component.
 */

/**
 * WordPress dependencies.
 */
import { useState } from '@wordpress/element';
import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { DataForm } from '@wordpress/dataviews';

/**
 * Internal dependencies.
 */
import { type FeedFormProps } from '../types/rss-feed-import';
import { DEFAULT_FEED, FEED_FORM_FIELDS } from '../utils/feeds-dataform';

/**
 * Form for adding a new RSS feed.
 *
 * Accepts a RSS feed URL, import frequency, and author, then calls onAdd on submit.
 * Resets the form after a successful submission.
 */
export const FeedForm = ( { onAdd, isAdding }: FeedFormProps ) => {
	const [ feedData, setFeedData ] = useState( DEFAULT_FEED );

	const handleSubmit = async ( e: React.FormEvent ) => {
		e.preventDefault();
		const success = await onAdd( {
			feed_url: feedData.feed_url,
			interval: feedData.interval,
			author_id: parseInt( feedData.author_id, 10 ),
		} );
		if ( success ) {
			setFeedData( DEFAULT_FEED );
		}
	};

	return (
		<div className="newspack-lite-section">
			<div className="newspack-lite-section-header">
				<h3>{ __( 'Add RSS Feed', 'newspack-lite-site' ) }</h3>
				<p>{ __( 'Schedule an RSS feed to be imported on a recurring basis.', 'newspack-lite-site' ) }</p>
			</div>

			<form onSubmit={ handleSubmit }>
				<div className="newspack-lite-section-fields">
					<DataForm
						data={ feedData }
						fields={ FEED_FORM_FIELDS }
						form={ {
							layout: { type: 'regular' },
							fields: [ 'feed_url', 'interval', 'author_id' ],
						} }
						onChange={ partial =>
							setFeedData( prev => ( {
								...prev,
								...partial,
							} ) )
						}
					/>
					<div>
						<Button variant="secondary" type="submit" isBusy={ isAdding } disabled={ isAdding || ! feedData.feed_url }>
							{ isAdding ? __( 'Adding…', 'newspack-lite-site' ) : __( 'Add RSS Feed', 'newspack-lite-site' ) }
						</Button>
					</div>
				</div>
			</form>
		</div>
	);
};
