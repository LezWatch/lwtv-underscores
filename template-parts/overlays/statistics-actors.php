<?php
/**
 * Template part for displaying the character or actor image
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package LezWatch.TV
 */

$this_id = $args['actor_id'] ?? null;

?>
<button type="button" class="btn btn-primary btn-lg" data-bs-toggle="modal" data-bs-target="#statisticsModal">
	Statistics
</button>

<div class="modal fade" id="statisticsModal" tabindex="-1" aria-labelledby="statisticsModalLabel" aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<h3 class="modal-title fs-5" id="statisticsModalLabel">Character Statistics</h3>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body">
			<section name="stats" id="stats" class="showschar-section">
				<div class="card-body">
					<div class="card-meta">
						<div class="card-meta-item">
							<?php
							$attributes = array(
								'posttype' => get_post_type( $this_id ),
							);
							$stats      = lwtv_plugin()->generate_stats_block_actor( $attributes );

							if ( ! empty( $stats ) ) {
								// phpcs:ignore WordPress.Security.EscapeOutput
								echo $stats;
							} else {
								echo '<p>What\'re the odds? Don\'t worry, the statistics will be right back!</p>';
							}
							?>
							<p><em><small>Note: Character roles may exceed the number of characters played, if the character appeared on multiple TV shows.</small></em></p>
						</div>
					</div>
				</div>
			</section>
			</div>
		</div>
	</div>
</div>

