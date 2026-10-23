/**
 * BLOCK: Spoilers
 *
 */

// Import defaults
import metadata from './block.json';
import Edit from './js/edit';
import Save from './js/save';
import deprecated from './js/deprecated';
import Icon from '../_common/svg/spoiler';

//  Import CSS.
import './css/style.scss';
import './css/editor.scss';

import { registerBlockType } from '@wordpress/blocks';

registerBlockType( metadata.name, {
	icon: Icon,
	edit: Edit,
	save: Save,
	deprecated,
} );
