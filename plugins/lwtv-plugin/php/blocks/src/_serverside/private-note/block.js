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

// Current markup: the wrapper props supply `wp-block-lez-library-private-note`
// plus any custom class, so no attribute has to be stitched in by hand.
function Save() {
	const blockProps = useBlockProps.save( {
		className: 'alert alert-warning',
	} );
	return (
		<div { ...blockProps }>
			<InnerBlocks.Content />
		</div>
	);
}

// Markup written since the apiVersion 3 switch (2026-06-29). It never called
// useBlockProps.save(), and it read `attributes.className` directly, so a note
// without a custom class saved the literal word "undefined" as a class.
function SaveWithoutBlockProps( { attributes: { className } } ) {
	return (
		<div className={ `${ className } alert alert-warning` }>
			<InnerBlocks.Content />
		</div>
	);
}

const hasUndefinedClass = ( { className } ) =>
	/(^|\s)undefined(\s|$)/.test( className ?? '' );

// Older (apiVersion 1) posts also carried the generated wrapper class, so they
// validate against the current save, with "undefined" parsed into className.
const removeUndefinedClass = ( attributes ) => ( {
	...attributes,
	className:
		( attributes.className ?? '' )
			.split( /\s+/ )
			.filter( ( name ) => name && name !== 'undefined' )
			.join( ' ' ) || undefined,
} );

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

	save: Save,

	deprecated: [
		{
			save: Save,
			isEligible: hasUndefinedClass,
			migrate: removeUndefinedClass,
		},
		{
			save: SaveWithoutBlockProps,
			migrate: removeUndefinedClass,
		},
	],
} );
