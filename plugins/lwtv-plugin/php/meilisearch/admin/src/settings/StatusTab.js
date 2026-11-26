/**
 * Status: the server, keys and each index, with background jobs.
 */
import apiFetch from '@wordpress/api-fetch';
import { Button, Notice, Spinner } from '@wordpress/components';
import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

import usePoll from '../shared/usePoll';
import { POLL_MAX_MS, POLL_MS, REST } from './config';

const KIND = {
	apply: __( 'Apply settings', 'lwtv-underscores' ),
	setup: __( 'Set up', 'lwtv-underscores' ),
	reindex: __( 'Reindex', 'lwtv-underscores' ),
};

const STEP = {
	check: __( 'checking the index', 'lwtv-underscores' ),
	create: __( 'creating the index', 'lwtv-underscores' ),
	settings: __( 'sending settings', 'lwtv-underscores' ),
	prune: __( 'removing stale documents', 'lwtv-underscores' ),
	wait: __( 'finishing', 'lwtv-underscores' ),
};

const isRunning = ( job ) => 'running' === job?.state;

/**
 * What a job is doing, in words.
 *
 * @param {Object|null} job Job.
 * @return {string} Text.
 */
function jobText( job ) {
	if ( ! job ) {
		return '—';
	}
	const kind = KIND[ job.kind ] || job.kind;
	if ( isRunning( job ) ) {
		const step =
			'batch' === job.step
				? sprintf(
						/* translators: 1: documents sent, 2: posts in total. */
						__( '%1$d of %2$d sent', 'lwtv-underscores' ),
						job.sent,
						job.total
					)
				: STEP[ job.step ] || job.step;
		/* translators: 1: job name, 2: what it's doing. */
		return sprintf( __( '%1$s: %2$s…', 'lwtv-underscores' ), kind, step );
	}
	const state = {
		done: __( 'done', 'lwtv-underscores' ),
		failed: __( 'failed', 'lwtv-underscores' ),
		cancelled: __( 'cancelled', 'lwtv-underscores' ),
	}[ job.state ];
	const text = sprintf(
		/* translators: 1: job name, 2: done, failed or cancelled. */
		__( '%1$s %2$s', 'lwtv-underscores' ),
		kind,
		state || job.state
	);
	return job.message ? `${ text }: ${ job.message }` : text;
}

