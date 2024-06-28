<?php
/**
 * Last Death
 *
 * @package LWTV Underscores
 */
?>
<div class="alert alert-danger" role="alert">
	<div class="container">
		<div class="row">
			<div class="col dead-widget-container">
				<center><?php echo wp_kses_post( lwtv_last_death() ); ?></center>
			</div>
		</div>
	</div>
</div>
