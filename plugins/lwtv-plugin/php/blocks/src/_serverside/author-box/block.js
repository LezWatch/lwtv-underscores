/**
 * BLOCK: Author Box
 */

// Import CSS.
import './editor.scss';

import { registerBlockType } from '@wordpress/blocks';

// Edit as it's own file
import edit from './edit';
import Icon from '../../_common/svg/team-member';

// Register block
registerBlockType('lwtv/author-box', {
	apiVersion: 3,
	title: 'Team Member',
	icon: Icon,
	category: 'lezwatch',
	attributes: {
		users: { type: 'string' },
		format: { type: 'string', default: 'large' },
	},
	edit,
	save() {
		// Rendering in PHP
		return null;
	},
});
