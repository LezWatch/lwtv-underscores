/**
 * General: who gets Meilisearch search, and the fallback timeout.
 */
import apiFetch from '@wordpress/api-fetch';
import {
	Button,
	Card,
	CardBody,
	Notice,
	RadioControl,
	TextControl,
} from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

import { REST } from './config';

const CACHE_NOTE = __(
	'Logged-out visitors get cached pages, so a change to or from Everyone (and a timeout change while it’s Everyone) reaches them only as those pages expire. Purge the page cache to apply it now.',
	'lwtv-underscores'
);

export default function General( { data, onSaved, say } ) {
	const locked = data.constant.defined;
	const [ choice, setChoice ] = useState( data.settings.switch );
	const [ timeout, setTimeoutValue ] = useState(
		String( data.settings.timeout_ms )
	);
	const [ errors, setErrors ] = useState( {} );
	const [ notice, setNotice ] = useState( null );
	const [ busy, setBusy ] = useState( false );

	const shown = locked ? data.effective.mode : choice;
	const fallback =
		'searchwp' === data.fallback
			? __( 'SearchWP', 'lwtv-underscores' )
			: __( 'WordPress search', 'lwtv-underscores' );

	const save = () => {
		const body = {};
		if ( ! locked && choice !== data.settings.switch ) {
			body.switch = choice;
		}
		if ( timeout !== String( data.settings.timeout_ms ) ) {
			// Send what was typed; the server refuses anything that isn't a whole number in range.
			body.timeout_ms = /^\d+$/.test( timeout )
				? Number( timeout )
				: timeout;
		}
		if ( ! Object.keys( body ).length ) {
			setNotice( {
				status: 'info',
				message: __( 'Nothing to save.', 'lwtv-underscores' ),
			} );
			return;
		}

		const before = data;
		setBusy( true );
		setErrors( {} );
		setNotice( null );
		apiFetch( { path: `/${ REST }`, method: 'POST', data: body } )
			.then( ( next ) => {
				onSaved( next );
				setChoice( next.settings.switch );
				setTimeoutValue( String( next.settings.timeout_ms ) );
				const everyone =
					'everyone' === before.effective.mode ||
					'everyone' === next.effective.mode;
				const message = everyone
					? `${ __( 'Saved.', 'lwtv-underscores' ) } ${ CACHE_NOTE }`
					: __( 'Saved.', 'lwtv-underscores' );
				setNotice( {
					status: everyone ? 'warning' : 'success',
					message,
				} );
				say( __( 'Settings saved.', 'lwtv-underscores' ) );
			} )
			.catch( ( e ) => {
				setErrors( e?.data?.errors || {} );
				const message =
					e?.message ||
					__( 'Could not save the settings.', 'lwtv-underscores' );
				setNotice( { status: 'error', message } );
				say( message );
			} )
			.finally( () => setBusy( false ) );
	};

	return (
		<Card className="lwtv-settings__card">
			<CardBody>
				{ notice && (
					<Notice
						status={ notice.status }
						onRemove={ () => setNotice( null ) }
					>
						{ notice.message }
					</Notice>
				) }

				{ data.blocked && (
					<Notice status="warning" isDismissible={ false }>
						{ 'unconfigured' === data.blocked
							? __(
									'Search is off on this site whatever this says: the Meilisearch module isn’t configured in wp-config.php.',
									'lwtv-underscores'
								)
							: __(
									'Search is off on this site whatever this says: the search key is the same as the indexing key.',
									'lwtv-underscores'
								) }
					</Notice>
				) }

				<div className="lwtv-settings__row">
					<h2>
						{ __(
							'Who gets Meilisearch search',
							'lwtv-underscores'
						) }
					</h2>
					<div className="lwtv-settings__field">
						<RadioControl
							label={ __(
								'Who gets Meilisearch search',
								'lwtv-underscores'
							) }
							hideLabelFromVision
							selected={ shown }
							onChange={ setChoice }
							disabled={ locked || busy }
							options={ [
								{
									label: __( 'Off', 'lwtv-underscores' ),
									value: 'off',
									description: __(
										'Everyone gets the old search.',
										'lwtv-underscores'
									),
								},
								{
									label: __( 'Editors', 'lwtv-underscores' ),
									value: 'editors',
									description: __(
										'Users who can edit posts get Meilisearch; visitors don’t.',
										'lwtv-underscores'
									),
								},
								{
									label: __( 'Everyone', 'lwtv-underscores' ),
									value: 'everyone',
									description: __(
										'Everyone, including visitors and cached pages.',
										'lwtv-underscores'
									),
								},
							] }
						/>
						{ locked && (
							<p className="lwtv-settings__locked">
								{ sprintf(
									/* translators: %s: the constant's value. */
									__(
										'Set in wp-config.php (LWTV_MEILI_MODAL is “%s”). Remove that line to manage this here.',
										'lwtv-underscores'
									),
									data.constant.value ?? ''
								) }
								{ ! data.constant.valid &&
									` ${ __(
										'That value isn’t one of off, editors or everyone, so search is off.',
										'lwtv-underscores'
									) }` }
								{ data.constant.valid &&
									data.effective.mode !==
										data.constant.value &&
									` ${ sprintf(
										/* translators: %s: off, editors or everyone. */
										__(
											'Search reads it as “%s”.',
											'lwtv-underscores'
										),
										data.effective.mode
									) }` }
								{ data.settings.switch ===
									data.effective.mode &&
									'off' !== data.effective.mode &&
									` ${ __(
										'It’s also saved here, so removing the line keeps it.',
										'lwtv-underscores'
									) }` }
							</p>
						) }
						{ errors.switch && (
							<p className="lwtv-settings__error">
								{ errors.switch }
							</p>
						) }
						<p className="lwtv-settings__hint">{ CACHE_NOTE }</p>
					</div>
				</div>

				<div className="lwtv-settings__row">
					<h2>{ __( 'Fallback', 'lwtv-underscores' ) }</h2>
					<div className="lwtv-settings__field">
						<TextControl
							type="number"
							label={ __(
								'Timeout (milliseconds)',
								'lwtv-underscores'
							) }
							help={
								errors.timeout_ms ||
								sprintf(
									/* translators: 1: minimum, 2: maximum, 3: step. */
									__(
										'How long search waits for Meilisearch before falling back. From %1$d to %2$d, in steps of %3$d.',
										'lwtv-underscores'
									),
									data.limits.timeout_min,
									data.limits.timeout_max,
									data.limits.timeout_step
								)
							}
							className={ `lwtv-settings__timeout${ errors.timeout_ms ? ' has-error' : '' }` }
							min={ data.limits.timeout_min }
							max={ data.limits.timeout_max }
							step={ data.limits.timeout_step }
							value={ timeout }
							onChange={ setTimeoutValue }
							__next40pxDefaultSize
							__nextHasNoMarginBottom
						/>
						<p>
							{ sprintf(
								/* translators: %s: SearchWP or WordPress search. */
								__(
									'If Meilisearch doesn’t answer in time: %s.',
									'lwtv-underscores'
								),
								fallback
							) }
						</p>
					</div>
				</div>

				<Button
					variant="primary"
					onClick={ save }
					isBusy={ busy }
					disabled={ busy }
					accessibleWhenDisabled
				>
					{ __( 'Save settings', 'lwtv-underscores' ) }
				</Button>
			</CardBody>
		</Card>
	);
}
