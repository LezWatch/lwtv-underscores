/**
 * The Search Settings screen: General (switch, fallback) and Status.
 */
import apiFetch from '@wordpress/api-fetch';
import { Notice, Spinner } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import Tabs from '../shared/Tabs';
import useAnnounce, { LiveRegion } from '../shared/useAnnounce';
import General from './General';
import StatusTab from './StatusTab';
import { REST } from './config';
import './style.scss';

export default function SettingsApp() {
	const [ data, setData ] = useState( null );
	const [ loadError, setLoadError ] = useState( '' );
	const [ tab, setTab ] = useState( 'general' );
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
				onSelect={ setTab }
				tabs={ [
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
						name: 'status',
						title: __( 'Status', 'lwtv-underscores' ),
						content: (
							<StatusTab
								active={ 'status' === tab }
								say={ say }
							/>
						),
					},
				] }
			/>
		</div>
	);
}
