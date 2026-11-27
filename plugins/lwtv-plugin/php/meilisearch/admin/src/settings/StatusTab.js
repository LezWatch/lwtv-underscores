/**
 * Status: a connection rail (server, keys, search) beside one row per index,
 * with each index's share of the documents and its background jobs.
 *
 * Design: docs/superpowers/specs/2026-11-27-meilisearch-status-tab-design.md
 */
import apiFetch from '@wordpress/api-fetch';
import { Button, Notice, Spinner } from '@wordpress/components';
import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';

import { KIND, isRunning, jobText } from '../shared/jobText';
import usePoll from '../shared/usePoll';
import { POLL_MAX_MS, POLL_MS, REST } from './config';

export default function StatusTab( { active, say } ) {
	const [ status, setStatus ] = useState( null );
	const [ error, setError ] = useState( '' );
	const [ busy, setBusy ] = useState( false );
	const [ refreshing, setRefreshing ] = useState( false );
	const [ notice, setNotice ] = useState( null );
	const previous = useRef( {} );
	// The row last focused inside the list, so focus can stay in that row
	// when its focused button goes away (Cancel, when the job stops).
	const focusedRow = useRef( null );
	const list = useRef( null );

	const announceChanges = useCallback(
		( next ) => {
			for ( const row of next?.indexes || [] ) {
				const before = previous.current[ row.type ];
				if (
					isRunning( before ) &&
					row.job &&
					! isRunning( row.job )
				) {
					say(
						sprintf(
							/* translators: 1: index name, 2: what happened to the job. */
							__( '%1$s: %2$s.', 'lwtv-underscores' ),
							row.label,
							jobText( row.job )
						)
					);
				}
				previous.current[ row.type ] = row.job;
			}
		},
		[ say ]
	);

	const load = useCallback(
		() => apiFetch( { path: `/${ REST }/status` } ),
		[]
	);

	const running = !! status?.indexes?.some( ( row ) => isRunning( row.job ) );
	const poll = usePoll( {
		active: active && running,
		request: load,
		onResult: ( next ) => {
			announceChanges( next );
			setStatus( next );
		},
		onTimeout: () =>
			say( __( 'Still running; refresh to check.', 'lwtv-underscores' ) ),
		intervalMs: POLL_MS,
		maxMs: POLL_MAX_MS,
	} );
	const { invalidate, restart } = poll;

	// Resolves true when the status loaded, false when it didn't.
	const refresh = useCallback( () => {
		invalidate();
		setError( '' );
		return load()
			.then( ( next ) => {
				announceChanges( next );
				setStatus( next );
				restart();
				return true;
			} )
			.catch( ( e ) => {
				setError(
					e?.message ||
						__( 'Could not load the status.', 'lwtv-underscores' )
				);
				return false;
			} );
	}, [ invalidate, restart, load, announceChanges ] );

	// The Refresh status button: busy while it runs, then says how it went.
	const refreshNow = () => {
		setRefreshing( true );
		refresh()
			.then( ( ok ) =>
				say(
					ok
						? __( 'Status refreshed.', 'lwtv-underscores' )
						: __( 'Could not load the status.', 'lwtv-underscores' )
				)
			)
			.finally( () => setRefreshing( false ) );
	};

	// If the focused button disappeared, keep focus in its row.
	useEffect( () => {
		const type = focusedRow.current;
		const doc = list.current?.ownerDocument;
		if ( ! type || ! doc || doc.activeElement !== doc.body ) {
			return;
		}
		const row = doc.getElementById( `lwtv-settings-row-${ type }` );
		const target =
			row?.querySelector( 'button' ) || row?.querySelector( 'h3' );
		target?.focus();
	}, [ status ] );

	// Load when the tab is first shown.
	useEffect( () => {
		if ( active && ! status ) {
			refresh();
		}
	}, [ active, status, refresh ] );

	const act = ( row, kind, method ) => {
		setBusy( true );
		setNotice( null );
		const request =
			'DELETE' === method
				? apiFetch( {
						path: `/${ REST }/jobs/${ row.type }`,
						method: 'DELETE',
					} )
				: apiFetch( {
						path: `/${ REST }/jobs`,
						method: 'POST',
						data: { type: row.type, kind },
					} );
		request
			.then( ( response ) => {
				// Remember the job as running, so a job that finishes before
				// the next refresh still gets its "done" announcement.
				if ( 'DELETE' !== method && response?.job ) {
					previous.current[ row.type ] = response.job;
				}
				const message =
					'DELETE' === method
						? sprintf(
								/* translators: %s: index name. */
								__(
									'Cancelled the job on %s.',
									'lwtv-underscores'
								),
								row.label
							)
						: sprintf(
								/* translators: 1: job name, 2: index name. */
								__(
									'%1$s started on %2$s.',
									'lwtv-underscores'
								),
								KIND[ kind ],
								row.label
							);
				say( message );
				return refresh();
			} )
			.catch( ( e ) => {
				const message =
					e?.message ||
					__( 'Could not start the job.', 'lwtv-underscores' );
				setNotice( message );
				say( message );
			} )
			.finally( () => setBusy( false ) );
	};

	if ( error && ! status ) {
		return (
			<Notice status="error" isDismissible={ false }>
				{ error }
			</Notice>
		);
	}
	if ( ! status ) {
		return <Spinner />;
	}

	const total = status.indexes.reduce(
		( sum, row ) => sum + ( row.documents > 0 ? row.documents : 0 ),
		0
	);
	// With no count from any index (server down), a total of 0 would mislead.
	const counted = status.indexes.some( ( row ) => null !== row.documents );

	return (
		<div className="lwtv-settings__status">
			{ notice && (
				<Notice status="error" onRemove={ () => setNotice( null ) }>
					{ notice }
				</Notice>
			) }
			{ error && (
				<Notice status="error" onRemove={ () => setError( '' ) }>
					{ error }
				</Notice>
			) }
			{ ! status.server.ok && (
				<Notice status="error" isDismissible={ false }>
					{ sprintf(
						/* translators: %s: error from the server. */
						__(
							'The search server can’t be reached: %s',
							'lwtv-underscores'
						),
						status.server.message ||
							__( 'no answer', 'lwtv-underscores' )
					) }
				</Notice>
			) }
			{ ! status.action_scheduler && (
				<Notice status="warning" isDismissible={ false }>
					{ __(
						'Action Scheduler isn’t available, so Set up and Reindex are off. Use wp lwtv meili reindex.',
						'lwtv-underscores'
					) }
				</Notice>
			) }

			<div className="lwtv-settings__cols">
				<Rail
					status={ status }
					onRefresh={ refreshNow }
					refreshing={ refreshing }
					busy={ busy }
				/>

				<section
					className="lwtv-settings__card lwtv-settings__indexes"
					aria-labelledby="lwtv-settings-indexes-title"
				>
					<div className="lwtv-settings__card-head">
						<h2 id="lwtv-settings-indexes-title">
							{ __( 'Indexes', 'lwtv-underscores' ) }
						</h2>
						<p className="lwtv-settings__total">
							{ counted
								? sprintf(
										/* translators: %s: number of documents. */
										_n(
											'%s document · bar shows share of total',
											'%s documents · bar shows share of total',
											total,
											'lwtv-underscores'
										),
										total.toLocaleString()
									)
								: __(
										'Document counts unavailable',
										'lwtv-underscores'
									) }
						</p>
					</div>
					<ul className="lwtv-settings__index-list" ref={ list }>
						{ status.indexes.map( ( row ) => (
							<IndexRow
								key={ row.type }
								row={ row }
								total={ total }
								busy={ busy }
								canSchedule={ !! status.action_scheduler }
								hasIndexKey={ !! status.keys.index_key }
								onAct={ ( kind, method ) =>
									act( row, kind, method )
								}
								onFocus={ () =>
									( focusedRow.current = row.type )
								}
								onLeave={ () => {
									if ( focusedRow.current === row.type ) {
										focusedRow.current = null;
									}
								} }
							/>
						) ) }
					</ul>
				</section>
			</div>
		</div>
	);
}

