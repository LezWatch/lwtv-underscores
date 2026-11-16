/**
 * BLOCK: Private Note
 *
 */

//  Import CSS.
import './style.scss';
import './editor.scss';

import Icon from '../../_common/svg/private-note';

import { registerBlockType } from '@wordpress/blocks';
import { InnerBlocks, useBlockProps } from '@wordpress/block-editor';

registerBlockType( 'lez-library/private-note', {
	apiVersion: 3,
	title: 'Private Note',
	icon: Icon,
	category: 'lezwatch',
	description:
		'Private notes, only seen by logged in users. It will be stripped from all published pages.',

	// Declared on the block type, not as an InnerBlocks prop (deprecated in
	// WP 7.2, which is the first version to read it from here).
	template: [
		[
			'core/paragraph',
			{
				content:
					'All content in this block will be invisible to non-logged-in visitors (delete this and replace it).',
			},
		],
	],

	edit: function Edit() {
		const blockProps = useBlockProps( {
			className: 'alert alert-warning',
		} );
		return (
			<div { ...blockProps }>
				<InnerBlocks templateLock={ false } />
			</div>
		);
	},

	save: ( props ) => {
		const {
			attributes: { className },
		} = props;
		return (
			<div className={ `${ className } alert alert-warning` }>
				<InnerBlocks.Content />
			</div>
		);
	},
} );
