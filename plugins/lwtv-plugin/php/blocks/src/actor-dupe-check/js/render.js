// Plugin Specific Imports
import { __ } from '@wordpress/i18n';
import { PluginPrePublishPanel } from '@wordpress/editor';
import { useSelect, useDispatch } from '@wordpress/data';
import { useState, useEffect, useMemo, useRef } from '@wordpress/element';
import { Button, Spinner } from '@wordpress/components';

const NOTICE_ID = 'lwtv-actor-dupe-check';
const LOCK_KEY = 'lwtv-actor-dupe-check';
const DEBOUNCE_MS = 500;
const MIN_LENGTH = 3;

/**
 * A stable string for one set of matches, used to decide whether the warning
 * has anything new to say.
 *
 * @param {Array} matches Candidates from the REST route.
 * @return {string} Signature.
 */
const signatureOf = (matches) =>
	matches
		.map((match) => `${match.id}:${match.tier}`)
		.sort()
		.join('|');

/**
 * Wording for one candidate.
 *
 * The two tiers are not equally trustworthy and must not read as though they
 * are. A full match means every part of the name is accounted for once accents,
 * punctuation and word order are set aside. An ends match shares only the first
 * and last part, which is what catches a dropped middle name -- and also pairs
 * people who are genuinely different.
 *
 * @param {Object} candidate One candidate.
 * @return {string} Label.
 */
const describe = (candidate) => {
	const status =
		candidate.status === 'publish' ? '' : ` (${candidate.status})`;

	return candidate.tier === 'full'
		? `${candidate.title}${status}`
		: `${candidate.title}${status} — similar name`;
};

