/**
 * BLOCK: TV Show Calendar
 */

//  Import CSS.
import './editor.scss';
import Icon from '../../_common/svg/calendar-week';

import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps } from '@wordpress/block-editor';
import ServerSideRender from '@wordpress/server-side-render';

// Register block
registerBlockType( 'lwtv/tvshow-calendar', {
	apiVersion: 3,
	title: 'TV Shows Calendar',
	icon: Icon,
	category: 'lezwatch',
	keywords: [ 'calendar', 'tv shows' ],
	edit: function Edit() {
		return (
			<div { ...useBlockProps() }>
				<ServerSideRender block="lwtv/tvshow-calendar" />
			</div>
		);
	},
	save() {
		// This is a display only block. No saving.
		return null;
	},
} );
