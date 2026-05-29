/**
 * Add Feed form component.
 */

/**
 * WordPress dependencies.
 */
import { useState } from '@wordpress/element';
import { Button, TextControl, SelectControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

interface Props {
	onAdd: ( data: {
		feed_url: string;
		interval: string;
		author_id: number;
	} ) => Promise< boolean >;
	isAdding: boolean;
}

/**
 * Form for adding a new RSS feed.
 *
 * Accepts a feed URL, import frequency, and author, then calls onAdd on submit.
 * Resets the URL field after a successful submission.
 */
export const FeedForm = ( { onAdd, isAdding }: Props ) => {
	const intervals = window.NewspackLiteSiteRssImport?.intervals ?? [];
	const authors = window.NewspackLiteSiteRssImport?.authors ?? [];

	const [ feedUrl, setFeedUrl ] = useState( '' );
	const [ interval, setInterval ] = useState(
		intervals[ 0 ]?.value ?? 'daily'
	);
	const [ authorId, setAuthorId ] = useState( authors[ 0 ]?.value ?? '' );

	const handleSubmit = async ( e: React.FormEvent ) => {
		e.preventDefault();
		const success = await onAdd( {
			feed_url: feedUrl,
			interval,
			author_id: parseInt( authorId, 10 ),
		} );
		if ( success ) {
			setFeedUrl( '' );
		}
	};

	return (
		<div className="newspack-lite-section">
			<div className="newspack-lite-section-header">
				<h3>{ __( 'Add Feed', 'newspack-lite-site' ) }</h3>
				<p>
					{ __(
						'Schedule an RSS feed to be imported as posts on a recurring basis.',
						'newspack-lite-site'
					) }
				</p>
			</div>

			<form onSubmit={ handleSubmit }>
				<div className="newspack-lite-section-fields">
					<TextControl
						label={ __( 'Feed URL', 'newspack-lite-site' ) }
						type="url"
						value={ feedUrl }
						onChange={ setFeedUrl }
						placeholder="https://example.com/feed/"
						required
						__nextHasNoMarginBottom
					/>
					<SelectControl
						label={ __( 'Frequency', 'newspack-lite-site' ) }
						value={ interval }
						options={ intervals }
						onChange={ setInterval }
						__nextHasNoMarginBottom
					/>
					<SelectControl
						label={ __( 'Author', 'newspack-lite-site' ) }
						value={ authorId }
						options={ authors }
						onChange={ setAuthorId }
						__nextHasNoMarginBottom
					/>
					<div>
						<Button
							variant="secondary"
							type="submit"
							isBusy={ isAdding }
							disabled={ isAdding || ! feedUrl }
						>
							{ isAdding
								? __( 'Adding…', 'newspack-lite-site' )
								: __( 'Add Feed', 'newspack-lite-site' ) }
						</Button>
					</div>
				</div>
			</form>
		</div>
	);
};
