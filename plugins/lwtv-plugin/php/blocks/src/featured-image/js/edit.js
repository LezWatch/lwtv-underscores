import { PostFeaturedImage } from '@wordpress/editor';
import { useBlockProps } from '@wordpress/block-editor';

export default function Edit() {
	return (
		<div { ...useBlockProps() }>
			<PostFeaturedImage />
		</div>
	);
}
