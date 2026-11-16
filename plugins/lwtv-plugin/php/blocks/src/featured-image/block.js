/**
 * BLOCK: Featured Image
 *
 */

import metadata from './block.json';

import Edit from './js/edit';
import Save from './js/save';
import Icon from '../_common/svg/featured-image';

// Import CSS.
import './css/editor.scss';

import { registerBlockType } from '@wordpress/blocks';
import { addFilter } from '@wordpress/hooks';

const withImageSize = function () {
	return 'large';
};

addFilter( 'editor.PostFeaturedImage.imageSize', metadata.name, withImageSize );

registerBlockType( metadata.name, {
	icon: Icon,
	edit: Edit,
	save: Save,
} );
