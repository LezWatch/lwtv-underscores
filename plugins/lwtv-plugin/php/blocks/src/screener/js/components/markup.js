// Markup helpers for the screener badges.
//
// `legacy` reproduces older saved markup so the deprecations in
// ../deprecated.js validate existing content:
//   false  - current: Bootstrap 6 classes, data-bs-toggle, icons rendered.
//   'bs5'  - Bootstrap 5 classes and data-bs-toggle, with the icon missing:
//            display-star/trigger used to render `{ Star }` (a component
//            function, which React drops) instead of `<Star />`.
//   'bs4'  - Bootstrap 5 classes with Bootstrap 4's data-toggle, icons rendered.

const V6_THEMES = { dark: 'inverse' };

export function buttonClass( color, legacy = false ) {
	if ( legacy ) {
		return `btn btn-${ color }`;
	}
	return `btn btn-solid theme-${ V6_THEMES[ color ] ?? color }`;
}

export function tooltipAttrs( legacy = false ) {
	return 'bs4' === legacy
		? { 'data-toggle': 'tooltip' }
		: { 'data-bs-toggle': 'tooltip' };
}

export function showIcon( legacy = false ) {
	return 'bs5' !== legacy;
}
