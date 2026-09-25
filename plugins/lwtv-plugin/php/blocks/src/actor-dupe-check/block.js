/**
 * Editor: Duplicate Actor Check
 *
 * Warns on the title field of a new actor. block.json's editorScript is
 * load-bearing: without it this is never built or enqueued. See
 * docs/architecture/duplicate-detection.md#editor-warning.
 */

// Import defaults
import metadata from './block.json';
import { registerPlugin } from '@wordpress/plugins';

// Plugin Specific Imports
import Render from './js/render';
import './css/editor.scss';

registerPlugin( metadata.textdomain, {
	render: Render,
} );
