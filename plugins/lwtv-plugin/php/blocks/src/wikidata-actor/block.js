/**
 * Sidebar: Wikidata
 */

// Import defaults
import metadata from './block.json';
import { registerPlugin } from '@wordpress/plugins';

// Plugin Specific Imports
import Render from './js/render';
import './css/editor.scss';

registerPlugin(metadata.textdomain, {
	render: Render,
});
