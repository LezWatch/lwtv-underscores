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
/* translators: 1: count, 2: year. */
$sb_title      = sprintf( _n( '%1$s show premiered in %2$s', '%1$s shows premiered in %2$s', $sb_count, 'lwtv' ), number_format_i18n( $sb_count ), (string) $this_year );
$sb_desc       = __( 'Series with a queer woman or non-binary character that started airing this year.', 'lwtv' );
$sb_foot       = __( 'A show counts as new the year its first episode aired.', 'lwtv' );
$sb_by_name    = $new_shows_by_name;
$sb_by_format  = $new_shows_by_format;
$sb_by_country = $new_shows_by_country;
// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
include __DIR__ . '/partials/show-block.php';
