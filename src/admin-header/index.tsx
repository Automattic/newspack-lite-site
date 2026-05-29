/**
 * Admin header entry point.
 *
 * Renders the shared header bar and tab navigation for the Settings
 * and RSS Feed Import admin pages.
 */

/**
 * WordPress dependencies.
 */
import { createRoot, createPortal, StrictMode } from '@wordpress/element';

/**
 * Internal dependencies.
 */
import './style.scss';

const { title = '', tabs = [] } = window.NewspackLiteSiteAdminHeader || {};

/**
 * Admin header component.
 *
 * Renders the Newspack Lite Site header bar with logo and page title.
 * When tabs are present, portals the tab navigation into #newspack-lite-tabs-nav
 * so it can be sticky relative to the full viewport.
 */
function AdminHeader() {
	const tabsContainer = document.getElementById( 'newspack-lite-tabs-nav' );

	return (
		<>
			<div className="newspack-lite-header">
				<div className="newspack-lite-header__inner">
					<div className="newspack-lite-title">
						<svg
							xmlns="http://www.w3.org/2000/svg"
							height="36"
							width="36"
							viewBox="0 0 24 24"
							className="newspack-lite-icon"
							aria-hidden="true"
							focusable={ false }
						>
							<path
								fillRule="evenodd"
								clipRule="evenodd"
								d="M24 12C24 18.6271 18.6271 24 12 24C5.37213 24 0 18.6271 0 12C0 5.3729 5.3729 0 12 0C18.6271 0 24 5.3729 24 12ZM17.4545 17.4546L6.54545 6.54545V17.4545H8.72727V11.8182L14.3636 17.4546H17.4545ZM11.2727 8.18182H17.4545V6.54545H9.63636L11.2727 8.18182ZM17.4545 11.2727H14.3636L12.7273 9.63636H17.4545V11.2727ZM17.4545 12.7273V14.3636L15.8182 12.7273H17.4545Z"
							/>
						</svg>
						<div>
							<h2>{ title }</h2>
						</div>
					</div>
				</div>
			</div>

			{ tabs.length > 0 &&
				tabsContainer &&
				createPortal(
					<div className="newspack-lite-tabbed-navigation">
						<ul>
							{ tabs.map( ( tab ) => (
								<li key={ tab.id }>
									<a
										href={ tab.href }
										className={
											tab.isActive ? 'selected' : ''
										}
									>
										{ tab.label }
									</a>
								</li>
							) ) }
						</ul>
					</div>,
					tabsContainer
				) }
		</>
	);
}

const el = document.getElementById( 'newspack-lite-admin-header' );
if ( el ) {
	createRoot( el ).render(
		<StrictMode>
			<AdminHeader />
		</StrictMode>
	);
}
