import { PanelBody, SelectControl } from '@wordpress/components';
import {
	RichText,
	InspectorControls,
	useBlockProps,
} from '@wordpress/block-editor';

export default function Edit( props ) {
	const { attributes, setAttributes } = props;
	const { summary, grade } = attributes;
	const blockProps = useBlockProps( {
		className: 'bd-callout show-grade',
	} );

	// Summary:
	function onSummaryChange( value ) {
		setAttributes( { summary: value } );
	}

	return (
		<>
			<InspectorControls>
				<PanelBody title={ 'Grade Block Settings' }>
					<SelectControl
						label={ 'Grade' }
						value={ grade }
						options={ [
							{ label: 'Pick a grade...', value: '' },
							{ label: 'A+', value: 'A+' },
							{ label: 'A', value: 'A' },
							{ label: 'A-', value: 'A-' },
							{ label: 'B+', value: 'B+' },
							{ label: 'B', value: 'B' },
							{ label: 'B-', value: 'B-' },
							{ label: 'C+', value: 'C+' },
							{ label: 'C', value: 'C' },
							{ label: 'C-', value: 'C-' },
							{ label: 'D', value: 'D' },
							{ label: 'F', value: 'F' },
						] }
						onChange={ ( value ) =>
							setAttributes( { grade: value } )
						}
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				<div className="show-grade grade">{ grade }</div>
				<div className="show-grade body">
					<RichText
						tagName="p"
						value={ summary }
						placeholder={ 'Summary (could have been better...)' }
						onChange={ onSummaryChange }
					/>
				</div>
			</div>
		</>
	);
}
