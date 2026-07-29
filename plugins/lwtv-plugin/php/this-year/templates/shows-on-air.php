<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * This Year — Shows On Air: thin wrapper around the shared show-block partial.
 *
 * @package LezWatch.TV
 *
 * @var int   $this_year
 * @var int   $shows_on_air_count
 * @var array $shows_by_name
 * @var array $shows_by_format
 * @var array $shows_by_country
 */

$sb_accent = 'blue';
$sb_count  = (int) $shows_on_air_count;

// Empty state — guard first, nothing else in this template applies.
if ( 0 === $sb_count ) {
	?>
	<div class="lwtv-ty-empty">
		<div class="lwtv-ty-empty-icon">
			<?php echo lwtv_plugin()->get_symbolicon( svg: 'construction.svg', icon: 'svg-construction', max_size: '28' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>
		<h2><?php esc_html_e( 'No shows were found on air this year.', 'lwtv' ); ?></h2>
		<p><?php esc_html_e( "We're surprised too!", 'lwtv' ); ?></p>
	</div>
	<?php
	return;
}

/* translators: 1: count, 2: year. */
$sb_title      = sprintf( _n( '%1$s show on air in %2$s', '%1$s shows on air in %2$s', $sb_count, 'lwtv' ), number_format_i18n( $sb_count ), (string) $this_year );
$sb_desc       = __( 'Every tracked series airing at least one episode this year.', 'lwtv' );
$sb_foot       = __( 'Grouped alphabetically, by format, or by country of origin.', 'lwtv' );
$sb_source     = 'on-air';
$sb_by_name    = $shows_by_name;
$sb_by_format  = $shows_by_format;
$sb_by_country = $shows_by_country;

// The shared block auto-derives the most-popular letter / format / country
// callouts from the grouped data above.

// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
include __DIR__ . '/partials/show-block.php';
