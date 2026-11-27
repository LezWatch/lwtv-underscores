/**
 * Poll while something is in progress, safely.
 *
 * - An answer from before the latest invalidate() is dropped, so a slow
 *   poll can never overwrite what a newer save or job start returned.
 * - A failed poll tries again on the next tick, until maxMs is up.
 * - restart() starts the clock again (after a save, an apply, a job start).
 *
 * The callbacks are read through refs, so re-rendering never resets the timer.
 */
import { useCallback, useEffect, useRef, useState } from '@wordpress/element';

/**
 * @param {Object}                 options            Options.
 * @param {boolean}                options.active     Whether to keep polling.
 * @param {() => Promise<Object>}  options.request    Returns a Promise of fresh data.
 * @param {(data: Object) => void} options.onResult   Gets the data.
 * @param {() => void}             options.onTimeout  Called once when maxMs runs out.
 * @param {number}                 options.intervalMs Wait between polls.
 * @param {number}                 options.maxMs      Give up after this long.
 * @return {{timedOut: boolean, restart: () => void, invalidate: () => void}} Controls.
 */
export default function usePoll( {
	active,
	request,
	onResult,
	onTimeout,
	intervalMs,
	maxMs,
} ) {
	const [ timedOut, setTimedOut ] = useState( false );
	const [ tick, setTick ] = useState( 0 );
	const generation = useRef( 0 );
	const started = useRef( Date.now() );
	const callbacks = useRef( {} );
	callbacks.current = { request, onResult, onTimeout };

	const invalidate = useCallback( () => {
		generation.current++;
	}, [] );

	const restart = useCallback( () => {
		started.current = Date.now();
		setTimedOut( false );
		setTick( ( n ) => n + 1 );
	}, [] );

	useEffect( () => {
		if ( ! active || timedOut ) {
			return;
		}
		const asked = generation.current;
		const timer = setTimeout( () => {
			const over = () => Date.now() - started.current >= maxMs;
			callbacks.current
				.request()
				.then( ( data ) => {
					if ( asked !== generation.current ) {
						return;
					}
					callbacks.current.onResult( data );
					if ( over() ) {
						setTimedOut( true );
						callbacks.current.onTimeout?.();
					} else {
						setTick( ( n ) => n + 1 );
					}
				} )
				.catch( () => {
					if ( asked !== generation.current ) {
						return;
					}
					if ( over() ) {
						setTimedOut( true );
						callbacks.current.onTimeout?.();
					} else {
						setTick( ( n ) => n + 1 );
					}
				} );
		}, intervalMs );

		return () => clearTimeout( timer );
	}, [ active, timedOut, tick, intervalMs, maxMs ] );

	return { timedOut, restart, invalidate };
}
