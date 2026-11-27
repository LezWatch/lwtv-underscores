/**
 * The Search admin app. One bundle for every Search screen: WordPress's
 * menu does the routing, and this mounts the screen whose root the page
 * printed.
 */
import { createRoot } from '@wordpress/element';

import RulesApp from './rules/RulesApp';
import SettingsApp from './settings/SettingsApp';
import './shared/style.scss';

const screens = {
	'lwtv-meili-rules-root': RulesApp,
	'lwtv-meili-settings-root': SettingsApp,
};

for ( const [ id, Screen ] of Object.entries( screens ) ) {
	const root = document.getElementById( id );
	if ( root ) {
		createRoot( root ).render( <Screen /> );
	}
}