/**
 * The connection rail: server, keys, search, and Refresh status.
 *
 * @param {Object}     props            Props.
 * @param {Object}     props.status     The status response.
 * @param {() => void} props.onRefresh  Refresh status.
 * @param {boolean}    props.refreshing Whether a refresh is running.
 * @param {boolean}    props.busy       Whether a job request is running.
 * @return {Element} Rail.
 */
function Rail( { status, onRefresh, refreshing, busy } ) {
	const up = status.server.ok;
	const mode =
		{
			off: __( 'Off', 'lwtv-underscores' ),
			editors: __( 'Editors', 'lwtv-underscores' ),
			everyone: __( 'Everyone', 'lwtv-underscores' ),
		}[ status.switch.mode ] || status.switch.mode;
	const set = ( isSet ) =>
		isSet ? (
			<span className="is-ok">{ __( 'Set', 'lwtv-underscores' ) }</span>
		) : (
			<span className="is-error">
				{ __( 'Not set', 'lwtv-underscores' ) }
			</span>
		);
	const differs = status.keys.search_key_differs;
	// With no search key there's nothing to compare.
	const hasSearchKey = !! status.keys.search_key;

	return (
		<section
			className="lwtv-settings__card lwtv-settings__rail"
			aria-labelledby="lwtv-settings-server-title"
		>
			<div className="lwtv-settings__card-head">
				<h2 id="lwtv-settings-server-title">
					<span
						className={ `lwtv-settings__dot ${
							up ? 'is-up' : 'is-down'
						}` }
						aria-hidden="true"
					/>
					{ up
						? __( 'Server available', 'lwtv-underscores' )
						: __( 'Server unavailable', 'lwtv-underscores' ) }
				</h2>
			</div>
			<dl className="lwtv-settings__facts-list">
				<div>
					<dt>{ __( 'Host', 'lwtv-underscores' ) }</dt>
					<dd>
						<span className="lwtv-settings__value">
							{ status.host }
						</span>
						<Source source={ status.host_source } />
					</dd>
				</div>
				<div>
					<dt>{ __( 'Prefix', 'lwtv-underscores' ) }</dt>
					<dd>
						{ sprintf(
							/* translators: %s: index prefix. */
							__( '“%s”', 'lwtv-underscores' ),
							status.prefix
						) }
						<Source source={ status.prefix_source } />
					</dd>
				</div>
				<div>
					<dt>{ __( 'Indexing key', 'lwtv-underscores' ) }</dt>
					<dd>{ set( status.keys.index_key ) }</dd>
				</div>
				<div>
					<dt>{ __( 'Search key', 'lwtv-underscores' ) }</dt>
					<dd>{ set( status.keys.search_key ) }</dd>
				</div>
				<div>
					<dt>{ __( 'Keys differ', 'lwtv-underscores' ) }</dt>
					<dd>
						{ ! hasSearchKey && '—' }
						{ hasSearchKey && differs && (
							<span className="is-ok">
								{ __( 'Yes', 'lwtv-underscores' ) }
							</span>
						) }
						{ hasSearchKey && ! differs && (
							<span className="is-warn">
								{ __( 'No', 'lwtv-underscores' ) }
							</span>
						) }
					</dd>
					{ hasSearchKey && ! differs && (
						<dd className="lwtv-settings__note">
							{ __(
								'The search key reaches visitors’ browsers, so it must not be the indexing key.',
								'lwtv-underscores'
							) }
						</dd>
					) }
				</div>
				<div>
					<dt>{ __( 'Search', 'lwtv-underscores' ) }</dt>
					<dd>
						{ mode }
						<Source source={ status.switch.source } />
					</dd>
				</div>
				<div>
					<dt>{ __( 'Timeout', 'lwtv-underscores' ) }</dt>
					<dd className="lwtv-settings__num">
						{ sprintf(
							/* translators: %d: timeout in milliseconds. */
							__( '%d ms', 'lwtv-underscores' ),
							status.timeout_ms
						) }
					</dd>
				</div>
			</dl>
			<div className="lwtv-settings__card-foot">
				<Button
					variant="secondary"
					icon="update"
					onClick={ onRefresh }
					isBusy={ refreshing }
					disabled={ busy || refreshing }
					accessibleWhenDisabled
				>
					{ __( 'Refresh status', 'lwtv-underscores' ) }
				</Button>
			</div>
		</section>
	);
}

