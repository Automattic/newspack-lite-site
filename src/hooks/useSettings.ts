/**
 * Settings data hook.
 */

/**
 * WordPress dependencies.
 */
import { useState, useEffect, useCallback } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import { type SiteSettings } from '../types/settings';

const SETTINGS_PATH = '/wp/v2/settings';
const OPTION_KEY = 'newspack_lite_site_settings';

/**
 * Returns the default settings object used before the REST response arrives.
 */
function getDefaultSettings(): SiteSettings {
	return {
		enabled: false,
		url_base: 'lite',
		posts_per_page: 10,
		categories: [],
		footer_html: '',
		ga4_measurement_id: '',
		primary_color: '',
		font_import_url: '',
		font_body: '',
	};
}

/**
 * Manages settings state and REST API interactions for the Settings page.
 *
 * Fetches saved settings on mount. Tracks dirty state by comparing current
 * values against the last saved snapshot.
 */
export function useSettings() {
	const [ settings, setSettings ] = useState< SiteSettings | null >( null );
	const [ savedSettings, setSavedSettings ] = useState< SiteSettings | null >(
		null
	);
	const [ isLoading, setIsLoading ] = useState( true );
	const [ isSaving, setIsSaving ] = useState( false );
	const [ error, setError ] = useState< string | null >( null );
	const [ saveSuccess, setSaveSuccess ] = useState( false );

	const isDirty =
		settings !== null &&
		JSON.stringify( settings ) !== JSON.stringify( savedSettings );

	useEffect( () => {
		let cancelled = false;

		apiFetch< Record< string, SiteSettings > >( { path: SETTINGS_PATH } )
			.then( ( response ) => {
				if ( cancelled ) {
					return;
				}
				const fetched = response[ OPTION_KEY ] ?? getDefaultSettings();
				setSettings( fetched );
				setSavedSettings( fetched );
				setIsLoading( false );
			} )
			.catch( ( err: Error ) => {
				if ( cancelled ) {
					return;
				}
				setError(
					err.message ??
						__( 'Failed to load settings.', 'newspack-lite-site' )
				);
				setIsLoading( false );
			} );

		return () => {
			cancelled = true;
		};
	}, [] );

	const updateSetting = useCallback(
		< K extends keyof SiteSettings >(
			key: K,
			value: SiteSettings[ K ]
		) => {
			setSettings( ( prev ) =>
				prev ? { ...prev, [ key ]: value } : prev
			);
			setSaveSuccess( false );
		},
		[]
	);

	const saveSettings = useCallback( async () => {
		if ( ! settings ) {
			return;
		}

		setIsSaving( true );
		setError( null );
		setSaveSuccess( false );

		try {
			const response = await apiFetch< Record< string, SiteSettings > >( {
				path: SETTINGS_PATH,
				method: 'POST',
				data: { [ OPTION_KEY ]: settings },
			} );

			const saved = response[ OPTION_KEY ] ?? settings;
			setSettings( saved );
			setSavedSettings( saved );
			setSaveSuccess( true );
			setTimeout( () => setSaveSuccess( false ), 3000 );
		} catch ( err ) {
			setError(
				( err as Error ).message ??
					__( 'Failed to save settings.', 'newspack-lite-site' )
			);
		} finally {
			setIsSaving( false );
		}
	}, [ settings ] );

	return {
		settings,
		updateSetting,
		isLoading,
		isSaving,
		isDirty,
		saveSettings,
		error,
		saveSuccess,
	};
}
