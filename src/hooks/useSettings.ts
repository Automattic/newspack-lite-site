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
		include_subcategories: true,
		tags: [],
		excluded_categories: [],
		excluded_tags: [],
		footer_html: '',
		custom_css: '',
		external_links_new_tab: true,
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
 * values against the last saved snapshot. Saving resolves to whether it succeeded,
 * and discarding puts the last saved snapshot back.
 */
export function useSettings() {
	const [ settings, setSettings ] = useState< SiteSettings | null >( null );
	const [ savedSettings, setSavedSettings ] = useState< SiteSettings | null >( null );
	const [ isLoading, setIsLoading ] = useState( true );
	const [ isSaving, setIsSaving ] = useState( false );
	const [ error, setError ] = useState< string | null >( null );

	const isDirty = settings !== null && JSON.stringify( settings ) !== JSON.stringify( savedSettings );

	useEffect( () => {
		let cancelled = false;

		apiFetch< Record< string, SiteSettings > >( { path: SETTINGS_PATH } )
			.then( response => {
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
				setError( err.message ?? __( "Couldn't load settings. Reload the page to try again.", 'newspack-lite-site' ) );
				setIsLoading( false );
			} );

		return () => {
			cancelled = true;
		};
	}, [] );

	const updateSettings = useCallback( ( partial: Partial< SiteSettings > ) => {
		setSettings( prev => ( prev ? { ...prev, ...partial } : prev ) );
	}, [] );

	const discardChanges = useCallback( () => {
		setSettings( savedSettings );
	}, [ savedSettings ] );

	const saveSettings = useCallback( async (): Promise< boolean > => {
		if ( ! settings ) {
			return false;
		}

		setIsSaving( true );
		setError( null );

		try {
			const response = await apiFetch< Record< string, SiteSettings > >( {
				path: SETTINGS_PATH,
				method: 'POST',
				data: { [ OPTION_KEY ]: settings },
			} );

			const saved = response[ OPTION_KEY ] ?? settings;
			setSettings( saved );
			setSavedSettings( saved );
			return true;
		} catch ( err ) {
			setError( ( err as Error ).message ?? __( "Couldn't save settings. Try again.", 'newspack-lite-site' ) );
			return false;
		} finally {
			setIsSaving( false );
		}
	}, [ settings ] );

	return {
		settings,
		updateSettings,
		isLoading,
		isSaving,
		isDirty,
		saveSettings,
		discardChanges,
		error,
	};
}
