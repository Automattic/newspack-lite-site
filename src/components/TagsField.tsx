/**
 * Tag multi-select field component with live search.
 */

/**
 * WordPress dependencies.
 */
import { BaseControl, FormTokenField } from '@wordpress/components';
import { useDebounce } from '@wordpress/compose';
import apiFetch from '@wordpress/api-fetch';
import { useState, useEffect, useCallback, useMemo } from '@wordpress/element';
import { decodeEntities } from '@wordpress/html-entities';
import { addQueryArgs } from '@wordpress/url';

/**
 * Internal dependencies.
 */
import { type TagsFieldProps, type TagItem } from '../types/settings';

const DEBOUNCE_DELAY = 200;

/**
 * Multi-select tag field backed by FormTokenField with live REST API search.
 *
 * Fetches suggestions from /wp/v2/tags on mount and on search input. Saved
 * IDs are resolved to names via a separate fetch so existing values display
 * correctly on load.
 */
export const TagsField = ( { value, onChange, label, help }: TagsFieldProps ) => {
	const [ suggestions, setSuggestions ] = useState< TagItem[] >( [] );
	const [ savedItems, setSavedItems ] = useState< TagItem[] >( [] );

	// Fetch initial suggestions on mount.
	useEffect( () => {
		apiFetch< TagItem[] >( {
			path: addQueryArgs( '/wp/v2/tags', {
				per_page: 100,
				_fields: 'id,name',
			} ),
		} )
			.then( setSuggestions )
			.catch( () => {} );
	}, [] );

	const valueKey = value.join( ',' );

	// Fetch labels for already-saved IDs so tokens display correctly on load.
	useEffect( () => {
		if ( ! valueKey ) {
			setSavedItems( [] );
			return;
		}
		apiFetch< TagItem[] >( {
			path: addQueryArgs( '/wp/v2/tags', {
				include: valueKey,
				per_page: 100,
				_fields: 'id,name',
			} ),
		} )
			.then( setSavedItems )
			.catch( () => {} );
	}, [ valueKey ] );

	const fetchSuggestions = useCallback( ( search: string ) => {
		apiFetch< TagItem[] >( {
			path: addQueryArgs( '/wp/v2/tags', {
				search,
				per_page: 100,
				_fields: 'id,name',
			} ),
		} )
			.then( setSuggestions )
			.catch( () => {} );
	}, [] );

	// Debounced search to avoid firing on every keystroke.
	const debouncedFetch = useDebounce( fetchSuggestions, DEBOUNCE_DELAY );

	// Merge saved items and suggestions into a single id→name map.
	const allItems = useMemo( () => {
		const map = new Map< number, string >();
		[ ...suggestions, ...savedItems ].forEach( tag => map.set( tag.id, decodeEntities( tag.name ) ) );
		return map;
	}, [ suggestions, savedItems ] );

	// Convert stored IDs to display labels for FormTokenField.
	const tokenValue = value.map( id => allItems.get( id ) ).filter( ( name ): name is string => name !== undefined );

	const suggestionLabels = Array.from( allItems.values() );

	const handleChange = ( tokens: ( string | { value: string } )[] ) => {
		const names = tokens.map( t => ( typeof t === 'string' ? t : t.value ) );
		const ids = names
			.map( name => {
				for ( const [ id, tagName ] of allItems ) {
					if ( tagName === name ) {
						return id;
					}
				}
				return null;
			} )
			.filter( ( id ): id is number => id !== null );
		onChange( ids );
	};

	return (
		<BaseControl help={ help }>
			<FormTokenField
				label={ label }
				value={ tokenValue }
				suggestions={ suggestionLabels }
				onInputChange={ debouncedFetch }
				onChange={ handleChange }
				__experimentalExpandOnFocus
				__next40pxDefaultSize
			/>
		</BaseControl>
	);
};
