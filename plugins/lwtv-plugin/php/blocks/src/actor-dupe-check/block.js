/**
 * Editor: Duplicate Actor Check
 *
 * An actor is almost always created from a blank Add Actor screen, because the
 * editorial order is show, then actors, then characters. That makes the title
 * field the only place a duplicate can be caught before a second post exists.
 *
 * Registered through block.json's editorScript rather than its own enqueue, the
 * same way wikidata-actor is. Note that editorScript is load-bearing: without
 * it the JavaScript is never built or enqueued, and the panel never runs.
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
