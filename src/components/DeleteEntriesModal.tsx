/**
 * Delete entries confirmation modal component.
 */

/**
 * WordPress dependencies.
 */
import { useState } from '@wordpress/element';
import { Button } from '@wordpress/components';
import { __, _n, sprintf } from '@wordpress/i18n';
import { decodeEntities } from '@wordpress/html-entities';

/**
 * Internal dependencies.
 */
import { type DeleteEntriesModalProps } from '../types/rss-entries';

/**
 * Confirmation modal shown before permanently deleting RSS entries.
 *
 * Warns that the deletion is irreversible, then offers Cancel and Delete Permanently buttons.
 */
export const DeleteEntriesModal = ( { items, closeModal, onActionPerformed, onConfirm }: DeleteEntriesModalProps ) => {
	const [ isDeleting, setIsDeleting ] = useState( false );

	return (
		<>
			<p>
				{ 1 === items.length
					? __( "Permanently delete this entry? This can't be undone.", 'newspack-lite-site' )
					: sprintf(
							/* translators: %d: number of entries */
							_n(
								"Permanently delete %d entry? This can't be undone.",
								"Permanently delete these %d entries? This can't be undone.",
								items.length,
								'newspack-lite-site'
							),
							items.length
						) }
			</p>
			{ 1 === items.length && (
				<p>
					<strong>{ decodeEntities( items[ 0 ].title?.raw ?? '' ) || __( '(no title)', 'newspack-lite-site' ) }</strong>
				</p>
			) }
			<div className="newspack-lite-modal-actions">
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
					{ __( 'Delete Permanently', 'newspack-lite-site' ) }
				</Button>
			</div>
		</>
	);
};
