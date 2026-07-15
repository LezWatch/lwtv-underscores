<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The template for displaying the top nations
 *
 * @package LezWatch.TV
 *
 * Required variables:
 * @var int $shows - Total shows
 * @var int $characters - Total characters
 * @var int $actors - Total actors
 * @var int $dead_chars - Total dead characters
 */

use LWTV\Statistics\Build\Nations as Build_Nations;

$top_amount       = 10;
$build_nations    = new Build_Nations();
$nations          = $build_nations->get_top_nations( $top_amount );
$formatted_amount = new \NumberFormatter( 'en_US', \NumberFormatter::SPELLOUT );
?>

<h4>Top <?php echo esc_html( ucfirst( $formatted_amount->format( $top_amount ) ) ); ?> Nations</h4>

<table class="table table-striped table-hover">
	<thead>
		<tr>
			<th scope="col">&nbsp;</th>
			<th scope="col">Shows</th>
			<th scope="col">Percent</th>
		</tr>
	</thead>
	<tbody>
		<?php
		foreach ( $nations as $nation ) {
			$percent = $shows ? round( ( ( $nation['count'] / $shows ) * 100 ), 1 ) : 0;
			echo '<tr>
					<th scope="row"><a href="' . esc_url( site_url( 'statistics/nations/?country=' . $nation['slug'] ) ) . '">' . esc_html( $nation['name'] ) . '</a></th>
					<td>' . (int) $nation['count'] . '</td>
					<td><div class="progress"><div class="progress-bar" role="progressbar" style="width: ' . esc_html( $percent ) . '%;" aria-valuenow="25" aria-valuemin="0" aria-valuemax="100"></div></div>&nbsp;' . esc_html( $percent ) . '%</td>
				</tr>';
		}
		?>
	</tbody>
</table>
