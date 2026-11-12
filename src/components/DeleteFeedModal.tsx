/**
 * Delete RSS feed confirmation modal component.
 */

/**
 * WordPress dependencies.
 */
import { useState } from '@wordpress/element';
import { Button } from '@wordpress/components';
import { Stack } from '@wordpress/ui';
import { __, _n, sprintf } from '@wordpress/i18n';
import { type RenderModalProps } from '@wordpress/dataviews';

/**
 * Internal dependencies.
 */
import { type Feed } from '../types/rss-feed-import';

const MAX_LISTED_FEEDS = 5;

/**
 * Confirmation modal shown before deleting an RSS feed.
 *
 * Explains that imports stop and existing entries stay, lists the feeds when there are only a few, then offers Cancel and Delete buttons.
 */
export const DeleteFeedModal = ( {
	items,
	closeModal,
	onActionPerformed,
	onConfirm,
}: RenderModalProps< Feed > & { onConfirm: ( items: Feed[] ) => Promise< boolean > } ) => {
	const [ isDeleting, setIsDeleting ] = useState( false );

	return (
		<>
			<p>
				{ 1 === items.length
					? __( 'Delete this RSS feed? It stops importing, and the entries it already imported stay on the site.', 'newspack-lite-site' )
					: sprintf(
							/* translators: %d: number of RSS feeds */
							_n(
								'Delete %d RSS feed? It stops importing, and the entries it already imported stay on the site.',
								'Delete %d RSS feeds? They stop importing, and the entries they already imported stay on the site.',
								items.length,
								'newspack-lite-site'
							),
							items.length
						) }
			</p>
			{ items.length <= MAX_LISTED_FEEDS && (
				<ul className="newspack-lite-delete-feeds">
					{ items.map( item => (
						<li key={ item.id }>
							<strong>{ item.feed_url }</strong>
						</li>
					) ) }
				</ul>
			) }
			<Stack direction="row" justify="flex-end" gap="sm">
				<Button variant="secondary" onClick={ () => closeModal?.() } disabled={ isDeleting }>
					{ __( 'Cancel', 'newspack-lite-site' ) }
				</Button>
				<Button
					variant="primary"
					isDestructive
					isBusy={ isDeleting }
					disabled={ isDeleting }
					onClick={ async () => {
						setIsDeleting( true );
						await onConfirm( items );
						onActionPerformed?.( items );
						closeModal?.();
					} }
				>
					{ __( 'Delete', 'newspack-lite-site' ) }
				</Button>
			</Stack>
		</>
	);
};
