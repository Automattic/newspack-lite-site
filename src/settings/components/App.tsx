/**
 * Settings & Appearance page root component.
 */

/**
 * WordPress dependencies.
 */
import { Button, Notice, Snackbar, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import { useSettings } from '../hooks/useSettings';
import { SettingsPanel } from './SettingsPanel';
import { AppearancePanel } from './AppearancePanel';

/**
 * Root component for the Settings page.
 *
 * Loads current settings on mount, renders the active panel (General or
 * Appearance), and handles saving via the WordPress REST API.
 */
export const App = () => {
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

	const activePanel =
		window.NewspackLiteSiteSettings?.tab === 'appearance'
			? 'appearance'
			: 'settings';

	if ( isLoading ) {
		return <Spinner />;
	}

	return (
		<div className="newspack-lite-settings-wrap">
			{ error && <Notice status="error">{ error }</Notice> }

			{ saveSuccess && (
				<div className="newspack-lite-snackbar-container">
					<Snackbar>
						{ __( 'Settings saved.', 'newspack-lite-site' ) }
					</Snackbar>
				</div>
			) }

			<div className="newspack-lite-sections">
				{ activePanel === 'settings' ? (
					<SettingsPanel
						settings={ settings }
						onChange={ updateSetting }
					/>
				) : (
					<AppearancePanel
						settings={ settings }
						onChange={ updateSetting }
					/>
				) }

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
			</div>
		</div>
	);
};
