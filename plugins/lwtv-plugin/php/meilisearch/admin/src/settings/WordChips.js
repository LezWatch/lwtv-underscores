/**
 * A list of words as chips: Enter or a comma adds what's typed (a pasted
 * list too), Backspace in the empty box removes the last chip, and each
 * chip's remove button is named after its word.
 *
 * It only reports the whole new list through onChange; the caller cleans,
 * de-duplicates and limits it, and says what happened.
 */
import { useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

/**
 * @param {Object}                    props             Props.
 * @param {string}                    props.id          The text box's id.
 * @param {string}                    props.label       Visible label.
 * @param {string}                    props.placeholder Placeholder.
 * @param {string[]}                  props.words       The words.
 * @param {(words: string[]) => void} props.onChange    Gets the whole new list.
 * @param {(word: string) => void}    props.onRemoved   Told which word was removed.
 * @param {boolean}                   props.disabled    Disabled.
 * @param {boolean}                   props.full        No room for more words.
 * @param {string}                    props.describedBy Ids of help and errors.
 * @return {Element} Chips.
 */
export default function WordChips( {
	id,
	label,
	placeholder,
	words,
	onChange,
	onRemoved,
	disabled,
	full,
	describedBy,
} ) {
	const [ text, setText ] = useState( '' );
	const input = useRef( null );

	const add = ( parts ) => {
		const fresh = parts.map( ( p ) => p.trim() ).filter( Boolean );
		if ( fresh.length ) {
			onChange( [ ...words, ...fresh ] );
		}
	};

	const remove = ( word ) => {
		onChange( words.filter( ( w ) => w !== word ) );
		onRemoved( word );
		input.current?.focus();
	};

	// A comma (typed or pasted) ends a word; what follows the last one stays typed.
	const type = ( value ) => {
		if ( ! value.includes( ',' ) ) {
			setText( value );
			return;
		}
		const parts = value.split( ',' );
		setText( parts.pop() );
		add( parts );
	};

	const keyDown = ( event ) => {
		if ( 'Enter' === event.key ) {
			event.preventDefault();
			add( [ text ] );
			setText( '' );
		} else if ( 'Backspace' === event.key && '' === text && words.length ) {
			event.preventDefault();
			remove( words[ words.length - 1 ] );
		}
	};

	return (
		<div className="lwtv-settings__chips-field">
			<label className="lwtv-settings__chips-label" htmlFor={ id }>
				{ label }
			</label>
			{ /* Clicking the box's padding puts the cursor in the text box. */ }
			{ /* eslint-disable-next-line jsx-a11y/click-events-have-key-events, jsx-a11y/no-static-element-interactions */ }
			<div
				className={ `lwtv-settings__chips${
					disabled ? ' is-disabled' : ''
				}` }
				onClick={ ( e ) => {
					if ( e.target === e.currentTarget ) {
						input.current?.focus();
					}
				} }
			>
				{ words.length > 0 && (
					<ul
						aria-label={ sprintf(
							/* translators: %s: the field's label. */
							__( '%s: words added', 'lwtv-underscores' ),
							label
						) }
					>
						{ words.map( ( word ) => (
							<li key={ word }>
								<span>{ word }</span>
								<button
									type="button"
									onClick={ () => remove( word ) }
									disabled={ disabled }
									aria-label={ sprintf(
										/* translators: %s: a word. */
										__( 'Remove “%s”', 'lwtv-underscores' ),
										word
									) }
								>
									<svg
										width="12"
										height="12"
										viewBox="0 0 12 12"
										aria-hidden="true"
										focusable="false"
									>
										<path
											d="M3 3l6 6M9 3l-6 6"
											stroke="currentColor"
											strokeWidth="1.5"
											strokeLinecap="round"
										/>
									</svg>
								</button>
							</li>
						) ) }
					</ul>
				) }
				<input
					ref={ input }
					id={ id }
					type="text"
					value={ text }
					placeholder={ words.length ? '' : placeholder }
					onChange={ ( e ) => type( e.target.value ) }
					onKeyDown={ keyDown }
					disabled={ disabled || full }
					aria-describedby={ describedBy }
					autoComplete="off"
				/>
			</div>
		</div>
	);
}