export default function StatusTab( { active, say } ) {
	const [ status, setStatus ] = useState( null );
	const [ error, setError ] = useState( '' );
	const [ busy, setBusy ] = useState( false );
	const [ notice, setNotice ] = useState( null );
	const previous = useRef( {} );
	// The row last focused inside the table, so focus can stay in that row
	// when its focused button goes away (Cancel, when the job stops).
	const focusedRow = useRef( null );
	const table = useRef( null );

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

	const refresh = useCallback( () => {
		invalidate();
		setError( '' );
		return load()
			.then( ( next ) => {
				announceChanges( next );
				setStatus( next );
				restart();
			} )
			.catch( ( e ) =>
				setError(
					e?.message ||
						__( 'Could not load the status.', 'lwtv-underscores' )
				)
			);
	}, [ invalidate, restart, load, announceChanges ] );

	// If the focused button disappeared, keep focus in its row.
	useEffect( () => {
		const type = focusedRow.current;
		const doc = table.current?.ownerDocument;
		if ( ! type || ! doc || doc.activeElement !== doc.body ) {
			return;
		}
		const row = doc.getElementById( `lwtv-settings-row-${ type }` );
		const target =
			row?.querySelector( 'button' ) || row?.querySelector( 'th' );
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

	const yes = __( 'yes', 'lwtv-underscores' );
	const no = __( 'no', 'lwtv-underscores' );
	const stateText = {
		exists: __( 'Set up', 'lwtv-underscores' ),
		missing: __( 'Not set up', 'lwtv-underscores' ),
		unknown: __( 'Unknown', 'lwtv-underscores' ),
	};

	return (
		<div className="lwtv-settings__status">
			{ notice && (
				<Notice status="error" onRemove={ () => setNotice( null ) }>
					{ notice }
				</Notice>
			) }

			<div className="lwtv-settings__summary">
				<p>
					<strong>{ __( 'Server:', 'lwtv-underscores' ) }</strong>{ ' ' }
					{ status.server.ok
						? __( 'available', 'lwtv-underscores' )
						: sprintf(
								/* translators: %s: error from the server. */
								__( 'unreachable (%s)', 'lwtv-underscores' ),
								status.server.message
							) }
					{ ' · ' }
					{ status.host }
					{ ' · ' }
					{ sprintf(
						/* translators: %s: index prefix. */
						__( 'prefix “%s”', 'lwtv-underscores' ),
						status.prefix
					) }
				</p>
				<p>
					<strong>{ __( 'Keys:', 'lwtv-underscores' ) }</strong>{ ' ' }
					{ sprintf(
						/* translators: 1: yes/no, 2: yes/no, 3: yes/no. */
						__(
							'indexing key set: %1$s · search key set: %2$s · search key differs from the indexing key: %3$s',
							'lwtv-underscores'
						),
						status.keys.index_key ? yes : no,
						status.keys.search_key ? yes : no,
						status.keys.search_key_differs ? yes : no
					) }
				</p>
				<p>
					<strong>{ __( 'Search:', 'lwtv-underscores' ) }</strong>{ ' ' }
					{ sprintf(
						/* translators: 1: off/editors/everyone, 2: where it's set, 3: timeout in ms. */
						__(
							'%1$s (%2$s) · timeout %3$d ms',
							'lwtv-underscores'
						),
						status.switch.mode,
						'constant' === status.switch.source
							? __( 'set in wp-config.php', 'lwtv-underscores' )
							: __( 'set on this page', 'lwtv-underscores' ),
						status.timeout_ms
					) }
				</p>
				{ ! status.action_scheduler && (
					<p>
						{ __(
							'Action Scheduler isn’t available, so Set up and Reindex are off. Use wp lwtv meili reindex.',
							'lwtv-underscores'
						) }
					</p>
				) }
				<Button
					variant="secondary"
					onClick={ refresh }
					disabled={ busy }
					accessibleWhenDisabled
				>
					{ __( 'Refresh', 'lwtv-underscores' ) }
				</Button>
			</div>

			<table
				className="widefat striped lwtv-settings__table"
				ref={ table }
			>
				<caption className="screen-reader-text">
					{ __( 'Search indexes on this site', 'lwtv-underscores' ) }
				</caption>
				<thead>
					<tr>
						<th scope="col">
							{ __( 'Index', 'lwtv-underscores' ) }
						</th>
						<th scope="col">
							{ __( 'State', 'lwtv-underscores' ) }
						</th>
						<th scope="col">
							{ __( 'Documents', 'lwtv-underscores' ) }
						</th>
						<th scope="col">
							{ __( 'Settings', 'lwtv-underscores' ) }
						</th>
						<th scope="col">
							{ __( 'Synonyms', 'lwtv-underscores' ) }
						</th>
						<th scope="col">{ __( 'Job', 'lwtv-underscores' ) }</th>
						<th scope="col">
							{ __( 'Actions', 'lwtv-underscores' ) }
						</th>
					</tr>
				</thead>
				<tbody>
					{ status.indexes.map( ( row ) => {
						const exists = 'exists' === row.state;
						const jobRunning = isRunning( row.job );
						const drifted =
							row.settings_drift.length > 0 ||
							false === row.synonyms_match;
						const off = busy || jobRunning;
						const needsScheduler = ! status.action_scheduler;
						const label = ( action ) =>
							sprintf(
								/* translators: 1: action, 2: index name. */
								__( '%1$s %2$s', 'lwtv-underscores' ),
								action,
								row.label
							);

						return (
							<tr
								key={ row.type }
								id={ `lwtv-settings-row-${ row.type }` }
								onFocus={ () =>
									( focusedRow.current = row.type )
								}
							>
								<th
									scope="row"
									tabIndex={ -1 }
									data-label={ __(
										'Index',
										'lwtv-underscores'
									) }
								>
									{ row.label }
									<br />
									<code>{ row.uid }</code>
								</th>
								<td
									data-label={ __(
										'State',
										'lwtv-underscores'
									) }
								>
									{ stateText[ row.state ] || row.state }
								</td>
								<td
									data-label={ __(
										'Documents',
										'lwtv-underscores'
									) }
								>
									{ null === row.documents
										? '—'
										: row.documents.toLocaleString() }
									{ row.indexing &&
										` (${ __(
											'indexing',
											'lwtv-underscores'
										) })` }
								</td>
								<td
									data-label={ __(
										'Settings',
										'lwtv-underscores'
									) }
								>
									{ ! exists && '—' }
									{ exists &&
										( row.settings_drift.length
											? sprintf(
													/* translators: %s: setting names. */
													__(
														'Differs: %s',
														'lwtv-underscores'
													),
													row.settings_drift.join(
														', '
													)
												)
											: __(
													'Match',
													'lwtv-underscores'
												) ) }
								</td>
								<td
									data-label={ __(
										'Synonyms',
										'lwtv-underscores'
									) }
								>
									{ null === row.synonyms_match && '—' }
									{ true === row.synonyms_match &&
										__( 'Match', 'lwtv-underscores' ) }
									{ false === row.synonyms_match &&
										__( 'Differ', 'lwtv-underscores' ) }
								</td>
								<td
									data-label={ __(
										'Job',
										'lwtv-underscores'
									) }
									className={
										'failed' === row.job?.state
											? 'is-error'
											: ''
									}
								>
									{ jobRunning && <Spinner /> }
									{ jobText( row.job ) }
								</td>
								<td
									data-label={ __(
										'Actions',
										'lwtv-underscores'
									) }
									className="lwtv-settings__actions"
								>
									{ exists && drifted && (
										<Button
											variant="secondary"
											size="compact"
											disabled={ off }
											accessibleWhenDisabled
											aria-label={ label( KIND.apply ) }
											onClick={ () =>
												act( row, 'apply', 'POST' )
											}
										>
											{ KIND.apply }
										</Button>
									) }
									{ exists && (
										<Button
											variant="secondary"
											size="compact"
											disabled={ off || needsScheduler }
											accessibleWhenDisabled
											aria-label={ label( KIND.reindex ) }
											onClick={ () =>
												act( row, 'reindex', 'POST' )
											}
										>
											{ KIND.reindex }
										</Button>
									) }
									{ 'missing' === row.state && (
										<Button
											variant="primary"
											size="compact"
											disabled={ off || needsScheduler }
											accessibleWhenDisabled
											aria-label={ label( KIND.setup ) }
											onClick={ () =>
												act( row, 'setup', 'POST' )
											}
										>
											{ KIND.setup }
										</Button>
									) }
									{ jobRunning && (
										<Button
											variant="tertiary"
											size="compact"
											isDestructive
											disabled={ busy }
											accessibleWhenDisabled
											aria-label={ label(
												__(
													'Cancel the job on',
													'lwtv-underscores'
												)
											) }
											onClick={ () =>
												act( row, null, 'DELETE' )
											}
										>
											{ __(
												'Cancel',
												'lwtv-underscores'
											) }
										</Button>
									) }
								</td>
							</tr>
						);
					} ) }
				</tbody>
			</table>
		</div>
	);
}
