/**
 * Settings & Appearance page root component.
 */

/**
 * WordPress dependencies.
 */
import { Button, Notice, Snackbar, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * External dependencies.
 */
import { HashRouter, Switch, Route, Redirect } from 'react-router-dom';
import { TabbedNavigation } from 'newspack-components';

/**
 * Internal dependencies.
 */
import { useSettings } from '../hooks/useSettings';
import { AppHeader } from '../components/AppHeader';
import { SettingsPanel } from '../components/SettingsPanel';
import { AppearancePanel } from '../components/AppearancePanel';

/**
 * Tab definitions for the Settings page.
 */
const SETTINGS_TABS = [
	{ label: __( 'General', 'newspack-lite-site' ), path: '/general' },
	{ label: __( 'Appearance', 'newspack-lite-site' ), path: '/appearance' },
];

/**
 * Root component for the Settings page.
 *
 * Loads current settings on mount, renders the active panel (General or
 * Appearance), and handles saving via the WordPress REST API.
 */
export const SettingsApp = () => {
	const {
		settings,
		updateSetting,
		isLoading,
		isSaving,
		isDirty,
		saveSettings,
		error,
		saveSuccess,
	} = useSettings();

	const saveButton = (
		<div className="newspack-lite-buttons-card">
			<Button
				variant="primary"
				isBusy={ isSaving }
				disabled={ ! isDirty || isSaving }
				onClick={ saveSettings }
			>
				{ isSaving
					? __( 'Saving…', 'newspack-lite-site' )
					: __( 'Save Settings', 'newspack-lite-site' ) }
			</Button>
		</div>
	);

	return (
		<HashRouter>
			<div className="wrap">
				<AppHeader
					headerText={ __( 'Settings', 'newspack-lite-site' ) }
				/>
				<TabbedNavigation items={ SETTINGS_TABS } />
				{ error && (
					<Notice status="error" isDismissible={ false }>
						{ error }
					</Notice>
				) }
				<Switch>
					<Route path="/general">
						<div className="newspack-lite-sections">
							{ isLoading ? (
								<Spinner />
							) : (
								<SettingsPanel
									settings={ settings }
									onChange={ updateSetting }
								/>
							) }
							{ saveButton }
						</div>
					</Route>
					<Route path="/appearance">
						<div className="newspack-lite-sections">
							{ isLoading ? (
								<Spinner />
							) : (
								<AppearancePanel
									settings={ settings }
									onChange={ updateSetting }
								/>
							) }
							{ saveButton }
						</div>
					</Route>
					<Redirect to="/general" />
				</Switch>
				{ saveSuccess && (
					<div className="newspack-lite-snackbar-container">
						<Snackbar>
							{ __( 'Settings saved.', 'newspack-lite-site' ) }
						</Snackbar>
					</div>
				) }
			</div>
		</HashRouter>
	);
};
