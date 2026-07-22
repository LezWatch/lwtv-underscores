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
		<span data-bs-toggle="modal" data-bs-target="#statistics" id="statistics-modal">
			<h5><?php echo lwtv_plugin()->get_symbolicon( svg: 'presentation-alt.svg', icon: 'svg-chart-line' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> Statistics</h5>
		</span>
	</div>
</div>

<div class="modal fade" data-modal-type="overlay" id="statistics" tabindex="-1" aria-labelledby="statisticsLabel" aria-hidden="true">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<h3 class="modal-title fs-5" id="statisticsLabel">Character Statistics</h3>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body lwtv-actor-stats-modal">
				<?php
				$lwtv_char_list  = get_post_meta( $this_id, 'lezactors_char_list', true );
				$lwtv_char_count = is_array( $lwtv_char_list ) ? count( $lwtv_char_list ) : 0;

				$lwtv_roles_raw  = lwtv_plugin()->generate_individual_actors( $this_id, 'array', 'roles' );
				$lwtv_roles_data = ( is_array( $lwtv_roles_raw ) && ! empty( $lwtv_roles_raw ) ) ? (array) reset( $lwtv_roles_raw ) : array();
				$lwtv_dead_raw   = lwtv_plugin()->generate_individual_actors( $this_id, 'array', 'dead' );
				$lwtv_dead_data  = ( is_array( $lwtv_dead_raw ) && ! empty( $lwtv_dead_raw ) ) ? (array) reset( $lwtv_dead_raw ) : array();

				if ( 0 === $lwtv_char_count && empty( $lwtv_roles_data ) ) {
					?>
					<p><img src="<?php echo esc_url( get_template_directory_uri() ); ?>/images/rose.gif" alt="Rose revealing herself by peeling off a face mask in Jane the Virgin" class="alignleft"/></p>
					<p>What're the odds? Don't worry, the statistics will be right back!</p>
					<?php
				} else {
					// ---- Roles donut (Regular pink / Recurring blue / Guest amber). ----
					$lwtv_role_classes = array( 'pink', 'blue', 'amber' );
					$lwtv_total_roles  = 0;
					foreach ( $lwtv_roles_data as $lwtv_role ) {
						$lwtv_total_roles += (int) $lwtv_role['count'];
					}
					$lwtv_role_segments = array();
					foreach ( array_values( $lwtv_roles_data ) as $lwtv_i => $lwtv_role ) {
						$lwtv_rc              = (int) $lwtv_role['count'];
						$lwtv_role_segments[] = array(
							'label' => $lwtv_role['name'],
							'count' => $lwtv_rc,
							'pct'   => ( $lwtv_total_roles > 0 ) ? round( ( $lwtv_rc / $lwtv_total_roles ) * 100, 1 ) : 0,
							'class' => $lwtv_role_classes[ $lwtv_i ] ?? 'grey',
						);
					}

					// ---- Status donut (Alive green / Dead red). ----
					$lwtv_alive           = isset( $lwtv_dead_data[0] ) ? (int) $lwtv_dead_data[0]['count'] : 0;
					$lwtv_dead            = isset( $lwtv_dead_data[1] ) ? (int) $lwtv_dead_data[1]['count'] : 0;
					$lwtv_total_status    = $lwtv_alive + $lwtv_dead;
					$lwtv_dominant        = max( $lwtv_alive, $lwtv_dead );
					$lwtv_status_pct      = ( $lwtv_total_status > 0 ) ? round( ( $lwtv_dominant / $lwtv_total_status ) * 100, 1 ) : 0;
					$lwtv_status_fam      = ( 0 === $lwtv_dead ) ? 'green' : 'red';
					$lwtv_status_sub      = ( $lwtv_alive >= $lwtv_dead ) ? __( 'alive', 'lwtv' ) : __( 'dead', 'lwtv' );
					$lwtv_status_segments = array(
						array(
							'label' => isset( $lwtv_dead_data[0] ) ? $lwtv_dead_data[0]['name'] : __( 'Alive', 'lwtv' ),
							'count' => $lwtv_alive,
							'pct'   => ( $lwtv_total_status > 0 ) ? round( ( $lwtv_alive / $lwtv_total_status ) * 100, 1 ) : 0,
							'class' => 'green',
						),
						array(
							'label' => isset( $lwtv_dead_data[1] ) ? $lwtv_dead_data[1]['name'] : __( 'Dead', 'lwtv' ),
							'count' => $lwtv_dead,
							'pct'   => ( $lwtv_total_status > 0 ) ? round( ( $lwtv_dead / $lwtv_total_status ) * 100, 1 ) : 0,
							'class' => 'red',
						),
					);
					?>
					<p class="lwtv-actor-stats-caption">
						<?php esc_html_e( 'Statistics are updated daily.', 'lwtv' ); ?>
						<span class="lwtv-actor-stats-dot" aria-hidden="true"></span>
						<strong>
						<?php
						/* translators: %s: number of characters played. */
						printf( esc_html( _n( '%s character', '%s characters', $lwtv_char_count, 'lwtv' ) ), esc_html( number_format_i18n( $lwtv_char_count ) ) );
						?>
						</strong>
					</p>
					<div class="lwtv-actor-stats-grid">
						<?php
						$donut = array(
							'layout'     => 'compact',
							'segments'   => $lwtv_role_segments,
							'center'     => $lwtv_total_roles,
							'center_sub' => __( 'roles', 'lwtv' ),
							'eyebrow'    => __( 'Roles', 'lwtv' ),
						);
						// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
						include LWTV_PLUGIN_PATH . '/php/statistics/templates/partials/donut.php';

						$donut = array(
							'layout'        => 'compact',
							'segments'      => $lwtv_status_segments,
							'center_pct'    => (int) round( $lwtv_status_pct ),
							'center_family' => $lwtv_status_fam,
							'center_sub'    => $lwtv_status_sub,
							'eyebrow'       => __( 'Status', 'lwtv' ),
						);
						// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
						include LWTV_PLUGIN_PATH . '/php/statistics/templates/partials/donut.php';
						?>
					</div>
					<p><em><small><?php esc_html_e( 'Note: character roles may exceed the number of characters played, if the character appeared on multiple TV shows.', 'lwtv' ); ?></small></em></p>
					<?php
				}
				?>
			</div>
		</div>
	</div>
</div>
