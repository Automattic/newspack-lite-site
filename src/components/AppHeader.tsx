/**
 * App header component.
 */

/**
 * External dependencies.
 */
import { NewspackIcon } from 'newspack-components';

/**
 * Internal dependencies.
 */
import { type AppHeaderProps } from '../types/settings';

/**
 * Renders the Newspack admin header bar with logo and page title.
 */
export const AppHeader = ( { headerText, subHeaderText }: AppHeaderProps ) => {
	return (
		<div className="newspack-wizard__header">
			<div className="newspack-wizard__header__inner">
				<div className="newspack-wizard__title">
					<NewspackIcon size={ 36 } />
					<div>
						<h2>{ headerText }</h2>
						{ subHeaderText && <span>{ subHeaderText }</span> }
					</div>
				</div>
			</div>
		</div>
	);
};
