<?php
/*
 * Validation: Watch Term Check For LezWatch.TV
 *
 * Shows what `wp lwtv debug watchurls` last found: provider terms whose URLs no
 * longer work, or no longer point at the provider we think they do.
 *
 * This tab does not run a full scan. Checking every term URL means a few hundred
 * HTTP requests, which is a cron job, not a page load -- so the sweep lives in
 * `wp lwtv debug watchurls` and this reads the findings it leaves behind. The
 * one thing it will do live is re-check the URLs already flagged, which is a
 * short list and is exactly what you want after fixing one. That is bounded by
 * wall clock, not by count, because slow hosts are the failure mode here.
 *
 * Findings are keyed to terms, not posts, so Validation::table_content() (which
 * assumes post IDs and calls get_the_title()) can't render them.
 */

namespace LWTV\Validator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\Admin_Menu\Validation;
use LWTV\CPTs\Shows\Watching\Watch_Hosts;
use LWTV\CPTs\Shows\Watching\Watch_Url_Health;
use LWTV\Debugger\Watch_URLs;
use LWTV\Schedulers\Watch_URLs_Task;
use LWTV\Theme\Ways_To_Watch as Theme_Ways_To_Watch;

class Watch_Term_Check {

	/**
	 * admin-post action for the bounded re-check.
	 */
	const ACTION_RECHECK = 'lwtv_watch_recheck_urls';

	/**
	 * admin-post action for queueing a full sweep.
	 *
	 * Separate from ACTION_RECHECK because they are different shapes: a re-check
	 * probes a bounded subset inside this request, a scan queues the lot to run
	 * elsewhere.
	 */
	const ACTION_SCAN = 'lwtv_watch_scan_urls';

	/**
	 * Transient prefix for one-shot admin notices, per user.
	 */
	const NOTICE_PREFIX = 'lwtv_watchcheck_notice_';

	/**
	 * Capability needed to trigger a re-check.
	 *
	 * Reading the report only needs the page's own cap; making the site go and
	 * fetch a few dozen third-party URLs should need more than that.
	 */
	const CAP_MANAGE = 'manage_categories';

	/**
	 * Dashicon and label per outcome.
	 *
	 * A method rather than a const because the labels are translated, and a
	 * const can't call __().
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	private static function labels(): array {
		return array(
			Watch_Url_Health::STATUS_BROKEN  => array( 'dashicons-no', __( 'Broken', 'lwtv' ) ),
			Watch_Url_Health::STATUS_REVIEW  => array( 'dashicons-warning', __( 'Needs review', 'lwtv' ) ),
			Watch_Url_Health::STATUS_BLOCKED => array( 'dashicons-shield', __( 'Blocked us', 'lwtv' ) ),
		);
	}

	/**
	 * Hook the admin-post handler.
	 *
	 * Must be called from somewhere that runs on every admin request --
	 * Admin_Menu\Validation::init() fires on `admin_menu`, which admin-post.php
	 * never triggers.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_post_' . self::ACTION_RECHECK, array( $this, 'handle_recheck' ) );
		add_action( 'admin_post_' . self::ACTION_SCAN, array( $this, 'handle_scan' ) );
	}

	/**
	 * Render the tab.
	 *
	 * @return void
	 */
	public static function make(): void {
		self::show_notice();

		$items = lwtv_plugin()->get_stored( Watch_URLs::TRANSIENT_PROBLEMS );

		// Instantiated, not called statically: Validation::last_run() reads a
		// static cache of the status option that only the constructor fills.
		$last_run = ( new Validation() )->last_run( Watch_URLs::STATUS_KEY );

		// No transient at all means the scan has never run, or has expired.
		// That is a different thing from "ran and found nothing", and saying
		// "Excellent!" to an empty cache would be a lie.
		if ( false === $items ) {
			self::render_never_run();
			return;
		}

		if ( ! is_array( $items ) || empty( $items ) ) {
			self::render_all_clear( $last_run );
			return;
		}

		self::render_findings( $items, $last_run );
	}

