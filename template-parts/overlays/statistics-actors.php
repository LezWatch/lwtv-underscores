<?php
/**
 * Overlay for actor statistics
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package LezWatch.TV
 */

$this_id = $args['actor_id'] ?? null;

?>

<div class="col">
	<div class="card text-center">
		<span data-bs-toggle="modal" data-bs-target="#statisticsModal">
			<?php echo lwtv_symbolicons( 'presentation-alt.svg', 'fa-chart-line' ); ?>
			<p>Statistics</p>
		</span>
	</div>
</div>

<div class="modal fade" id="statisticsModal" tabindex="-1" aria-labelledby="statisticsModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-lg">
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
								?>
								<p><em><small>Note: Character roles may exceed the number of characters played, if the character appeared on multiple TV shows.</small></em></p>
								<?php
							} else {
								?>
								<p><img src="<?php echo esc_url( get_template_directory_uri() ); ?>/images/rose.gif" alt="Rose revealing herself by peeling off a face mask in Jane the Virgin" class="alignleft"/></p>
								<p>What're the odds? Don't worry, the statistics will be right back!</p>
								<?php
							}
							?>
						</div>
					</div>
				</div>
			</section>
			</div>
		</div>
	</div>
</div>

