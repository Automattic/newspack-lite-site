/**
 * Delete feed confirmation modal component.
 */

/**
 * WordPress dependencies.
 */
import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import { type FeedModalProps } from '../types/rss-feed-import';

/**
 * Confirmation modal shown before deleting an RSS feed.
 *
 * Warns that the deletion is irreversible, then offers Cancel and Delete buttons.
 */
export const DeleteFeedModal = ( { items, closeModal, onActionPerformed, onAction }: FeedModalProps ) => {
	const item = items[ 0 ];

	return (
		<>
			<p>{ __( 'Are you sure you want to delete this RSS feed? This action cannot be undone.', 'newspack-lite-site' ) }</p>
			<p>
				<strong>{ item.feed_url }</strong>
			</p>
			<div className="newspack-lite-modal-actions">
				<Button variant="secondary" onClick={ () => closeModal?.() }>
					{ __( 'Cancel', 'newspack-lite-site' ) }
				</Button>
				<Button
					variant="primary"
					isDestructive
					onClick={ () => {
						onAction( item.id, 'delete' );
						onActionPerformed?.( items );
						closeModal?.();
					} }
				>
					{ __( 'Delete', 'newspack-lite-site' ) }
				</Button>
			</div>
		</>
	);
};