	/**
	 * Nothing cached: point at the command that fills it.
	 *
	 * @return void
	 */
	private static function render_never_run(): void {
		$queued = Watch_URLs_Task::is_queued();
		?>
		<div class="lwtv-tools-container lwtv-tools-container__alert">
			<h3>
				<span class="dashicons dashicons-info"></span>
				<?php
				if ( $queued ) {
					esc_html_e( 'Scan running', 'lwtv' );
				} else {
					esc_html_e( 'No results yet', 'lwtv' );
				}
				?>
			</h3>
			<div id="lwtv-tools-alerts">
				<?php if ( $queued ) : ?>
					<p><?php esc_html_e( 'A sweep is queued and will run in the background. Reload this page in a few minutes — the results appear here when it finishes.', 'lwtv' ); ?></p>
				<?php else : ?>
					<p><?php esc_html_e( 'This check has not run, or its results have expired.', 'lwtv' ); ?></p>
					<p>
						<?php esc_html_e( 'It makes one request per provider URL, which is too slow for a page load, so pressing Run Scan queues it to run in the background rather than making you wait. On the command line:', 'lwtv' ); ?>
						<code>wp lwtv debug watchurls --force</code>
					</p>
				<?php endif; ?>
			</div>
		</div>

		<?php
		if ( ! $queued ) {
			self::render_scan_form();
		}
	}