/**
 * One index: name, count, share bar, facts and actions.
 *
 * @param {Object}                                  props             Props.
 * @param {Object}                                  props.row         The index row from the status response.
 * @param {number}                                  props.total       Documents across all indexes.
 * @param {boolean}                                 props.busy        Whether a job request is running.
 * @param {boolean}                                 props.canSchedule Whether Action Scheduler is there.
 * @param {boolean}                                 props.hasIndexKey Whether the indexing key is set.
 * @param {(kind: ?string, method: string) => void} props.onAct       Start ( kind, 'POST' ) or cancel ( null, 'DELETE' ) a job.
 * @param {() => void}                              props.onFocus     Remember this row as focused.
 * @param {() => void}                              props.onLeave     Forget it, once focus has left the row.
 * @return {Element} Row.
 */
function IndexRow( {
	row,
	total,
	busy,
	canSchedule,
	hasIndexKey,
	onAct,
	onFocus,
	onLeave,
} ) {
	const exists = 'exists' === row.state;
	const jobRunning = isRunning( row.job );
	const drifted =
		row.settings_drift.length > 0 || false === row.synonyms_match;
	const off = busy || jobRunning;
	// Set up and Reindex need the scheduler and the indexing key.
	const offJob = off || ! canSchedule || ! hasIndexKey;
	const label = ( action ) =>
		sprintf(
			/* translators: 1: action, 2: index name. */
			__( '%1$s %2$s', 'lwtv-underscores' ),
			action,
			row.label
		);
	const width = share( row.documents, total );

	const facts = [];
	if ( exists ) {
		facts.push( [ 'is-ok', __( 'Set up', 'lwtv-underscores' ) ] );
	} else if ( 'missing' === row.state ) {
		facts.push( [ 'is-warn', __( 'Not set up', 'lwtv-underscores' ) ] );
	} else {
		facts.push( [ '', __( 'Unknown', 'lwtv-underscores' ) ] );
	}
	if ( row.indexing ) {
		facts.push( [ '', __( 'Indexing…', 'lwtv-underscores' ) ] );
	}
	if ( exists ) {
		facts.push(
			row.settings_drift.length
				? [
						'is-warn',
						sprintf(
							/* translators: %s: setting names. */
							__( 'Settings differ: %s', 'lwtv-underscores' ),
							row.settings_drift.join( ', ' )
						),
					]
				: [ '', __( 'Settings match', 'lwtv-underscores' ) ]
		);
		if ( true === row.synonyms_match ) {
			facts.push( [ '', __( 'Synonyms match', 'lwtv-underscores' ) ] );
		} else if ( false === row.synonyms_match ) {
			facts.push( [
				'is-warn',
				__( 'Synonyms differ', 'lwtv-underscores' ),
			] );
		}
	}

	return (
		<li
			id={ `lwtv-settings-row-${ row.type }` }
			className="lwtv-settings__index"
			onFocus={ onFocus }
			onBlur={ ( event ) => {
				const next = event.relatedTarget;
				// Focus moved within the row: still here.
				if ( next && event.currentTarget.contains( next ) ) {
					return;
				}
				// Focus went nowhere because its button was removed (Cancel,
				// when the job stops): keep the row, so focus can come back.
				if ( ! next && ! event.target.isConnected ) {
					return;
				}
				onLeave();
			} }
		>
			<div className="lwtv-settings__index-head">
				<h3 tabIndex={ -1 }>{ row.label }</h3>
				<code>{ row.uid }</code>
				<span className="lwtv-settings__count lwtv-settings__num">
					{ null === row.documents
						? '—'
						: row.documents.toLocaleString() }
				</span>
			</div>
			<div className="lwtv-settings__bar" aria-hidden="true">
				{ width > 0 && <span style={ { width: `${ width }%` } } /> }
			</div>
			<div className="lwtv-settings__index-foot">
				<ul className="lwtv-settings__facts">
					{ facts.map( ( [ className, text ] ) => (
						<li key={ text } className={ className }>
							{ text }
						</li>
					) ) }
					{ /* Busy marks only the job text: on the whole row, a long
					job would keep screen readers from the row and its Cancel. */ }
					<li
						className={
							'failed' === row.job?.state ? 'is-error' : ''
						}
						aria-busy={ jobRunning }
					>
						{ jobRunning && <Spinner /> }
						{ row.job
							? jobText( row.job )
							: __( 'No active job', 'lwtv-underscores' ) }
					</li>
				</ul>
				<div className="lwtv-settings__actions">
					{ exists && drifted && (
						<Button
							variant="secondary"
							disabled={ off }
							accessibleWhenDisabled
							aria-label={ label( KIND.apply ) }
							onClick={ () => onAct( 'apply', 'POST' ) }
						>
							{ KIND.apply }
						</Button>
					) }
					{ exists && (
						<Button
							variant="secondary"
							disabled={ offJob }
							accessibleWhenDisabled
							aria-label={ label( KIND.reindex ) }
							onClick={ () => onAct( 'reindex', 'POST' ) }
						>
							{ KIND.reindex }
						</Button>
					) }
					{ 'missing' === row.state && (
						<Button
							variant="primary"
							disabled={ offJob }
							accessibleWhenDisabled
							aria-label={ label( KIND.setup ) }
							onClick={ () => onAct( 'setup', 'POST' ) }
						>
							{ KIND.setup }
						</Button>
					) }
					{ jobRunning && (
						<Button
							variant="tertiary"
							isDestructive
							disabled={ busy }
							accessibleWhenDisabled
							aria-label={ label(
								__( 'Cancel the job on', 'lwtv-underscores' )
							) }
							onClick={ () => onAct( null, 'DELETE' ) }
						>
							{ __( 'Cancel', 'lwtv-underscores' ) }
						</Button>
					) }
				</div>
			</div>
		</li>
	);
}

