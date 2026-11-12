/**
 * Settings page root component.
 */

/**
 * WordPress dependencies.
 */
import { useEffect, useRef, useState } from '@wordpress/element';
import { Button, Notice, Snackbar } from '@wordpress/components';
import { Stack } from '@wordpress/ui';
import { __ } from '@wordpress/i18n';

/**
 * External dependencies.
 */
import Router from 'newspack-components/dist/esm/proxied-imports/router';
import Page from 'newspack-components/dist/esm/page';
import TabbedNavigation from 'newspack-components/dist/esm/tabbed-navigation';
import Waiting from 'newspack-components/dist/esm/waiting';
import useUnsavedChangesDialog from 'newspack-components/dist/esm/hooks/use-unsaved-changes-dialog';

/**
 * Internal dependencies.
 */
import { useSettings } from '../hooks/useSettings';
import { SettingsPanel } from '../components/SettingsPanel';
import { AppearancePanel } from '../components/AppearancePanel';

const { HashRouter, Switch, Route, Redirect, useLocation } = Router;

const SNACKBAR_DURATION = 3000;

/**
 * Tab definitions for the Settings page.
 */
const SETTINGS_TABS = [
	{
		label: __( 'General', 'newspack-lite-site' ),
		path: '/general',
		subTitle: __( 'Turn on Lite Site and choose the content it shows.', 'newspack-lite-site' ),
		Panel: SettingsPanel,
	},
	{
		label: __( 'Appearance', 'newspack-lite-site' ),
		path: '/appearance',
		subTitle: __( 'Set colors and fonts. Keep it light so pages load fast on slow connections.', 'newspack-lite-site' ),
		Panel: AppearancePanel,
	},
];

/**
 * The Settings page inside the router: header with tabs and Save, and the active tab's sections.
 *
 * Both tabs edit one settings object and Save stores all of it. Leaving a tab with
 * unsaved changes asks first, and moving to another tab discards them, so each tab
 * only ever saves what it shows.
 */
const SettingsScreen = () => {
	const { pathname } = useLocation();
	const { settings, savedSettings, updateSettings, isLoading, isSaving, isDirty, saveSettings, discardChanges, error } = useSettings();
	const [ isSaved, setIsSaved ] = useState( false );
	const lastPathname = useRef( pathname );
	const activeTab = SETTINGS_TABS.find( tab => tab.path === pathname );

	const { confirmDialog } = useUnsavedChangesDialog( { when: isDirty && ! isSaving } );

	const handleSave = async () => {
		setIsSaved( false );
		if ( await saveSettings() ) {
			setIsSaved( true );
		}
	};

	const saveRef = useRef( handleSave );
	saveRef.current = handleSave;

	useEffect( () => {
		if ( lastPathname.current !== pathname ) {
			lastPathname.current = pathname;
			discardChanges();
		}
	}, [ pathname, discardChanges ] );

	useEffect( () => {
		if ( ! isSaved ) {
			return;
		}
		const timer = setTimeout( () => setIsSaved( false ), SNACKBAR_DURATION );
		return () => clearTimeout( timer );
	}, [ isSaved ] );

	return (
		<>
			<Page
				breadcrumbItems={ [
					{ label: 'Lite Site' },
					{ label: __( 'Settings', 'newspack-lite-site' ) },
					...( activeTab ? [ { label: activeTab.label } ] : [] ),
				] }
				subTitle={ activeTab?.subTitle }
				tabbedNavigation={ <TabbedNavigation items={ SETTINGS_TABS } /> }
				actions={
					<Button
						variant="primary"
						onClick={ () => saveRef.current() }
						isBusy={ isSaving }
						disabled={ ! settings || ! isDirty || isSaving }
						accessibleWhenDisabled
					>
						{ __( 'Save', 'newspack-lite-site' ) }
					</Button>
				}
			>
				<div className="newspack-lite-settings">
					{ confirmDialog }
					<Switch>
						{ SETTINGS_TABS.map( ( { path, Panel } ) => (
							<Route key={ path } path={ path }>
								{ isLoading ? (
									<div className="newspack-lite-loader">
										<Stack direction="column" align="center" gap="sm">
											<Waiting noMargin />
											<strong>{ __( 'Fetching…', 'newspack-lite-site' ) }</strong>
										</Stack>
									</div>
								) : (
									<Stack direction="column" gap="xl">
										{ error && (
											<Notice status="error" isDismissible={ false } politeness={ settings ? 'assertive' : 'polite' }>
												{ error }
											</Notice>
										) }
										<Panel settings={ settings } savedSettings={ savedSettings } onChange={ updateSettings } />
									</Stack>
								) }
							</Route>
						) ) }
						<Redirect to="/general" />
					</Switch>
				</div>
			</Page>
			{ isSaved && (
				<div className="newspack-lite-snackbar-container">
					<Snackbar>{ __( 'Settings saved.', 'newspack-lite-site' ) }</Snackbar>
				</div>
			) }
		</>
	);
};

/**
 * Root component for the Settings page.
 */
export const SettingsApp = () => (
	<HashRouter>
		<div className="wrap newspack-lite-settings-page">
			<SettingsScreen />
		</div>
	</HashRouter>
);
