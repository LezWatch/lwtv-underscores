import { RichText } from '@wordpress/block-editor';
import { createElement } from '@wordpress/element';
import metadata from '../block.json';

// Bootstrap 5 markup (alert alert-danger), saved before the v6 migration.
// Lets those blocks validate; they upgrade on next save. Deprecations don't
// inherit attributes or supports, so pass them through.
const v1 = {
	attributes: metadata.attributes,
	supports: metadata.supports,
	save( props ) {
		return createElement(
			'div',
			{ className: 'wp-block-lez-library-spoilers alert alert-danger' },
			createElement( RichText.Content, {
				value: props.attributes.content,
			} )
		);
	},
};

export default [ v1 ];
