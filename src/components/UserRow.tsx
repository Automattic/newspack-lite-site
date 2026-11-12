/**
 * Author name with avatar.
 */

/**
 * WordPress dependencies.
 */
import { Icon, commentAuthorAvatar } from '@wordpress/icons';
import { Stack } from '@wordpress/ui';

interface UserRowProps {
	label: string;
	avatarUrls?: Record< string, string >;
}

/**
 * Avatar and name on one line, falling back to a person icon when the user has no avatar.
 *
 * @param props            Component props.
 * @param props.label      Name to display.
 * @param props.avatarUrls REST `avatar_urls`, keyed by size.
 */
export function UserRow( { label, avatarUrls = {} }: UserRowProps ) {
	const avatarUrl = avatarUrls[ 24 ] || avatarUrls[ 48 ];

	return (
		<Stack render={ <span /> } direction="row" align="center" gap="sm">
			{ avatarUrl ? (
				<Stack render={ <span /> } className="newspack-lite-user-row__avatar" align="center" justify="center">
					<img src={ avatarUrl } srcSet={ avatarUrls[ 48 ] ? `${ avatarUrls[ 48 ] } 2x` : undefined } width={ 16 } height={ 16 } alt="" />
				</Stack>
			) : (
				<Icon icon={ commentAuthorAvatar } size={ 24 } />
			) }
			<span>{ label }</span>
		</Stack>
	);
}