/**
 * Where a value comes from, as a grey second line.
 *
 * @param {Object} props        Props.
 * @param {string} props.source constant, saved, option or default.
 * @return {Element|null} Line, or null when the source is unknown.
 */
function Source( { source } ) {
	const text = sourceText( source );
	return text ? (
		<span className="lwtv-settings__source">{ text }</span>
	) : null;
}

/**
 * Where a value comes from, in words.
 *
 * @param {string} source constant, saved, option or default.
 * @return {string} The words; '' when unknown.
 */
export function sourceText( source ) {
	return (
		{
			constant: __( 'wp-config.php', 'lwtv-underscores' ),
			saved: __( 'set on this page', 'lwtv-underscores' ),
			option: __( 'set on this page', 'lwtv-underscores' ),
			default: __( 'default', 'lwtv-underscores' ),
		}[ source ] || ''
	);
}

/**
 * An index's share of all documents, as a bar width.
 *
 * @param {?number} documents Documents in this index; null when unknown.
 * @param {number}  total     Documents across all indexes.
 * @return {number} 0 to 100; at least 2 for any non-empty index, so it shows.
 */
export function share( documents, total ) {
	if ( ! ( documents > 0 ) || ! ( total > 0 ) ) {
		return 0;
	}
	return Math.max( 2, ( documents / total ) * 100 );
}
