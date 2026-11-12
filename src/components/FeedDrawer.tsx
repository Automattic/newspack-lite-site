/**
 * Add and edit RSS feed drawer component.
 */

/**
 * WordPress dependencies.
 */
import { useEffect, useMemo, useRef, useState } from '@wordpress/element';
import { Notice, VisuallyHidden } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { rss, Icon } from '@wordpress/icons';
import type { ComponentProps, MouseEvent } from 'react';
import { DataForm } from '@wordpress/dataviews';

/**
 * External dependencies.
 */
import { Drawer } from 'newspack-components/dist/esm/drawer';

/**
 * Internal dependencies.
 */
import { type FeedChanges, type FeedDrawerProps, type FeedFormData } from '../types/rss-feed-import';
import { DEFAULT_FEED, getFeedFormFields, isKnownAuthor } from '../utils/feeds-dataform';

type NoticeAction = ComponentProps< typeof Notice >[ 'actions' ] extends ( infer A )[] | undefined ? A : never;

const FORM_FIELDS = [ 'feed_url', 'status', 'interval', 'author_id' ];

/**
 * Drawer for adding a new RSS feed or editing an existing one.
 *
 * Stays mounted and is driven by `isOpen`. Editing disables the URL.
 */
export const FeedDrawer = ( { isOpen, feed, isSaving, error, onClose, onAdd, onUpdate, onClearError, isImporting, onImport }: FeedDrawerProps ) => {
	const isEdit = !! feed;
	const initial: FeedFormData = useMemo(
		() =>
			feed
				? {
						feed_url: feed.feed_url,
						interval: feed.interval,
						author_id: isKnownAuthor( String( feed.author_id ) ) ? String( feed.author_id ) : '',
						status: feed.status,
					}
				: DEFAULT_FEED,
		[ feed ]
	);
	const [ draft, setDraft ] = useState< FeedFormData >( initial );
	const [ wasOpen, setWasOpen ] = useState( isOpen );
	const formRef = useRef< HTMLDivElement >( null );

	if ( wasOpen !== isOpen ) {
		setWasOpen( isOpen );
		if ( isOpen ) {
			setDraft( initial );
		}
	}

	useEffect( () => {
		if ( ! isOpen ) {
			return;
		}
		const timer = setTimeout( () => {
			formRef.current
				?.querySelector< HTMLElement >(
					'textarea:not([disabled]), input:not([disabled]), select:not([disabled]), [role="radio"][aria-checked="true"]'
				)
				?.focus();
		}, 50 );
		return () => clearTimeout( timer );
	}, [ isOpen ] );

	const needsAuthor = isEdit && '' === initial.author_id;
	const fields = useMemo( () => getFeedFormFields( isEdit, needsAuthor ), [ isEdit, needsAuthor ] );
	const isDirty = isEdit
		? draft.interval !== initial.interval || draft.author_id !== initial.author_id || draft.status !== initial.status
		: draft.feed_url !== initial.feed_url ||
			draft.interval !== initial.interval ||
			draft.author_id !== initial.author_id ||
			draft.status !== initial.status;

	const handleChange = ( partial: Partial< FeedFormData > ) => {
		onClearError();
		setDraft( current => ( { ...current, ...partial } ) );
	};

	const handleSave = async () => {
		let success: boolean;
		if ( feed ) {
			const changes: FeedChanges = {};
			if ( draft.interval !== initial.interval ) {
				changes.interval = draft.interval;
			}
			if ( draft.author_id !== initial.author_id ) {
				changes.author_id = parseInt( draft.author_id, 10 );
			}
			if ( draft.status !== initial.status ) {
				changes.status = draft.status;
			}
			success = await onUpdate( feed.id, changes );
		} else {
			success = await onAdd( {
				feed_url: draft.feed_url.trim(),
				interval: draft.interval,
				author_id: parseInt( draft.author_id, 10 ),
				status: draft.status,
			} );
		}
		if ( success ) {
			onClose();
		}
	};

	const canSave = ! isSaving && isKnownAuthor( draft.author_id ) && ( isEdit ? isDirty : '' !== draft.feed_url.trim() );

	const errorActions: NoticeAction[] = feed
		? [
				...( 'active' === feed.status
					? [
							{
								label: __( 'Retry Now', 'newspack-lite-site' ),
								className: isImporting ? 'is-busy' : undefined,
								disabled: isImporting,
								onClick: () => {
									onImport( feed.id );
								},
							},
						]
					: [] ),
				{
					label: (
						<>
							{ __( 'Open Feed URL', 'newspack-lite-site' ) }
							<VisuallyHidden> { __( '(opens in a new tab)', 'newspack-lite-site' ) }</VisuallyHidden>
						</>
					 ) as unknown as string,
					url: feed.feed_url,
					onClick: ( event: MouseEvent< HTMLAnchorElement | HTMLButtonElement > ) => {
						event.preventDefault();
						window.open( feed.feed_url, '_blank', 'noopener,noreferrer' );
					},
				},
			]
		: [];

	return (
		<Drawer.Root isOpen={ isOpen } size="medium" isDirty={ isDirty || isSaving } onRequestClose={ onClose }>
			<Drawer.Header>
				<Icon className="newspack-drawer__icon" icon={ rss } size={ 24 } />
				<Drawer.Title>{ isEdit ? __( 'Edit RSS Feed', 'newspack-lite-site' ) : __( 'Add RSS Feed', 'newspack-lite-site' ) }</Drawer.Title>
				<Drawer.CloseIcon />
			</Drawer.Header>
			<Drawer.Content>
				{ feed?.last_result?.error && (
					<Notice status="error" isDismissible={ false } politeness="polite" actions={ errorActions }>
						{ feed.last_result.error }
					</Notice>
				) }
				{ error && (
					<Notice status="error" isDismissible={ false } politeness="assertive">
						{ error }
					</Notice>
				) }
				<div ref={ formRef }>
					<DataForm
						data={ draft }
						fields={ fields }
						form={ { layout: { type: 'regular' }, fields: FORM_FIELDS } }
						onChange={ handleChange }
					/>
				</div>
			</Drawer.Content>
			<Drawer.Footer>
				<Drawer.Action variant="secondary" disabled={ isSaving } closes>
					{ __( 'Cancel', 'newspack-lite-site' ) }
				</Drawer.Action>
				<Drawer.Action variant="primary" isBusy={ isSaving } disabled={ ! canSave } onClick={ handleSave }>
					{ isEdit ? __( 'Save', 'newspack-lite-site' ) : __( 'Add RSS Feed', 'newspack-lite-site' ) }
				</Drawer.Action>
			</Drawer.Footer>
		</Drawer.Root>
	);
};
