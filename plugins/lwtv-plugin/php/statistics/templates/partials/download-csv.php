<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Reusable CSV download card: icon chip + title + row meta + download button.
 *
 * @package LezWatch.TV
 *
 * @var array $download_csv {
 *   @type string $title Card heading.
 *   @type int    $count Number of rows the CSV will contain.
 *   @type string $page  Singular unit for "one row per {page}" (e.g. 'year', 'actor').
 * }
 */

$dl_title = (string) ( $download_csv['title'] ?? __( 'Download the data', 'lwtv' ) );
$dl_count = (int) ( $download_csv['count'] ?? 0 );
$dl_page  = (string) ( $download_csv['page'] ?? __( 'row', 'lwtv' ) );
?>
<div class="lwtv-download-csv-panel bg-light">
	<span class="lwtv-download-csv-icon"><?php echo lwtv_plugin()->get_symbolicon( svg: 'magic-wand.svg', icon: 'svg-magic-wand', max_size: '24' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
	<span class="lwtv-download-csv-text">
		<strong class="lwtv-download-csv-title"><?php echo esc_html( $dl_title ); ?></strong>
		<small class="lwtv-download-csv-meta">
			<?php
			printf(
				/* translators: 1: number of rows, 2: singular unit (year, actor, station, nation). */
				esc_html( _n( '%1$s row · CSV · one row per %2$s', '%1$s rows · CSV · one row per %2$s', $dl_count, 'lwtv' ) ),
				esc_html( number_format_i18n( $dl_count ) ),
				esc_html( $dl_page )
			);
			?>
		</small>
	</span>
	<a class="lwtv-download-csv-btn" href="<?php echo esc_url( add_query_arg( 'download', 'csv' ) ); ?>"><?php esc_html_e( 'Download CSV', 'lwtv' ); ?></a>
</div>
