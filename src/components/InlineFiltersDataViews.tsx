/**
 * DataViews with the inline filters toolbar: filter chips beside the search, the view options on the right.
 */

/**
 * WordPress dependencies.
 */
import { useMemo } from '@wordpress/element';
import { DataViews } from '@wordpress/dataviews';
import { Stack } from '@wordpress/ui';

/**
 * Internal dependencies.
 */
import CountFooter, { type CountLabel } from './CountFooter';
import { columnShareWidth, evenColumns, titleColumnWidth, withoutColumnStyles } from '../utils/even-columns';
import './inline-filters-dataviews.scss';

type InlineFiltersDataViewsProps< Item > = React.ComponentProps< typeof DataViews< Item > > & { fitColumns?: string[]; countLabel?: CountLabel };

function InlineFiltersLayout( { header, searchLabel, footer }: { header?: React.ReactNode; searchLabel?: string; footer?: React.ReactNode } ) {
	return (
		<>
			<Stack direction="row" justify="space-between" align="flex-start" gap="xs" className="dataviews__view-actions">
				<Stack direction="row" align="center" gap="sm" wrap="wrap" className="dataviews__search">
					<DataViews.Search label={ searchLabel } />
					<DataViews.Filters />
				</Stack>
				<Stack direction="row" align="center" gap="xs" className="newspack-dataviews__view-config">
					<DataViews.LayoutSwitcher />
					<DataViews.ViewConfig />
					{ header }
				</Stack>
			</Stack>
			<DataViews.Layout />
			{ footer ?? <DataViews.Footer /> }
		</>
	);
}

function InlineFiltersDataViewsImpl( {
	fields,
	children,
	view,
	onChangeView,
	fitColumns,
	countLabel,
	...props
}: InlineFiltersDataViewsProps< unknown > ) {
	const shownFields = useMemo(
		() =>
			fields.map( field =>
				field.filterBy && false !== field.filterBy.isPrimary ? { ...field, filterBy: { ...field.filterBy, isPrimary: true } } : field
			),
		[ fields ]
	);
	const evenView = useMemo( () => evenColumns( view, fitColumns ), [ view, fitColumns ] );
	const columnWidths = useMemo( () => {
		const widths: Record< string, string > = {};
		const titleWidth = titleColumnWidth( view, fitColumns );
		const columnWidth = columnShareWidth( view );
		if ( titleWidth ) {
			widths[ '--newspack-dataviews-title-width' ] = titleWidth;
		}
		if ( columnWidth ) {
			widths[ '--newspack-dataviews-column-width' ] = columnWidth;
		}
		return Object.keys( widths ).length ? ( widths as React.CSSProperties ) : undefined;
	}, [ view, fitColumns ] );

	const footer = countLabel ? (
		<CountFooter
			countLabel={ countLabel }
			view={ evenView }
			data={ props.data }
			actions={ props.actions }
			paginationInfo={ props.paginationInfo }
			isLoading={ props.isLoading }
		/>
	) : undefined;

	return (
		<div className="newspack-dataviews" style={ columnWidths }>
			<DataViews
				{ ...props }
				view={ evenView }
				onChangeView={ nextView => onChangeView( withoutColumnStyles( nextView ) ) }
				fields={ shownFields }
			>
				{ children ?? <InlineFiltersLayout header={ props.header } searchLabel={ props.searchLabel } footer={ footer } /> }
			</DataViews>
		</div>
	);
}

/**
 * DataViews wrapper that shows the declared filters as chips beside the search, with no filter toggle.
 * A field with `filterBy.isPrimary: false` stays behind "Add filter".
 */
export const InlineFiltersDataViews = InlineFiltersDataViewsImpl as < Item >( props: InlineFiltersDataViewsProps< Item > ) => React.JSX.Element;
