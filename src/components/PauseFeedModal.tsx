/**
 * Pause feed confirmation modal component.
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
 * Confirmation modal shown before pausing an RSS feed.
 *
 * Explains that pausing prevents future imports but will not interrupt any
 * import currently in progress, then offers Cancel and Pause buttons.
 */
export const PauseFeedModal = ( {
	items,
	closeModal,
	onActionPerformed,
	onAction,
}: FeedModalProps ) => {
	const item = items[ 0 ];

	return (
		<>
			<p>
				{ __(
					'Pausing will prevent future imports. Any import currently in progress will finish.',
					'newspack-lite-site'
				) }
			</p>
			<p>
				<strong>{ item.feed_url }</strong>
			</p>
			<div className="newspack-lite-modal-actions">
				<Button variant="secondary" onClick={ () => closeModal?.() }>
					{ __( 'Cancel', 'newspack-lite-site' ) }
				</Button>
				<Button
					variant="primary"
					onClick={ () => {
						onAction( item.id, 'pause' );
						onActionPerformed?.( items );
						closeModal?.();
					} }
				>
					{ __( 'Pause', 'newspack-lite-site' ) }
				</Button>
			</div>
		</>
	);
};
