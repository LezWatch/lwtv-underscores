/**
 * Background jobs in words, shared by the Status and Typo tolerance tabs.
 */
import { __, sprintf } from '@wordpress/i18n';

export const KIND = {
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

export const isRunning = ( job ) => 'running' === job?.state;

/**
 * What a job is doing, in words.
 *
 * @param {Object|null} job Job.
 * @return {string} Text.
 */
export function jobText( job ) {
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
