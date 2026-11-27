/**
 * The Search Settings screen: Status (opens first), General (connection, switch, fallback), then Typo tolerance.
 * The chosen tab is kept in the URL (&tab=), so a reload or a link opens it.
 */
import apiFetch from '@wordpress/api-fetch';
import { Notice, Spinner } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import Tabs from '../shared/Tabs';
import useAnnounce, { LiveRegion } from '../shared/useAnnounce';
import General from './General';
import StatusTab from './StatusTab';
import TypoTab from './TypoTab';
import { REST } from './config';
import './style.scss';

const TABS = [ 'status', 'general', 'typo' ];

/**
 * The tab named in the URL, or Status.
 *
 * @return {string} Tab name.
 */
function tabFromUrl() {
	try {
		const tab = new URL( window.location.href ).searchParams.get( 'tab' );
		return TABS.includes( tab ) ? tab : 'status';
	} catch {
		return 'status';
	}
}

/**
 * Put the tab in the URL without a reload.
 *
 * @param {string} tab Tab name.
 */
function tabToUrl( tab ) {
	try {
		const url = new URL( window.location.href );
		url.searchParams.set( 'tab', tab );
		window.history.replaceState( window.history.state, '', url );
	} catch {}
}

export default function SettingsApp() {
	const [ data, setData ] = useState( null );
	const [ loadError, setLoadError ] = useState( '' );
	const [ tab, setTab ] = useState( tabFromUrl );
	const choose = ( name ) => {
		setTab( name );
		tabToUrl( name );
	};
	const [ announcement, say ] = useAnnounce();

	useEffect( () => {
		apiFetch( { path: `/${ REST }` } )
			.then( setData )
			.catch( ( e ) =>
				setLoadError(
					e?.message ||
						__(
							'Could not load the search settings.',
							'lwtv-underscores'
						)
				)
			);
	}, [] );

	if ( loadError ) {
		return (
			<Notice status="error" isDismissible={ false }>
				{ loadError }
			</Notice>
		);
	}

	if ( ! data ) {
		return <Spinner />;
	}

	return (
		<div className="lwtv-settings">
			<LiveRegion text={ announcement } />
			<Tabs
				id="lwtv-settings"
				label={ __( 'Search settings', 'lwtv-underscores' ) }
				selected={ tab }
				onSelect={ choose }
				tabs={ [
					{
						name: 'status',
						title: __( 'Status', 'lwtv-underscores' ),
						content: (
							<StatusTab
								active={ 'status' === tab }
								say={ say }
							/>
						),
					},
					{
						name: 'general',
						title: __( 'General', 'lwtv-underscores' ),
						content: (
							<General
								data={ data }
								onSaved={ setData }
								say={ say }
							/>
						),
					},
					{
						name: 'typo',
						title: __( 'Typo tolerance', 'lwtv-underscores' ),
						content: (
							<TypoTab active={ 'typo' === tab } say={ say } />
						),
					},
				] }
			/>
		</div>
	);
}
