/**
 * Accessible tabs (WAI-ARIA tablist), controlled by the app so it can switch
 * to the tab holding an error before moving focus there.
 *
 * Arrow keys, Home and End move between tabs (and select them); only the
 * selected tab is in the Tab order. Every panel stays mounted, hidden when
 * not selected, so edits in one tab survive switching to another.
 */
import { useRef } from '@wordpress/element';

export default function Tabs( { id, label, tabs, selected, onSelect } ) {
	const refs = useRef( {} );

	const move = ( event, index ) => {
		const last = tabs.length - 1;
		const next = {
			ArrowRight: index === last ? 0 : index + 1,
			ArrowLeft: index === 0 ? last : index - 1,
			Home: 0,
			End: last,
		}[ event.key ];

		if ( next === undefined ) {
			return;
		}
		event.preventDefault();
		onSelect( tabs[ next ].name );
		refs.current[ tabs[ next ].name ]?.focus();
	};

	return (
		<div className="lwtv-rules-tabs">
			<div
				className="lwtv-rules-tabs__list"
				role="tablist"
				aria-label={ label }
			>
				{ tabs.map( ( tab, index ) => {
					const isSelected = tab.name === selected;
					return (
						<button
							key={ tab.name }
							ref={ ( el ) => ( refs.current[ tab.name ] = el ) }
							type="button"
							role="tab"
							id={ `${ id }-tab-${ tab.name }` }
							aria-controls={ `${ id }-panel-${ tab.name }` }
							aria-selected={ isSelected }
							tabIndex={ isSelected ? 0 : -1 }
							className={ `lwtv-rules-tabs__tab${
								isSelected ? ' is-selected' : ''
							}` }
							onClick={ () => onSelect( tab.name ) }
							onKeyDown={ ( event ) => move( event, index ) }
						>
							{ tab.title }
						</button>
					);
				} ) }
			</div>
			{ tabs.map( ( tab ) => (
				<div
					key={ tab.name }
					role="tabpanel"
					id={ `${ id }-panel-${ tab.name }` }
					aria-labelledby={ `${ id }-tab-${ tab.name }` }
					hidden={ tab.name !== selected }
					className="lwtv-rules-tabs__panel"
				>
					{ tab.content }
				</div>
			) ) }
		</div>
	);
}
