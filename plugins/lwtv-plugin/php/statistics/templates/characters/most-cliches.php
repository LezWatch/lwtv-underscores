<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The template for displaying characters with the most clichés.
 *
 * @package LezWatch.TV
 */

use LWTV\Statistics\Build\Cliche_Leaders as Build_Cliche_Leaders;

// Cached in the build class, so this call is free even though the barchart
// path below fetches the same data.
$cliche_leaders = ( new Build_Cliche_Leaders() )->generate();
?>
<h3><?php esc_html_e( 'Top 25 Characters With the Most Clichés', 'lwtv' ); ?></h3>

<div class="container chart-container">
	<div class="row">
		<div class="col-sm-12">
			<?php echo lwtv_plugin()->generate_characters_statistics( 'barchart', 'most-cliches' ); ?>
		</div>
	</div>
</div>

<?php if ( ! empty( $cliche_leaders ) ) { ?>
<table class="table table-striped table-hover">
	<thead>
		<tr>
			<th scope="col"><?php esc_html_e( 'Rank', 'lwtv' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Character', 'lwtv' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Clichés', 'lwtv' ); ?></th>
		</tr>
	</thead>
	<tbody>
		<?php
		$rank = 0;
		foreach ( $cliche_leaders as $leader ) {
			++$rank;
			echo '<tr>
					<th scope="row">' . (int) $rank . '</th>
					<td><a href="' . esc_url( $leader['url'] ) . '">' . esc_html( $leader['name'] ) . '</a></td>
					<td>' . (int) $leader['count'] . '</td>
				</tr>';
		}
		?>
	</tbody>
</table>
<?php } ?>
<?php
