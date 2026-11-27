/**
 * One polite live region per screen: say() puts a message in it.
 */
import { useCallback, useRef, useState } from '@wordpress/element';

/**
 * @return {[string, (message: string) => void]} The region's text, and say( message ).
 */
export default function useAnnounce() {
	const [ text, setText ] = useState( '' );
	const toggle = useRef( false );

	// A toggled trailing space makes a repeated message announce again.
	const say = useCallback( ( message ) => {
		toggle.current = ! toggle.current;
		setText( message + ( toggle.current ? ' ' : '' ) );
	}, [] );

	return [ text, say ];
}

/**
 * The live region itself.
 *
 * @param {Object} props      Props.
 * @param {string} props.text What to announce.
 * @return {Element} Region.
 */
export function LiveRegion( { text } ) {
	return (
		<div className="screen-reader-text" role="status" aria-live="polite">
			{ text }
		</div>
	);
}
