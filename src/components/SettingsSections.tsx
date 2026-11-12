/**
 * Two-column settings sections.
 */

/**
 * WordPress dependencies.
 */
import { Fragment } from '@wordpress/element';
import { CheckboxControl } from '@wordpress/components';
import { DataForm, type Field } from '@wordpress/dataviews';
import { Stack } from '@wordpress/ui';

/**
 * External dependencies.
 */
import Divider from 'newspack-components/dist/esm/divider';
import Grid from 'newspack-components/dist/esm/grid';
import SectionHeader from 'newspack-components/dist/esm/section-header';

/**
 * Internal dependencies.
 */
import { type SettingsSection, type SiteSettings } from '../types/settings';

/**
 * Renders named settings sections, each with its title and description on the
 * left and its fields on the right, separated by full-width dividers.
 *
 * Each field gets its own DataForm so the column's Stack gap spaces the fields.
 * Boolean fields render as a bare CheckboxControl, since the DataForm field wrapper
 * adds a minimum height that leaves a gap below a checkbox.
 *
 * @param {Object}   props          Component props.
 * @param {Array}    props.sections Sections to render, in order.
 * @param {Array}    props.fields   DataForm field definitions the sections draw from.
 * @param {Object}   props.settings Current settings.
 * @param {Function} props.onChange Called with the changed settings.
 */
export const SettingsSections = ( {
	sections,
	fields,
	settings,
	onChange,
}: {
	sections: SettingsSection[];
	fields: Field< SiteSettings >[];
	settings: SiteSettings;
	onChange: ( partial: Partial< SiteSettings > ) => void;
} ) => (
	<div>
		{ sections.map( ( section, index ) => (
			<Fragment key={ section.id }>
				{ index > 0 && <Divider alignment="full-width" variant="tertiary" /> }
				<Grid columns={ 2 } gutter={ 32 } noMargin>
					<SectionHeader heading={ 2 } noMargin title={ section.title } description={ section.description } />
					<Stack direction="column" gap="xl">
						{ section.fields.map( fieldId => {
							const field = fields.find( ( { id } ) => id === fieldId );
							if ( field?.type === 'boolean' ) {
								return (
									<CheckboxControl
										key={ fieldId }
										__nextHasNoMarginBottom
										label={ field.label }
										help={ field.description }
										checked={ !! settings[ fieldId as keyof SiteSettings ] }
										onChange={ checked => onChange( { [ fieldId ]: checked } as Partial< SiteSettings > ) }
									/>
								);
							}
							return (
								<DataForm
									key={ fieldId }
									data={ settings }
									fields={ fields }
									form={ { layout: { type: 'regular' }, fields: [ fieldId ] } }
									onChange={ partial => onChange( partial as Partial< SiteSettings > ) }
								/>
							);
						} ) }
					</Stack>
				</Grid>
			</Fragment>
		) ) }
	</div>
);
