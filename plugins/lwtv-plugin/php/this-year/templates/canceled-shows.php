<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * This Year — Canceled Shows: thin wrapper around the shared show-block partial.
 *
 * @package LezWatch.TV
 *
 * @var int   $this_year
 * @var int   $canceled_shows_count
 * @var array $canceled_shows_by_name
 * @var array $canceled_shows_by_format
 * @var array $canceled_shows_by_country
 */

$sb_accent = 'amber';
$sb_count  = (int) $canceled_shows_count;
/* translators: 1: count, 2: year. */
$sb_title      = sprintf( _n( '%1$s show ended in %2$s', '%1$s shows ended in %2$s', $sb_count, 'lwtv' ), number_format_i18n( $sb_count ), (string) $this_year );
$sb_desc       = __( 'Series that aired their final episode this year.', 'lwtv' );
$sb_foot       = __( 'Includes both cancellations and planned finales.', 'lwtv' );
$sb_by_name    = $canceled_shows_by_name;
$sb_by_format  = $canceled_shows_by_format;
$sb_by_country = $canceled_shows_by_country;
// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
include __DIR__ . '/partials/show-block.php';
