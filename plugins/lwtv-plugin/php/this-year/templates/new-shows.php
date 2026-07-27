<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * This Year — New Shows: thin wrapper around the shared show-block partial.
 *
 * @package LezWatch.TV
 *
 * @var int   $this_year
 * @var int   $new_shows_count
 * @var array $new_shows_by_name
 * @var array $new_shows_by_format
 * @var array $new_shows_by_country
 */

$sb_accent = 'pink';
$sb_count  = (int) $new_shows_count;

// Empty state — guard first, nothing else in this template applies.
if ( 0 === $sb_count ) {
	?>
	<div class="lwtv-ty-empty">
		<div class="lwtv-ty-empty-icon">
			<?php echo lwtv_plugin()->get_symbolicon( svg: 'construction.svg', icon: 'svg-construction', max_size: '28' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>
		<h2><?php esc_html_e( 'No shows have premiered this year.', 'lwtv' ); ?></h2>
		<p><?php esc_html_e( "We're surprised too!", 'lwtv' ); ?></p>
	</div>
	<?php
	return;
}

/* translators: 1: count, 2: year. */
$sb_title      = sprintf( _n( '%1$s show premiered in %2$s', '%1$s shows premiered in %2$s', $sb_count, 'lwtv' ), number_format_i18n( $sb_count ), (string) $this_year );
$sb_desc       = __( 'Series and TV movies that started airing this year.', 'lwtv' );
$sb_foot       = __( 'A show counts as new the year its first episode aired.', 'lwtv' );
$sb_source     = 'new';
$sb_by_name    = $new_shows_by_name;
$sb_by_format  = $new_shows_by_format;
$sb_by_country = $new_shows_by_country;
// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
include __DIR__ . '/partials/show-block.php';
