/**
 * Delete RSS feed confirmation modal component.
 */

/**
 * WordPress dependencies.
 */
import { useState } from '@wordpress/element';
import { Button } from '@wordpress/components';
import { Stack } from '@wordpress/ui';
import { __ } from '@wordpress/i18n';
import { type RenderModalProps } from '@wordpress/dataviews';

/**
 * Internal dependencies.
 */
import { type Feed } from '../types/rss-feed-import';

/**
 * Confirmation modal shown before deleting an RSS feed.
 *
 * Explains that imports stop and existing entries stay, then offers Cancel and Delete buttons.
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
			<p>{ __( 'Delete this RSS feed? It stops importing, and the entries it already imported stay on the site.', 'newspack-lite-site' ) }</p>
			<p>
				<strong>{ items[ 0 ].feed_url }</strong>
			</p>
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