export default function Render() {
	const postType = useSelect((select) =>
		select('core/editor').getCurrentPostType()
	);

	const postId = useSelect((select) =>
		select('core/editor').getCurrentPostId()
	);

	const editedTitle = useSelect(
		(select) => select('core/editor').getEditedPostAttribute('title') || ''
	);

	const savedTitle = useSelect(
		(select) => select('core/editor').getCurrentPost().title || ''
	);

	const { createWarningNotice, removeNotice } = useDispatch('core/notices');
	const { lockPostSaving, unlockPostSaving } = useDispatch('core/editor');

	// Matches are kept with the name they belong to, so a result never applies
	// to a name the editor has since typed past.
	const [result, setResult] = useState({ name: '', matches: [] });
	const [isChecking, setIsChecking] = useState(false);
	const [acknowledged, setAcknowledged] = useState(false);

	const announced = useRef('');

	const isActor = postType === 'post_type_actors';
	const name = editedTitle.trim();

	/*
	 * Only check a name the editor has actually touched. Reopening a published
	 * actor and changing nothing should say nothing -- the duplicate, if there
	 * is one, is not news at that point, and a warning on every page load is a
	 * warning people learn to ignore.
	 */
	const shouldCheck =
		isActor && name.length >= MIN_LENGTH && name !== savedTitle.trim();

	const isCurrent = shouldCheck && result.name === name;

	// Memoised so the notice effect below depends on an array that only changes
	// when the matches themselves do, rather than on a fresh one every render.
	const matches = useMemo(
		() => (isCurrent ? result.matches : []),
		[isCurrent, result.matches]
	);

	const strict = matches.filter((candidate) => candidate.tier === 'full');

	// Computed once so the effects below can depend on a plain string rather
	// than on an array rebuilt on every render.
	const signature = signatureOf(matches);

	/*
	 * A strict match is the only thing here worth stopping a publish over. The
	 * audit that sized this found one strict collision across 6,132 actors,
	 * against four at the loose tier of which three were different people --
	 * so loose warns and strict blocks, and never the other way round.
	 */
	const shouldLock = strict.length > 0 && !acknowledged;

	// Fetch.
	useEffect(() => {
		if (!shouldCheck) {
			return undefined;
		}

		const controller = new AbortController();

		const timer = setTimeout(() => {
			setIsChecking(true);

			const path =
				`/lwtv/v1/actors/name-check?name=${encodeURIComponent(name)}` +
				`&exclude=${postId ? postId : 0}`;

			// The editor's own apiFetch, so the request carries the REST nonce.
			// This route is gated on the actors capability and will 401 without
			// it -- see Rest_API\Actor_Name_Check on why it is not public.
			window.wp
				.apiFetch({ path, signal: controller.signal })
				.then((data) => {
					setResult({
						name,
						matches:
							data && Array.isArray(data.matches)
								? data.matches
								: [],
					});
					setIsChecking(false);
				})
				.catch((error) => {
					// An aborted request is the expected outcome of typing
					// another character, not a failure worth reporting.
					if (error && error.name === 'AbortError') {
						return;
					}

					// Fail open. A check we could not run must never be the
					// reason somebody cannot publish.
					setResult({ name, matches: [] });
					setIsChecking(false);
				});
		}, DEBOUNCE_MS);

		return () => {
			clearTimeout(timer);
			controller.abort();
		};
	}, [shouldCheck, name, postId]);

	// A different set of matches is a new question, so it has to be asked again.
	useEffect(() => {
		setAcknowledged(false);
	}, [signature]);

	/*
	 * The lock. Keyed by name so it never fights another plugin's lock, and
	 * released in cleanup so no code path can leave the Publish button dead.
	 */
	useEffect(() => {
		if (shouldLock) {
			lockPostSaving(LOCK_KEY);
		} else {
			unlockPostSaving(LOCK_KEY);
		}

		return () => {
			unlockPostSaving(LOCK_KEY);
		};
	}, [shouldLock, lockPostSaving, unlockPostSaving]);

	/*
	 * The notice, which is also where the unlock lives.
	 *
	 * It has to be here rather than only in the pre-publish panel: that panel
	 * renders only when the editor has WordPress's pre-publish checks turned
	 * on, and a locked Publish button with no visible way to clear it would be
	 * indistinguishable from a broken editor.
	 */
	useEffect(() => {
		const announcement = `${signature}|${acknowledged}`;

		if (announcement === announced.current) {
			return undefined;
		}

		announced.current = announcement;
		removeNotice(NOTICE_ID);

		if (!matches.length) {
			return undefined;
		}

		let message = 'An actor with a similar name already exists.';

		if (strict.length && !acknowledged) {
			message =
				'This actor may already be in the database. Publishing is paused until you confirm.';
		} else if (strict.length) {
			message = 'This actor may already be in the database.';
		}

		const actions = matches
			.filter((candidate) => candidate.edit_url)
			.map((candidate) => ({
				label: describe(candidate),
				url: candidate.edit_url,
			}));

		if (strict.length && !acknowledged) {
			actions.unshift({
				label: 'These are different people',
				onClick: () => setAcknowledged(true),
			});
		}

		createWarningNotice(message, {
			id: NOTICE_ID,
			isDismissible: true,
			actions,
		});

		return undefined;
	}, [
		matches,
		signature,
		strict.length,
		acknowledged,
		createWarningNotice,
		removeNotice,
	]);

	// Clear up after ourselves when the editor moves on.
	useEffect(() => {
		return () => {
			removeNotice(NOTICE_ID);
		};
	}, [removeNotice]);

	if (!isActor) {
		return null;
	}

	// Nothing checked yet means an untouched name, and nothing to report.
	if (!isCurrent && !isChecking) {
		return null;
	}

	return (
		<PluginPrePublishPanel
			title={'Duplicate Check'}
			className={
				matches.length ? 'lwtv-dupe-check-warn' : 'lwtv-dupe-check-ok'
			}
			initialOpen={matches.length > 0}
			icon={'info-outline'}
		>
			{isChecking && <Spinner />}

			{!isChecking && !matches.length && (
				<p>{'No existing actor matches this name.'}</p>
			)}

			{!isChecking && matches.length > 0 && (
				<>
					<p>
						{strict.length && !acknowledged
							? 'These may be the same person. Confirm they are not to continue.'
							: 'These actors may be the same person.'}
					</p>
					<ul className="lwtv-dupe-check-list">
						{matches.map((candidate) => (
							<li key={candidate.id}>
								{candidate.edit_url ? (
									<a
										href={candidate.edit_url}
										target="_blank"
										rel="noreferrer"
									>
										{describe(candidate)}
									</a>
								) : (
									describe(candidate)
								)}
							</li>
						))}
					</ul>

					{strict.length > 0 && !acknowledged && (
						<Button
							variant="secondary"
							onClick={() => setAcknowledged(true)}
						>
							{'These are different people'}
						</Button>
					)}

					<p className="lwtv-dupe-check-hint">
						{
							'To stop this asking again, list them in "Not a duplicate of" under Administrative.'
						}
					</p>
				</>
			)}
		</PluginPrePublishPanel>
	);
}
