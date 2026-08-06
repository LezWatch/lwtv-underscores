<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Reusable waffle chart: an SVG grid of dots with N of them filled.
 *
 * The classic "1 in N" pictogram — 100 dots, one filled per percent.
 * Dots inherit the surrounding text color (currentColor); filled dots are
 * solid, unfilled dots are faded. Color families come from the parent
 * band (e.g. .card-header.bury-queers), so the same partial works on any
 * stats card in light and dark mode.
 *
 * @package LezWatch.TV
 *
 * @var array $waffle {
 *   @type int    $filled  Number of filled cells (clamped to 0…total).
 *   @type int    $total   Total cells. Default 100.
 *   @type int    $columns Grid columns. Default 10.
 *   @type int    $radius  Dot radius in viewBox units. Default 6. Pitch (the
 *                          center-to-center spacing) is derived from this, so
 *                          the gap between dots always scales with the dots
 *                          themselves — callers only ever set one number.
 *   @type string $label   Accessible description of the figure.
 * }
 */

$waffle_total   = max( 1, (int) ( $waffle['total'] ?? 100 ) );
$waffle_filled  = min( $waffle_total, max( 0, (int) ( $waffle['filled'] ?? 0 ) ) );
$waffle_columns = max( 1, (int) ( $waffle['columns'] ?? 10 ) );
$waffle_rows    = (int) ceil( $waffle_total / $waffle_columns );
$waffle_label   = (string) ( $waffle['label'] ?? '' );

// Geometry: pitch is derived from radius, not a fixed constant, so the gap
// between dots always scales with dot size instead of getting eaten as
// radius grows. The 17/6 ratio reproduces the original hand-tuned look
// (radius 6 → pitch 17, a ~0.42:1 gap-to-diameter ratio) at any size.
$waffle_radius = max( 1, (int) ( $waffle['radius'] ?? 6 ) );
$waffle_pitch  = (int) round( $waffle_radius * ( 17 / 6 ) );
$waffle_edge   = 8;
$waffle_width  = ( ( $waffle_columns - 1 ) * $waffle_pitch ) + ( 2 * $waffle_edge );
$waffle_height = ( ( $waffle_rows - 1 ) * $waffle_pitch ) + ( 2 * $waffle_edge );
?>
<svg class="lwtv-waffle" viewBox="0 0 <?php echo (int) $waffle_width; ?> <?php echo (int) $waffle_height; ?>" role="img" aria-label="<?php echo esc_attr( $waffle_label ); ?>">
	<?php
	for ( $waffle_cell = 0; $waffle_cell < $waffle_total; $waffle_cell++ ) {
		$waffle_col = $waffle_cell % $waffle_columns;
		$waffle_row = (int) floor( $waffle_cell / $waffle_columns );
		printf(
			'<circle class="lwtv-waffle-dot%1$s" cx="%2$d" cy="%3$d" r="%4$d" />',
			esc_attr( ( $waffle_cell < $waffle_filled ) ? ' lwtv-waffle-dot--filled' : '' ),
			(int) ( $waffle_edge + ( $waffle_col * $waffle_pitch ) ),
			(int) ( $waffle_edge + ( $waffle_row * $waffle_pitch ) ),
			(int) $waffle_radius
		);
	}
	?>
</svg>
