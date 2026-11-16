/**
 * External dependencies
 */

import { SelectControl, PanelBody, TextControl } from '@wordpress/components';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';

import ServerSideRender from '@wordpress/server-side-render';

export default function AuthorProfileBlock( { attributes, setAttributes } ) {
	const { users, format } = attributes;
	const blockProps = useBlockProps();

	return (
		<>
			<InspectorControls>
				<PanelBody title={ 'Team Member Settings' }>
					<TextControl
						label={ 'Username' }
						help={
							'Username or ID of team member (i.e. liljimmi, ipstenu, saralance)'
						}
						value={ users }
						onChange={ ( value ) =>
							setAttributes( { users: value } )
						}
					/>
					<SelectControl
						label={ 'Card Format' }
						value={ format }
						options={ [
							{ label: 'Large', value: 'large' },
							{ label: 'Compact', value: 'compact' },
							{ label: 'Thumbnail', value: 'thumbnail' },
						] }
						onChange={ ( value ) =>
							setAttributes( { format: value } )
						}
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				<ServerSideRender
					block="lwtv/author-box"
					attributes={ attributes }
				/>
			</div>
		</>
	);
}
