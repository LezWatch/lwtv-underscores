<?php
/*
 * Dashboard Widget
 *
 * The "LezWatch.TV Tools" Data Health widget: validation problems grouped by
 * severity for editors, plus stalled checks for admins. Background jobs are
 * in the Site Pulse widget. See docs/architecture/validation-screen.md#dashboard-widget.
 */

namespace LWTV\_Components;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\Admin_Menu\Build\Health_Report;
use LWTV\Admin_Menu\Validation;

class Dashboard_Widgets implements Component {

	/*
	 * Construct
	 */
	public function init() {
		add_action( 'wp_dashboard_setup', array( $this, 'custom_dashboard_widgets' ) );
	}

	/**
	 * Custom Dashboard Widget
	 */
	public function custom_dashboard_widgets() {
		if ( current_user_can( 'upload_files' ) ) {
			wp_add_dashboard_widget( 'lwtv_data_check_widget', __( 'LezWatch.TV Tools', 'lwtv-underscores' ), array( $this, 'check_dashboard_content' ) );
		}
	}

	/**
	 * Check dashboard content
	 *
	 * Echos page.
	 *
	 * @return void
	 */
	public function check_dashboard_content() {
		$is_admin = current_user_can( 'manage_options' );
		$report   = Health_Report::build( Validation::health_checks(), time(), $is_admin );
		$labels   = array(
			'fix_now'   => __( 'Fix now', 'lwtv-underscores' ),
			'attention' => __( 'Needs attention', 'lwtv-underscores' ),
			'backlog'   => __( 'Backlog', 'lwtv-underscores' ),
		);
		?>
		<div class="main lwtv-health">
			<?php if ( ! empty( $report['stale'] ) ) : ?>
				<div class="lwtv-health__stale">
					<?php
					$many = count( $report['stale'] ) > 1;
					if ( $many ) :
						?>
						<p><strong><?php echo esc_html( Health_Report::stale_summary( count( $report['stale'] ) ) ); ?></strong></p>
						<details>
							<summary><?php esc_html_e( 'Show which', 'lwtv-underscores' ); ?></summary>
					<?php endif; ?>
					<ul>
						<?php foreach ( $report['stale'] as $stale ) : ?>
							<li>
								<?php
								if ( $stale['last'] ) {
									printf(
										/* translators: 1: check name, 2: date it last ran. */
										esc_html__( '%1$s hasn\'t run since %2$s.', 'lwtv-underscores' ),
										'<strong>' . esc_html( $stale['name'] ) . '</strong>',
										esc_html( get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $stale['last'] ), 'F j, Y' ) )
									);
								} else {
									printf(
										/* translators: %s: check name. */
										esc_html__( '%s has never run.', 'lwtv-underscores' ),
										'<strong>' . esc_html( $stale['name'] ) . '</strong>'
									);
								}
								?>
							</li>
						<?php endforeach; ?>
					</ul>
					<?php if ( $many ) : ?>
						</details>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<?php if ( $report['all_clear'] ) : ?>
				<p><?php esc_html_e( 'No issues found! Celebrate gay times!', 'lwtv-underscores' ); ?></p>
			<?php endif; ?>

			<?php foreach ( $report['tiers'] as $tier => $rows ) : ?>
				<h3 class="lwtv-health__tier lwtv-health__tier--<?php echo esc_attr( $tier ); ?>"><?php echo esc_html( $labels[ $tier ] ?? $tier ); ?></h3>
				<ul class="lwtv-health__rows">
					<?php
					foreach ( $rows as $row ) :
						$url   = add_query_arg(
							array(
								'page' => 'lwtv_data_check',
								'tab'  => 'tab_' . $row['link_tab'],
							),
							admin_url( 'admin.php' )
						);
						$trend = Health_Report::trend( $row['new'], $row['resolved'] );
						$pill  = $row['as_of'] ? 'lwtv-tools-pill lwtv-tools-pill--stale' : 'lwtv-tools-pill';
						?>
						<li class="lwtv-health__row">
							<a href="<?php echo esc_url( $url ); ?>" class="lwtv-health__name"><?php echo esc_html( $row['name'] ); ?></a>
							<span class="lwtv-health__figures">
								<?php if ( $row['as_of'] && $row['last'] ) : ?>
									<span class="lwtv-health__trend">
										<?php
										printf(
											/* translators: %s: human-readable time difference, e.g. "3 days". */
											esc_html__( 'as of %s ago', 'lwtv-underscores' ),
											esc_html( human_time_diff( $row['last'] ) )
										);
										?>
									</span>
								<?php elseif ( '' !== $trend ) : ?>
									<span class="lwtv-health__trend"><?php echo esc_html( $trend ); ?></span>
								<?php endif; ?>
								<span class="<?php echo esc_attr( $pill ); ?>"><?php echo esc_html( number_format_i18n( $row['count'] ) ); ?></span>
							</span>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endforeach; ?>

			<?php if ( $report['last_checked'] ) : ?>
				<p class="lwtv-health__asof">
					<?php
					printf(
						/* translators: %s: date the newest check ran. */
						esc_html__( 'Last checked %s', 'lwtv-underscores' ),
						esc_html( get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $report['last_checked'] ), 'F j, Y' ) )
					);
					?>
				</p>
			<?php else : ?>
				<p class="lwtv-health__asof"><?php esc_html_e( 'The tools have not been run yet.', 'lwtv-underscores' ); ?></p>
			<?php endif; ?>

			<a href="<?php echo esc_url( admin_url( 'admin.php?page=lwtv_data_check' ) ); ?>" class="button-secondary"><?php esc_html_e( 'Go to the Tools Dashboard', 'lwtv-underscores' ); ?></a>
		</div>
		<?php
	}
}