	/**
	 * Run Scan, for when there is nothing cached.
	 *
	 * Queues the sweep instead of running it, which is the only honest way to
	 * offer this button: a hundred-odd HTTP requests will not fit in a page
	 * request, and the other validator tabs get away with scanning on render only
	 * because their scans are SQL.
	 *
	 * Absent entirely when Action Scheduler is not available, rather than
	 * offering a button that would queue into nothing.
	 *
	 * @return void
	 */
	private static function render_scan_form(): void {
		if ( ! current_user_can( self::CAP_MANAGE ) || ! Watch_URLs_Task::available() ) {
			return;
		}
		?>
		<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
			<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_SCAN ); ?>" />
			<?php wp_nonce_field( self::ACTION_SCAN ); ?>
			<p class="submit">
				<?php submit_button( __( 'Run Scan', 'lwtv' ), 'primary', '', false ); ?>
			</p>
			<p class="description">
				<?php esc_html_e( 'Checks every URL on every provider term. Runs in the background; come back in a few minutes.', 'lwtv' ); ?>
			</p>
		</form>
		<?php
	}

	/**
	 * Queue a full sweep.
	 *
	 * @return void
	 */
	public function handle_scan(): void {
		check_admin_referer( self::ACTION_SCAN );

		if ( ! current_user_can( self::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'lwtv' ), '', array( 'response' => 403 ) );
		}

		if ( ! Watch_URLs_Task::available() ) {
			self::set_notice(
				'error',
				sprintf(
					/* translators: %s: WP-CLI command in a code element. */
					__( 'Action Scheduler is not available, so the sweep cannot be queued. Run %s instead.', 'lwtv' ),
					'<code>wp lwtv debug watchurls --force</code>'
				)
			);
			self::redirect_back();
		}

		if ( Watch_URLs_Task::queue() ) {
			self::set_notice( 'success', __( 'Sweep queued. It runs in the background — reload in a few minutes.', 'lwtv' ) );
		} else {
			self::set_notice( 'info', __( 'A sweep is already queued.', 'lwtv' ) );
		}

		self::redirect_back();
	}

	/**
	 * Ran, found nothing.
	 *
	 * @param string $last_run Rendered last-run paragraph.
	 * @return void
	 */
	private static function render_all_clear( string $last_run ): void {
		?>
		<div class="lwtv-tools-container lwtv-tools-container__alert">
			<h3><span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'Excellent!', 'lwtv' ); ?></h3>
			<div id="lwtv-tools-alerts">
				<p><?php esc_html_e( 'Every URL on every watch provider term answered, stayed on its own domain, and still calls itself something we recognise.', 'lwtv' ); ?></p>
				<?php echo wp_kses_post( $last_run ); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Ran, found problems.
	 *
	 * @param array  $items    Findings.
	 * @param string $last_run Rendered last-run paragraph.
	 * @return void
	 */
	private static function render_findings( array $items, string $last_run ): void {
		// 'health', not 'status': status is the baseline's new/open/resolved now.
		$counts = array_count_values( array_column( $items, 'health' ) );
		$broken = (int) ( $counts[ Watch_Url_Health::STATUS_BROKEN ] ?? 0 );
		?>
		<div class="lwtv-tools-container lwtv-tools-container__alert">
			<h3>
				<span class="dashicons dashicons-warning"></span>
				<?php
				printf(
					/* translators: %d: number of URLs with problems. */
					esc_html( _n( '%d provider URL needs attention', '%d provider URLs need attention', count( $items ), 'lwtv' ) ),
					count( $items )
				);
				?>
			</h3>
			<div id="lwtv-tools-alerts">
				<p>
					<?php
					printf(
						/* translators: 1: definitely broken, 2: total findings. */
						esc_html__( '%1$d of %2$d are definitely broken. The rest answered, but something suggests they may not be the provider any more — a domain that changed hands still returns a healthy 200.', 'lwtv' ),
						absint( $broken ),
						count( $items )
					);
					?>
				</p>
				<p><?php esc_html_e( 'Worst first, then by how many shows point at the term. Fixing a term fixes every show that reaches it.', 'lwtv' ); ?></p>
				<?php echo wp_kses_post( $last_run ); ?>
			</div>
		</div>

		<div class="lwtv-tools-table">
			<table class="widefat fixed" cellspacing="0">
				<thead><tr>
					<th class="manage-column column-title column-primary" scope="col"><?php esc_html_e( 'Provider', 'lwtv' ); ?></th>
					<th class="manage-column column-comments num" scope="col"><?php esc_html_e( 'Shows', 'lwtv' ); ?></th>
					<th class="manage-column column-author" scope="col"><?php esc_html_e( 'Result', 'lwtv' ); ?></th>
					<th class="manage-column column-problem" scope="col"><?php esc_html_e( 'Problem', 'lwtv' ); ?></th>
				</tr></thead>
				<tbody>
					<?php
					$number = 0;
					foreach ( $items as $item ) {
						++$number;
						self::render_row( $item, 0 === $number % 2 );
					}
					?>
				</tbody>
			</table>
		</div>

		<?php
		self::render_recheck_form( count( $items ) );
	}

	/**
	 * One table row.
	 *
	 * @param array $item One finding.
	 * @param bool  $alt  Zebra striping.
	 * @return void
	 */
	private static function render_row( array $item, bool $alt ): void {
		$term_id = (int) ( $item['id'] ?? 0 );
		$edit    = $term_id ? get_edit_term_link( $term_id, Theme_Ways_To_Watch::TAXONOMY ) : '';
		$health  = (string) ( $item['health'] ?? Watch_Url_Health::STATUS_REVIEW );
		$labels  = self::labels();
		$label   = $labels[ $health ] ?? $labels[ Watch_Url_Health::STATUS_REVIEW ];
		$url     = (string) ( $item['url'] ?? '' );
		?>
		<tr class="<?php echo esc_attr( $alt ? 'alternate' : '' ); ?>">
			<td>
				<strong>
					<?php if ( $edit ) { ?>
						<a href="<?php echo esc_url( $edit ); ?>" target="_blank" rel="noopener"><?php echo esc_html( (string) ( $item['term'] ?? '' ) ); ?></a>
					<?php } else { ?>
						<?php echo esc_html( (string) ( $item['term'] ?? '' ) ); ?>
					<?php } ?>
				</strong>
				<?php if ( '' !== $url ) { ?>
					<div class="row-actions">
						<span class="view">
							<a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer nofollow"><?php echo esc_html( $url ); ?></a>
						</span>
					</div>
				<?php } ?>
			</td>
			<td><?php echo esc_html( (string) ( (int) ( $item['shows'] ?? 0 ) ) ); ?></td>
			<td>
				<span class="dashicons <?php echo esc_attr( $label[0] ); ?>"></span>
				<?php echo esc_html( $label[1] ); ?>
			</td>
			<td><?php echo esc_html( (string) ( $item['problem'] ?? '' ) ); ?></td>
		</tr>
		<?php
	}

	/**
	 * The bounded "check these again" form.
	 *
	 * @param int $count How many findings there are.
	 * @return void
	 */
	private static function render_recheck_form( int $count ): void {
		if ( ! current_user_can( self::CAP_MANAGE ) ) {
			?>
			<p><em><?php esc_html_e( 'You need permission to manage categories to re-check these URLs.', 'lwtv' ); ?></em></p>
			<?php
			return;
		}
		?>
		<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" style="margin: 1em 0;">
			<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_RECHECK ); ?>" />
			<?php wp_nonce_field( self::ACTION_RECHECK ); ?>
			<?php submit_button( __( 'Re-check these URLs', 'lwtv' ), 'primary', '', false ); ?>
			<span class="description">
				<?php
				printf(
					/* translators: 1: number of flagged URLs, 2: seconds of budget. */
					wp_kses_post( __( 'Re-probes the %1$d flagged URLs only, and drops any that now pass. Stops after about %2$d seconds; anything it did not reach is kept, not cleared. A full sweep of every term is %3$s.', 'lwtv' ) ),
					absint( $count ),
					absint( Watch_Hosts::UI_TIME_BUDGET ),
					'<code>wp lwtv debug watchurls --force</code>'
				);
				?>
			</span>
		</form>
		<?php
	}

	/**
	 * Re-check the flagged URLs.
	 *
	 * @return void
	 */
	public function handle_recheck(): void {
		check_admin_referer( self::ACTION_RECHECK );

		if ( ! current_user_can( self::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'lwtv' ), '', array( 'response' => 403 ) );
		}

		$items = (array) lwtv_plugin()->get_stored( Watch_URLs::TRANSIENT_PROBLEMS );

		if ( is_string( $items ) || ! is_array( $items ) || empty( $items ) ) {
			self::set_notice(
				'info',
				sprintf(
					/* translators: %s: WP-CLI command in a code element. */
					__( 'There is nothing flagged to re-check. Run %s for a full sweep.', 'lwtv' ),
					'<code>wp lwtv debug watchurls --force</code>'
				)
			);
			self::redirect_back();
		}

		// Belt: ask for more head-room where the host allows it. Braces: the
		// budget passed below, since set_time_limit can be disabled and does
		// nothing for a web-server or proxy timeout anyway.
		if ( function_exists( 'set_time_limit' ) ) {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			@set_time_limit( Watch_Hosts::UI_TIME_BUDGET * 2 );
		}

		$before = count( $items );
		$after  = ( new Watch_URLs() )->find_bad_watch_urls( $items, Watch_Hosts::UI_TIMEOUT, Watch_Hosts::UI_TIME_BUDGET );
		$fixed  = max( 0, $before - count( $after ) );

		if ( empty( $after ) ) {
			self::set_notice( 'success', __( 'Every flagged URL now passes. Nothing left to look at.', 'lwtv' ) );
			self::redirect_back();
		}

		self::set_notice(
			$fixed ? 'success' : 'info',
			sprintf(
				/* translators: 1: URLs that now pass, 2: URLs still flagged. */
				__( '%1$d URL(s) now pass; %2$d still flagged.', 'lwtv' ),
				$fixed,
				count( $after )
			)
		);

		self::redirect_back();
	}

	/**
	 * Stash a one-shot notice for the current user.
	 *
	 * @param string $type    'success', 'error' or 'info'.
	 * @param string $message Text.
	 * @return void
	 */
	private static function set_notice( string $type, string $message ): void {
		set_transient(
			self::NOTICE_PREFIX . get_current_user_id(),
			array(
				'type'    => $type,
				'message' => $message,
			),
			MINUTE_IN_SECONDS * 5
		);
	}

	/**
	 * Print and clear any pending notice.
	 *
	 * @return void
	 */
	private static function show_notice(): void {
		$key    = self::NOTICE_PREFIX . get_current_user_id();
		$notice = get_transient( $key );

		if ( ! is_array( $notice ) || empty( $notice['message'] ) ) {
			return;
		}

		delete_transient( $key );

		$class = 'error' === $notice['type'] ? 'notice-error' : ( 'info' === $notice['type'] ? 'notice-info' : 'notice-success' );
		?>
		<div class="notice <?php echo esc_attr( $class ); ?> is-dismissible">
			<?php
			// wp_kses_post, not esc_html: these messages are ours and some carry a
			// <code> element naming a WP-CLI command. Nothing here is user input.
			?>
			<p><?php echo wp_kses_post( $notice['message'] ); ?></p>
		</div>
		<?php
	}

	/**
	 * Back to the tab.
	 *
	 * @return void
	 */
	private static function redirect_back(): void {
		wp_safe_redirect( admin_url( 'admin.php?page=lwtv_data_check&tab=tab_watch_term_check' ) );
		exit;
	}
}
