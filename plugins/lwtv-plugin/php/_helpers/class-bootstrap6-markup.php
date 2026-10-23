<?php
/**
 * Name: Bootstrap 6 Markup
 * Description: Pure transform of Bootstrap 5 class names in stored post content.
 *
 * Bootstrap 6 replaced per-colour classes with a variant plus .theme-{key}:
 *
 *   alert-{c}        → theme-{c}               (keeps .alert)
 *   btn-{c}          → btn-solid theme-{c}     (keeps .btn)
 *   btn-outline-{c}  → btn-outline theme-{c}
 *   text-bg-{c}      → theme-{c}
 *
 * for c in primary, secondary, success, danger, warning, info, and dark,
 * which becomes inverse. This is the mapping scss/addons/_bootstrap-legacy.scss
 * styles, so migrated content looks the same. v6 has no light theme, so
 * light variants are reported, not changed.
 *
 * Only class attributes inside real tags change, plus "className" in block
 * comment JSON so a core block stays valid. Spoiler, Grade and Screener
 * blocks are skipped whole: they are upgraded by re-saving through
 * Gutenberg (see docs/operations/migrations/bootstrap-6.md), and a string
 * edit would leave them not matching save(). No WordPress calls, so it is
 * unit-testable; `wp lwtv migrate bootstrap6` supplies the content.
 *
 * @package LWTV
 */

namespace LWTV\_Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Bootstrap6_Markup {

	/**
	 * v5 colour => v6 theme key.
	 */
	const THEMES = array(
		'primary'   => 'primary',
		'secondary' => 'secondary',
		'success'   => 'success',
		'danger'    => 'danger',
		'warning'   => 'warning',
		'info'      => 'info',
		'dark'      => 'inverse',
	);

	/**
	 * Blocks whose markup comes from their own save(); left for the re-save.
	 */
	const SKIP_BLOCKS = 'lez-library\/spoilers|lwtv\/grade|lwtv\/screener';

	/**
	 * Migrate Bootstrap 5 classes in a post's content.
	 *
	 * @param string $html Post content.
	 *
	 * @return array{html: string, changes: array<string, int>, unmapped: string[]}
	 *               New content, count per v5 class replaced, and v5-looking
	 *               classes left alone because they have no v6 equivalent.
	 */
	public static function migrate( string $html ): array {
		$changes  = array();
		$unmapped = array();

		$pattern = '/(?<skip><!-- wp:(' . self::SKIP_BLOCKS . ')\b.*?<!-- \/wp:\2 -->)'
			. '|(?<comment><!-- wp:[a-z0-9\/-]+ \{.*?\} \/?-->)'
			. '|(?<tag><[a-zA-Z][a-zA-Z0-9-]*\b[^<>]*>)/s';

		$migrated = preg_replace_callback(
			$pattern,
			static function ( $m ) use ( &$changes, &$unmapped ) {
				if ( '' !== ( $m['skip'] ?? '' ) ) {
					return $m[0];
				}

				if ( '' !== ( $m['comment'] ?? '' ) ) {
					return preg_replace_callback(
						'/("className":")((?:[^"\\\\]|\\\\.)*)(")/',
						static function ( $c ) use ( &$changes, &$unmapped ) {
							return $c[1] . self::migrate_class_list( $c[2], $changes, $unmapped ) . $c[3];
						},
						$m[0]
					);
				}

				return preg_replace_callback(
					'/(\sclass\s*=\s*)(["\'])(.*?)\2/is',
					static function ( $c ) use ( &$changes, &$unmapped ) {
						return $c[1] . $c[2] . self::migrate_class_list( $c[3], $changes, $unmapped ) . $c[2];
					},
					$m[0]
				);
			},
			$html
		);

		return array(
			'html'     => ( null === $migrated ) ? $html : $migrated,
			'changes'  => $changes,
			'unmapped' => array_values( array_unique( $unmapped ) ),
		);
	}

	/**
	 * Map one space-separated class list.
	 *
	 * @param string   $class_list Class attribute value.
	 * @param array    $changes    Running count per replaced class (by reference).
	 * @param string[] $unmapped   Running list of unmappable classes (by reference).
	 *
	 * @return string
	 */
	private static function migrate_class_list( string $class_list, array &$changes, array &$unmapped ): string {
		$colours = implode( '|', array_keys( self::THEMES ) );
		$tokens  = preg_split( '/(\s+)/', $class_list, -1, PREG_SPLIT_DELIM_CAPTURE );
		$out     = array();
		$touched = false;

		foreach ( $tokens as $token ) {
			if ( preg_match( '/^(alert|text-bg|btn-outline|btn)-(' . $colours . ')$/', $token, $m ) ) {
				$theme = 'theme-' . self::THEMES[ $m[2] ];
				$swap  = array(
					'alert'       => array( $theme ),
					'text-bg'     => array( $theme ),
					'btn-outline' => array( 'btn-outline', $theme ),
					'btn'         => array( 'btn-solid', $theme ),
				)[ $m[1] ];

				$out[]             = implode( ' ', $swap );
				$changes[ $token ] = ( $changes[ $token ] ?? 0 ) + 1;
				$touched           = true;
				continue;
			}

			if ( preg_match( '/^(alert|text-bg|btn-outline|btn)-light$/', $token ) ) {
				$unmapped[] = $token;
			}

			$out[] = $token;
		}

		if ( ! $touched ) {
			return $class_list;
		}

		return self::dedupe( implode( '', $out ) );
	}

	/**
	 * Drop repeated classes, keeping the first, e.g. when content already
	 * had both alert-danger and theme-danger. Preserves outer whitespace.
	 *
	 * @param string $class_list Class list.
	 *
	 * @return string
	 */
	private static function dedupe( string $class_list ): string {
		$lead  = preg_match( '/^\s+/', $class_list, $l ) ? $l[0] : '';
		$trail = preg_match( '/\s+$/', $class_list, $t ) ? $t[0] : '';
		$seen  = array_values( array_unique( preg_split( '/\s+/', trim( $class_list ) ) ) );

		return $lead . implode( ' ', $seen ) . $trail;
	}
}
