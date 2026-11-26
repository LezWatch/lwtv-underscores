/**
 * The Search Rules app: edit, save and apply the synonyms list.
 */
import apiFetch from '@wordpress/api-fetch';
import { Button, Card, CardBody, Notice, Spinner } from '@wordpress/components';
import { useEffect, useMemo, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';

import ImportExport from './ImportExport';
import Tabs from '../shared/Tabs';
import useAnnounce, { LiveRegion } from '../shared/useAnnounce';
import usePoll from '../shared/usePoll';
import OneWay from './OneWay';
import Status from './Status';
import TestBox from './TestBox';
import TwoWay from './TwoWay';
import { newKey, summarize, toRows, toSynonyms } from './rules';
import './style.scss';

const config = window.lwtvMeiliRules || {};
const REST = config.restPath || 'lwtv/v1/meili-rules';
const POLL_MS = Number( config.pollMs ) || 2000;
const POLL_MAX_MS = Number( config.pollMaxMs ) || 60000;
const EMPTY = { two_way: [], one_way: [] };

export default function RulesApp() {
	const [ loaded, setLoaded ] = useState( false );
	const [ loadError, setLoadError ] = useState( '' );
	const [ twoWay, setTwoWay ] = useState( [] );
	const [ oneWay, setOneWay ] = useState( [] );
	const [ saved, setSaved ] = useState( EMPTY );
	const [ errors, setErrors ] = useState( [] );
	const [ warnings, setWarnings ] = useState( [] );
	const [ applied, setApplied ] = useState( null );
	const [ indexes, setIndexes ] = useState( [] );
	const [ starter, setStarter ] = useState( null );
	const [ busy, setBusy ] = useState( false );
	const [ notice, setNotice ] = useState( null );
	const [ announcement, say ] = useAnnounce();
	const [ focusRequest, setFocusRequest ] = useState( null );
	const [ tab, setTab ] = useState( 'two_way' );

	const labels = useMemo(
		() => Object.fromEntries( indexes.map( ( i ) => [ i.type, i.label ] ) ),
		[ indexes ]
	);
	const dirty =
		JSON.stringify( toSynonyms( twoWay, oneWay ) ) !==
		JSON.stringify( saved );

	const showRules = ( synonyms ) => {
		const rows = toRows( synonyms );
		setTwoWay( rows.twoWay );
		setOneWay( rows.oneWay );
	};

	// Poll while any index is still applying, for up to POLL_MAX_MS.
	// Saves and applies invalidate() first, so an older answer is dropped.
	const stillApplying = () =>
		say( __( 'Still applying; reload to check.', 'lwtv-underscores' ) );
	const poll = usePoll( {
		active: !! applied && summarize( applied ).applying,
		request: () => apiFetch( { path: `/${ REST }` } ),
		onResult: ( data ) => {
			const next = data.applied || null;
			setApplied( next );
			const s = summarize( next );
			if ( ! s.applying ) {
				say(
					sprintf(
						/* translators: 1: indexes that took the list, 2: indexes that exist here. */
						__(
							'Applied to %1$d of %2$d set-up indexes.',
							'lwtv-underscores'
						),
						s.ok,
						s.setUp
					)
				);
			}
		},
		onTimeout: stillApplying,
		intervalMs: POLL_MS,
		maxMs: POLL_MAX_MS,
	} );
	const timedOut = poll.timedOut;
	const startPolling = poll.restart;

	// Move focus once the rows a change produced have rendered.
	useEffect( () => {
		if ( ! focusRequest ) {
			return;
		}
		setFocusRequest( null );
		const { kind, row } = focusRequest;
		const field = ( i ) =>
			document.querySelector( `#lwtv-rules-${ kind }-${ i } input` );
		const el =
			kind === 'status'
				? document.getElementById( 'lwtv-rules-status' )
				: field( row ) ||
					field( row - 1 ) ||
					document.getElementById( `lwtv-rules-add-${ kind }` );
		if ( el ) {
			el.focus();
		}
	}, [ focusRequest, twoWay, oneWay ] );

	// First load.
	useEffect( () => {
		apiFetch( { path: `/${ REST }` } )
			.then( ( data ) => {
				const synonyms = data.rules?.synonyms || EMPTY;
				showRules( synonyms );
				setSaved( synonyms );
				setWarnings( data.warnings || [] );
				setApplied( data.applied || null );
				setIndexes( data.indexes || [] );
				setStarter( data.starter || null );
				startPolling();
				setLoaded( true );
			} )
			.catch( ( e ) =>
				setLoadError(
					e?.message ||
						__(
							'Could not load the search rules.',
							'lwtv-underscores'
						)
				)
			);
	}, [ startPolling ] );

	// The browser's "leave site?" prompt while there are unsaved changes.
	useEffect( () => {
		if ( ! dirty ) {
			return;
		}
		const warn = ( event ) => {
			event.preventDefault();
			event.returnValue = '';
		};
		window.addEventListener( 'beforeunload', warn );
		return () => window.removeEventListener( 'beforeunload', warn );
	}, [ dirty ] );

	// Show the row's tab, then focus its first field once it has rendered.
	const focusRow = ( m ) => {
		setTab( m.kind );
		setFocusRequest( { kind: m.kind, row: m.row } );
	};

	const save = ( synonyms ) => {
		poll.invalidate();
		setBusy( true );
		setNotice( null );
		apiFetch( { path: `/${ REST }`, method: 'POST', data: { synonyms } } )
			.then( ( data ) => {
				const next = data.rules?.synonyms || EMPTY;
				showRules( next );
				setSaved( next );
				setErrors( [] );
				setWarnings( data.warnings || [] );
				setApplied( data.applied || null );
				setStarter( null );
				startPolling();
				const count = ( data.warnings || [] ).length;
				const message = count
					? sprintf(
							/* translators: %d: number of warnings. */
							_n(
								'Saved, with %d warning.',
								'Saved, with %d warnings.',
								count,
								'lwtv-underscores'
							),
							count
						)
					: __(
							'Saved. Applying to the search indexes…',
							'lwtv-underscores'
						);
				setNotice( { status: count ? 'warning' : 'success', message } );
				say( message );
			} )
			.catch( ( e ) => {
				if ( e?.code === 'lwtv_meili_rules_invalid' ) {
					const found = e.data?.errors || [];
					setErrors( found );
					setWarnings( e.data?.warnings || [] );
					const message = sprintf(
						/* translators: %d: number of errors. */
						_n(
							'Not saved: %d error to fix.',
							'Not saved: %d errors to fix.',
							found.length,
							'lwtv-underscores'
						),
						found.length
					);
					setNotice( { status: 'error', message } );
					say( message );
					return;
				}
				const message =
					e?.message ||
					__(
						'Could not save the search rules.',
						'lwtv-underscores'
					);
				setNotice( { status: 'error', message } );
				say( message );
			} )
			.finally( () => setBusy( false ) );
	};

	const applyAgain = () => {
		poll.invalidate();
		setBusy( true );
		apiFetch( { path: `/${ REST }/apply`, method: 'POST' } )
			.then( ( data ) => {
				setApplied( data.applied || null );
				startPolling();
				// The Apply again button goes away while applying.
				setFocusRequest( { kind: 'status' } );
				say( __( 'Applying again…', 'lwtv-underscores' ) );
			} )
			.catch( ( e ) => {
				const message =
					e?.message ||
					__(
						'Could not apply the search rules.',
						'lwtv-underscores'
					);
				setNotice( { status: 'error', message } );
				say( message );
			} )
			.finally( () => setBusy( false ) );
	};

	const importList = ( synonyms ) => {
		// Show the imported rows first, so any errors land on them.
		const rows = toRows( synonyms );
		setTwoWay( rows.twoWay );
		setOneWay( rows.oneWay );
		save( toSynonyms( rows.twoWay, rows.oneWay ) );
	};

	// Removing a row renumbers the rows after it, so old messages no longer line up.
	const clearMessages = () => {
		setErrors( [] );
		setWarnings( [] );
	};

	if ( loadError ) {
		return (
			<Notice status="error" isDismissible={ false }>
				{ loadError }
			</Notice>
		);
	}

	if ( ! loaded ) {
		return <Spinner />;
	}

	const saveButton = (
		<>
			<Button
				variant="primary"
				onClick={ () => save( toSynonyms( twoWay, oneWay ) ) }
				disabled={ busy }
				isBusy={ busy }
				accessibleWhenDisabled
			>
				{ __( 'Save and apply', 'lwtv-underscores' ) }
			</Button>
			{ dirty && (
				<span className="lwtv-rules__dirty">
					{ __( 'Unsaved changes', 'lwtv-underscores' ) }
				</span>
			) }
		</>
	);

	// A tab's name says how many rows it has, and how many errors.
	const tabTitle = ( title, count, kind ) => {
		const problems = errors.filter(
			( m ) => m.kind === kind && m.row >= 0
		).length;
		return (
			<>
				{ title }{ ' ' }
				<span className="lwtv-rules-tabs__count">{ count }</span>
				{ problems > 0 && (
					<span className="screen-reader-text">, </span>
				) }
				{ problems > 0 && (
					<span className="lwtv-rules-tabs__errors">
						{ sprintf(
							/* translators: %d: number of errors. */
							_n(
								'%d error',
								'%d errors',
								problems,
								'lwtv-underscores'
							),
							problems
						) }
					</span>
				) }
			</>
		);
	};

	const listed = [ ...errors, ...warnings ];
	const firstProblem =
		errors.find( ( m ) => m.row >= 0 ) ||
		warnings.find( ( m ) => m.row >= 0 );
	const wholeList = errors.filter( ( m ) => m.row < 0 );

	return (
		<div className="lwtv-rules">
			<LiveRegion text={ announcement } />

			{ notice && (
				<Notice
					status={ notice.status }
					onRemove={ () => setNotice( null ) }
					actions={
						firstProblem && listed.length
							? [
									{
										label: errors.length
											? __(
													'Go to the first error',
													'lwtv-underscores'
												)
											: __(
													'Go to the first warning',
													'lwtv-underscores'
												),
										onClick: () => focusRow( firstProblem ),
									},
								]
							: []
					}
				>
					{ notice.message }
					{ wholeList.length > 0 && (
						<ul>
							{ wholeList.map( ( m, i ) => (
								<li key={ i }>{ m.message }</li>
							) ) }
						</ul>
					) }
				</Notice>
			) }

			{ starter && twoWay.length === 0 && oneWay.length === 0 && (
				<Notice
					status="info"
					isDismissible={ false }
					actions={ [
						{
							label: __(
								'Load starter list',
								'lwtv-underscores'
							),
							onClick: () => {
								showRules( starter );
								setTab( 'two_way' );
								setFocusRequest( { kind: 'two_way', row: 0 } );
							},
							variant: 'primary',
						},
					] }
				>
					{ __(
						'The list is empty. Load the starter list to review it, then save.',
						'lwtv-underscores'
					) }
				</Notice>
			) }

			<Card className="lwtv-rules__toolbar">
				<CardBody>
					<div className="lwtv-rules__save">
						{ saveButton }
						<Status
							applied={ applied }
							labels={ labels }
							timedOut={ timedOut }
							busy={ busy }
							onApplyAgain={ applyAgain }
						/>
					</div>
					<div className="lwtv-rules__tools">
						<TestBox restPath={ REST } />
						<ImportExport
							saved={ saved }
							busy={ busy }
							onImport={ importList }
						/>
					</div>
				</CardBody>
			</Card>

			<h2>{ __( 'Synonyms', 'lwtv-underscores' ) }</h2>
			<Tabs
				id="lwtv-rules"
				label={ __( 'Kinds of synonym', 'lwtv-underscores' ) }
				selected={ tab }
				onSelect={ setTab }
				tabs={ [
					{
						name: 'two_way',
						title: tabTitle(
							__( 'Two-way groups', 'lwtv-underscores' ),
							twoWay.length,
							'two_way'
						),
						content: (
							<TwoWay
								rows={ twoWay }
								errors={ errors }
								warnings={ warnings }
								onChange={ ( i, row ) =>
									setTwoWay(
										twoWay.map( ( r, j ) =>
											j === i ? row : r
										)
									)
								}
								onAdd={ () =>
									setTwoWay( [
										...twoWay,
										{ key: newKey(), words: [] },
									] )
								}
								onRemove={ ( i ) => {
									setTwoWay(
										twoWay.filter( ( r, j ) => j !== i )
									);
									clearMessages();
									setFocusRequest( {
										kind: 'two_way',
										row: i,
									} );
								} }
							/>
						),
					},
					{
						name: 'one_way',
						title: tabTitle(
							__( 'One-way', 'lwtv-underscores' ),
							oneWay.length,
							'one_way'
						),
						content: (
							<OneWay
								rows={ oneWay }
								errors={ errors }
								warnings={ warnings }
								onChange={ ( i, row ) =>
									setOneWay(
										oneWay.map( ( r, j ) =>
											j === i ? row : r
										)
									)
								}
								onAdd={ () =>
									setOneWay( [
										...oneWay,
										{ key: newKey(), from: '', to: [] },
									] )
								}
								onRemove={ ( i ) => {
									setOneWay(
										oneWay.filter( ( r, j ) => j !== i )
									);
									clearMessages();
									setFocusRequest( {
										kind: 'one_way',
										row: i,
									} );
								} }
							/>
						),
					},
				] }
			/>

			<div className="lwtv-rules__save lwtv-rules__save--bottom">
				{ saveButton }
			</div>
		</div>
	);
}
