/**
 * BLOCK: Glossary
 */

//  Import CSS.
import './editor.scss';
import Icon from '../../_common/svg/glossary';

import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, SelectControl } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';

registerBlockType( 'lez-library/glossary', {
	apiVersion: 3,
	title: 'Glossary',
	icon: Icon,
	category: 'lezwatch',
	attributes: {
		taxonomy: { type: 'string' },
	},
	edit: function Edit( { attributes, setAttributes } ) {
		const { taxonomy } = attributes;
		const blockProps = useBlockProps();

		return (
			<>
				<InspectorControls>
					<PanelBody title={ 'Glossary Block Settings' }>
						<SelectControl
							label={ 'Taxonomy' }
							value={ taxonomy }
							options={ [
								{
									label: 'Choose a taxonomy...',
									value: '',
								},
								{
									label: 'Clichés',
									value: 'lez_cliches',
								},
								{
									label: 'Tropes',
									value: 'lez_tropes',
								},
								{
									label: 'Formats',
									value: 'lez_formats',
								},
								{
									label: 'Genres',
									value: 'lez_genres',
								},
								{
									label: 'Intersections',
									value: 'lez_intersections',
								},
							] }
							onChange={ ( value ) =>
								setAttributes( { taxonomy: value } )
							}
						/>
					</PanelBody>
				</InspectorControls>
				<div { ...blockProps }>
					<ServerSideRender
						block="lez-library/glossary"
						attributes={ attributes }
					/>
				</div>
			</>
		);
	},
	save() {
		// Rendering in PHP
		return null;
	},
} );
