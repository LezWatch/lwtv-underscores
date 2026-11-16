import { RichText, useBlockProps } from '@wordpress/block-editor';

export default function Edit( { attributes, setAttributes } ) {
	const blockProps = useBlockProps( { className: 'alert alert-danger' } );

	return (
		<div { ...blockProps }>
			<RichText
				tagName="div"
				value={ attributes.content }
				onChange={ ( content ) => setAttributes( { content } ) }
			/>
		</div>
	);
}
