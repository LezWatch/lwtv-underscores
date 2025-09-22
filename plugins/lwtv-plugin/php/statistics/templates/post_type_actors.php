<?php
/**
 * The template for displaying the mini stats section on Actor Pages
 *
 * I don't like how it formats with the height...
 *
 * @package LezWatch.TV
 */

// If the actor has the post meta lezactors_char_list, show the stats
$char_list = get_post_meta( get_the_id(), 'lezactors_char_list', true );
?>

<div class="container chart-container">
	<div class="row">
		<?php
		if ( empty( $char_list ) ) {
			?>
			<div class="col-12">
				<h5>Please come back later, we're still processing this actor's data.</h5>
			</div>
			<?php
		} else {
			?>
			<div class="col-6">
				<h5>Roles</h5>
				<?php echo lwtv_plugin()->generate_individual_actors( get_the_id(), 'piechart', 'roles' ); ?>
			</div>
			<div class="col-6">
				<h5>Status</h5>
				<?php echo lwtv_plugin()->generate_individual_actors( get_the_id(), 'piechart', 'dead' ); ?>
			</div>
			<?php
		}
		?>
	</div>
</div>
