import metadata from '../block.json';
import { makeSave } from './save';

// Older saved markups, newest first, so existing screeners validate and
// upgrade on next save. See components/markup.js for what each level
// reproduces. Deprecations don't inherit attributes or supports.
const shared = {
	attributes: metadata.attributes,
	supports: metadata.supports,
};

export default [
	// Bootstrap 5 classes + data-bs-toggle, icons missing (pre-v6 save()).
	{ ...shared, save: makeSave( 'bs5' ) },
	// Bootstrap 5 classes + Bootstrap 4 data-toggle, icons present.
	{ ...shared, save: makeSave( 'bs4' ) },
	// As above, from the earliest screeners, which stored `queer` as a
	// number (e.g. 5). A string-only attribute rejects that and falls back
	// to the default, so accept both and migrate to a string.
	{
		...shared,
		attributes: {
			...metadata.attributes,
			queer: {
				...metadata.attributes.queer,
				type: [ 'string', 'number' ],
			},
		},
		save: makeSave( 'bs4' ),
		migrate: ( attributes ) => ( {
			...attributes,
			queer: String( attributes.queer ),
		} ),
	},
];
