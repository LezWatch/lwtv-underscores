import { RichText, PlainText, useBlockProps } from '@wordpress/block-editor';

// Editor-only badge with a picker. A <span> rather than the <button> the
// saved markup uses: a <select> can't live inside a <button> (or a <form>
// inside one), and React logs that nesting as an error.
function Picker( { label, color, value, options, onChange } ) {
	return (
		<span className={ `btn btn-${ color }` }>
			{ label }
			<select
				value={ value }
				onChange={ ( event ) => onChange( event.target.value ) }
			>
				{ options.map( ( [ optionValue, optionLabel ] ) => (
					<option key={ optionValue } value={ optionValue }>
						{ optionLabel }
					</option>
				) ) }
			</select>
		</span>
	);
}

export default function Edit( { attributes, setAttributes } ) {
	const { title, summary, queer, worthit, star, trigger } = attributes;
	const blockProps = useBlockProps( {
		className: 'lwtv-screener bd-callout screener-shortcode',
	} );

	return (
		<div { ...blockProps }>
			<h5>
				Screener Review On ...&nbsp;
				<PlainText
					value={ title }
					placeholder={ 'Show Title' }
					onChange={ ( value ) => setAttributes( { title: value } ) }
				/>
			</h5>
			<RichText
				tagName="p"
				value={ summary }
				placeholder={ 'Content of Review' }
				onChange={ ( value ) => setAttributes( { summary: value } ) }
			/>
			<p>
				<Picker
					label="Queer:"
					color="dark"
					value={ queer }
					options={ [ '0', '1', '2', '3', '4', '5' ].map( ( n ) => [
						n,
						n,
					] ) }
					onChange={ ( value ) => setAttributes( { queer: value } ) }
				/>
				<Picker
					label="Worth:"
					color={ worthit }
					value={ worthit }
					options={ [
						[ 'yes', 'Yes' ],
						[ 'meh', 'Meh' ],
						[ 'no', 'No' ],
						[ 'tbd', 'TBD' ],
					] }
					onChange={ ( value ) =>
						setAttributes( { worthit: value } )
					}
				/>
				<Picker
					label="Trigger:"
					color={ trigger }
					value={ trigger }
					options={ [
						[ 'none', 'None' ],
						[ 'low', 'Low' ],
						[ 'medium', 'Medium' ],
						[ 'high', 'High' ],
					] }
					onChange={ ( value ) =>
						setAttributes( { trigger: value } )
					}
				/>
				<Picker
					label="Star:"
					color={ star }
					value={ star }
					options={ [
						[ 'none', 'None' ],
						[ 'gold', 'Gold' ],
						[ 'silver', 'Silver' ],
						[ 'bronze', 'Bronze' ],
						[ 'anti', 'Anti' ],
					] }
					onChange={ ( value ) => setAttributes( { star: value } ) }
				/>
			</p>
		</div>
	);
}
