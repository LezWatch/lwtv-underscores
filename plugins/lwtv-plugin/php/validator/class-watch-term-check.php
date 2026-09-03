<?php
/*
 * Validation: Watch Term Check For LezWatch.TV
 *
 * Shows what `wp lwtv debug watchurls` last found: provider terms whose URLs no
 * longer work, or no longer point at the provider we think they do.
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
use LWTV\Debugger\Findings_Store;
use LWTV\Debugger\Format\Triage;
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
	 * admin-post action for re-checking a single flagged URL.
	 *
	 * Separate from ACTION_RECHECK: that one is a budgeted sweep over every
	 * flagged row, this is one request against one row, for when a term was
	 * just fixed -- or deleted -- and there is no reason to wait on the rest
	 * of the list to find out.
	 */
	const ACTION_RECHECK_ONE = 'lwtv_watch_recheck_one_url';

	/**
	 * admin-post action for confirming a term's provider despite a
	 * published-name mismatch.
	 *
	 * Term-wide, not per-URL: the override lives on the term (see
	 * Watch_Hosts::META_CONFIRMED_NAME), so confirming from any one flagged
	 * row clears the name-mismatch review on every URL that term has.
	 */
	const ACTION_CONFIRM_PROVIDER = 'lwtv_watch_confirm_provider';

	/**
	 * admin-post action for retiring a provider term nothing reaches.
	 *
	 * Offered only on rows Triage put in the unused pile, because it is the only
	 * action that can clear one. Re-checking a URL on a term no show reaches
	 * cannot help: whether it answers or not, nothing is pointing at it.
	 *
	 * Deletes the term, which is why the handler re-derives the show count live
	 * rather than trusting the stored row, and refuses outright if the term
	 * still holds object relationships.
	 */
	const ACTION_RETIRE = 'lwtv_watch_retire_term';

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
		add_action( 'admin_post_' . self::ACTION_RECHECK_ONE, array( $this, 'handle_recheck_one' ) );
		add_action( 'admin_post_' . self::ACTION_CONFIRM_PROVIDER, array( $this, 'handle_confirm_provider' ) );
		add_action( 'admin_post_' . self::ACTION_RETIRE, array( $this, 'handle_retire' ) );
	}

	/**
	 * Render the tab.
	 *
	 * @return void
	 */
	public static function make(): void {
		self::show_notice();

		$items = Findings_Store::load( Watch_URLs::FINDINGS_PROBLEMS );

		// Instantiated, not called statically: Validation::last_run() reads a
		// static cache of the status option that only the constructor fills.
		$last_run = ( new Validation() )->last_run( Watch_URLs::STATUS_KEY );

		// No findings at all means the scan has never run, or has expired.
		// That is a different thing from "ran and found nothing", and saying
		// "Excellent!" to an empty store would be a lie.
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
	 * Split, not filtered. A broken URL on a term no published show reaches is a
	 * real finding, but it is a different finding with a different fix, and
	 * leaving it interleaved by severity is what made this report hard to work:
	 * the rows an editor can act on sank under rows where every button on offer
	 * was the wrong one. See Debugger\Format\Triage.
	 *
	 * @param array  $items    Findings.
	 * @param string $last_run Rendered last-run paragraph.
	 * @return void
	 */
	private static function render_findings( array $items, string $last_run ): void {
		$split     = Triage::by_impact( $items );
		$affecting = $split['affecting'];
		$unused    = $split['unused'];

		// Primed over everything: both tables render a Shows cell.
		Affected_Shows::prime( $items );

		self::render_summary( $affecting, $unused, $last_run );

		if ( ! empty( $affecting ) ) {
			self::render_table( $affecting );
		}

		self::render_recheck_form( count( $items ), count( $unused ) );

		if ( ! empty( $unused ) ) {
			self::render_unused( $unused );
		}
	}

	/**
	 * The count block above the tables.
	 *
	 * Every number here is counted off the rows being rendered rather than
	 * asserted, so the copy cannot outlive the data it describes -- including the
	 * case where everything flagged turns out to be on an unused term and there
	 * is no worklist at all.
	 *
	 * @param array  $affecting Rows on terms at least one published show reaches.
	 * @param array  $unused    Rows on terms nothing reaches.
	 * @param string $last_run  Rendered last-run paragraph.
	 * @return void
	 */
	private static function render_summary( array $affecting, array $unused, string $last_run ): void {
		// 'health', not 'status': status is the baseline's new/open/resolved now.
		$counts  = array_count_values( array_column( $affecting, 'health' ) );
		$broken  = (int) ( $counts[ Watch_Url_Health::STATUS_BROKEN ] ?? 0 );
		$live    = count( $affecting );
		$dormant = count( $unused );
		?>
		<div class="lwtv-tools-container lwtv-tools-container__alert">
			<h3>
				<span class="dashicons <?php echo esc_attr( $live ? 'dashicons-warning' : 'dashicons-yes' ); ?>"></span>
				<?php
				if ( $live ) {
					printf(
						/* translators: %d: number of URLs with problems that at least one show reaches. */
						esc_html( _n( '%d provider URL needs attention', '%d provider URLs need attention', $live, 'lwtv' ) ),
						absint( $live )
					);
				} else {
					esc_html_e( 'Nothing a reader can reach is broken', 'lwtv' );
				}
				?>
			</h3>
			<div id="lwtv-tools-alerts">
				<?php if ( $live ) { ?>
					<p>
						<?php
						printf(
							/* translators: 1: definitely broken, 2: findings on terms a show reaches. */
							esc_html__( '%1$d of %2$d are definitely broken. The rest answered, but something suggests they may not be the provider any more — a domain that changed hands still returns a healthy 200.', 'lwtv' ),
							absint( $broken ),
							absint( $live )
						);
						?>
					</p>
					<p><?php esc_html_e( 'Worst first, then by how many shows point at the term. Fixing a term fixes every show that reaches it.', 'lwtv' ); ?></p>
				<?php } else { ?>
					<p><?php esc_html_e( 'Everything flagged is on a provider term no published show reaches, so nothing on the front end is pointing at a dead link.', 'lwtv' ); ?></p>
				<?php } ?>
				<?php if ( $dormant ) { ?>
					<p>
						<?php
						printf(
							/* translators: %d: number of flagged URLs on terms no published show reaches. */
							esc_html( _n( '%d flagged URL is on a term no published show reaches. It is listed separately below, because re-checking it cannot clear it.', '%d flagged URLs are on terms no published show reaches. Those are listed separately below, because re-checking them cannot clear them.', $dormant, 'lwtv' ) ),
							absint( $dormant )
						);
						?>
					</p>
				<?php } ?>
				<?php echo wp_kses_post( $last_run ); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * One findings table.
	 *
	 * Both sections share it, and share all four columns -- including Shows,
	 * which is a constant zero in the unused table. Printing the zero is the
	 * point: it is the reason those rows are down there.
	 *
	 * @param array $rows   Display rows, already sorted.
	 * @param bool  $unused Whether these are rows nothing reaches, which decides
	 *                      whether the retire action is offered.
	 * @return void
	 */
	private static function render_table( array $rows, bool $unused = false ): void {
		?>
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
					foreach ( $rows as $row ) {
						++$number;
						self::render_row( (array) $row, 0 === $number % 2, $unused );
					}
					?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * The rows nothing reaches, and what to do about them.
	 *
	 * In a <details>, shut by default. These are findings worth keeping -- "this
	 * service is gone" is real information -- but they are not a worklist, and
	 * anything permanently expanded above the fold becomes the thing you scroll
	 * past to reach the work. Native element, no JavaScript, degrades to an open
	 * list, matching the no-JS stance the Watch Providers tab already takes.
	 *
	 * @param array $unused Rows on terms nothing reaches.
	 * @return void
	 */
	private static function render_unused( array $unused ): void {
		$terms = count( array_unique( array_column( $unused, 'id' ) ) );
		?>
		<details class="lwtv-watch-unused">
			<summary>
				<?php
				printf(
					/* translators: %d: number of provider terms no published show reaches. */
					esc_html( _n( '%d unused provider term', '%d unused provider terms', $terms, 'lwtv' ) ),
					absint( $terms )
				);
				?>
			</summary>
			<p class="description">
				<?php esc_html_e( 'No published show reaches these terms, so a dead URL here is not reaching a reader, and re-checking it cannot clear the row. The decision these need is whether the term should exist at all: a service that has shut down can be retired, which deletes the term and takes its rows off this report for good.', 'lwtv' ); ?>
			</p>
			<p class="description">
				<?php esc_html_e( 'Two different things read as zero here. A term that genuinely serves nothing, and a term that has lost its host — the losing side of a contested host counts zero, and so does a term whose stored URL cannot be parsed, because neither one ever enters the host map shows are matched through. Check the Watch Providers tab before retiring anything you expected to be in use.', 'lwtv' ); ?>
			</p>
			<?php self::render_table( $unused, true ); ?>
		</details>
		<?php
	}

	/**
	 * One table row.
	 *
	 * @param array $item   One finding.
	 * @param bool  $alt    Zebra striping.
	 * @param bool  $unused Whether this row is on a term nothing reaches.
	 * @return void
	 */
	private static function render_row( array $item, bool $alt, bool $unused = false ): void {
		$term_id = (int) ( $item['id'] ?? 0 );
		$edit    = $term_id ? get_edit_term_link( $term_id, Theme_Ways_To_Watch::TAXONOMY ) : '';
		$health  = (string) ( $item['health'] ?? Watch_Url_Health::STATUS_REVIEW );
		$labels  = self::labels();
		$label   = $labels[ $health ] ?? $labels[ Watch_Url_Health::STATUS_REVIEW ];
		$url     = (string) ( $item['url'] ?? '' );

		// Nothing to key a single-row recheck on without both, and the button
		// fires a live request -- gated behind the same capability as the bulk
		// form rather than just being read-only-page-visible.
		$recheck_link = ( $term_id && '' !== $url && current_user_can( self::CAP_MANAGE ) )
			? wp_nonce_url(
				add_query_arg(
					array(
						'action' => self::ACTION_RECHECK_ONE,
						'term'   => $term_id,
						'url'    => rawurlencode( $url ),
					),
					admin_url( 'admin-post.php' )
				),
				self::ACTION_RECHECK_ONE
			)
			: '';

		// Only offered for the one cause confirming the provider actually
		// clears: a published-name mismatch. A broken link or an off-site
		// redirect still needs a real fix, and this button would not touch
		// them -- Watch_URLs::drop_reason() only drops REASON_NAME_MISMATCH
		// rows.
		$confirm_link = ( $term_id && Watch_Url_Health::REASON_NAME_MISMATCH === (string) ( $item['reason'] ?? '' ) && current_user_can( self::CAP_MANAGE ) )
			? wp_nonce_url(
				add_query_arg(
					array(
						'action' => self::ACTION_CONFIRM_PROVIDER,
						'term'   => $term_id,
					),
					admin_url( 'admin-post.php' )
				),
				self::ACTION_CONFIRM_PROVIDER
			)
			: '';

		// The only action that can clear an unused row, and never offered on a
		// row that does affect shows -- there, deleting the term would take a
		// working provider name off the front end. Nonce is per-term rather than
		// per-action: this one deletes something.
		$retire_link = ( $term_id && $unused && current_user_can( self::CAP_MANAGE ) )
			? wp_nonce_url(
				add_query_arg(
					array(
						'action' => self::ACTION_RETIRE,
						'term'   => $term_id,
					),
					admin_url( 'admin-post.php' )
				),
				self::ACTION_RETIRE . '_' . $term_id
			)
			: '';
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
							<?php if ( '' !== $retire_link ) { ?>
								| <a href="<?php echo esc_url( $retire_link ); ?>" class="lwtv-watch-retire"><?php esc_html_e( 'Retire term', 'lwtv' ); ?></a>
							<?php } ?>
							<?php if ( '' !== $recheck_link ) { ?>
								| <a href="<?php echo esc_url( $recheck_link ); ?>"><?php esc_html_e( 'Re-check this URL', 'lwtv' ); ?></a>
							<?php } ?>
							<?php if ( '' !== $confirm_link ) { ?>
								| <a href="<?php echo esc_url( $confirm_link ); ?>"><?php esc_html_e( 'Confirm provider', 'lwtv' ); ?></a>
							<?php } ?>
						</span>
					</div>
				<?php } ?>
			</td>
			<td><?php Affected_Shows::cell( (array) ( $item['show_ids'] ?? array() ), (int) ( $item['shows'] ?? 0 ) ); ?></td>
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
	 * The count is every flagged URL, not just the worklist above: the re-check
	 * probes the whole findings store, unused terms included. Said out loud when
	 * there are any, so the number under the button cannot look like it disagrees
	 * with the table it sits beneath.
	 *
	 * @param int $count  How many findings there are in total.
	 * @param int $unused How many of those are on terms nothing reaches.
	 * @return void
	 */
	private static function render_recheck_form( int $count, int $unused = 0 ): void {
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
					/* translators: 1: number of flagged URLs, 2: seconds of budget, 3: WP-CLI command in a code element. */
					wp_kses_post( __( 'Re-probes the %1$d flagged URLs only, and drops any that now pass. Stops after about %2$d seconds; anything it did not reach is kept, not cleared. A full sweep of every term is %3$s.', 'lwtv' ) ),
					absint( $count ),
					absint( Watch_Hosts::UI_TIME_BUDGET ),
					'<code>wp lwtv debug watchurls --force</code>'
				);

				if ( $unused ) {
					echo ' ';
					printf(
						/* translators: %d: number of flagged URLs on terms no published show reaches. */
						esc_html( _n( 'That count includes the %d URL on an unused term, which a re-check will only clear if the URL has come back.', 'That count includes the %d URLs on unused terms, which a re-check will only clear if those URLs have come back.', $unused, 'lwtv' ) ),
						absint( $unused )
					);
				}
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

		// Cast, so a `false` read (never run, or expired) becomes the empty array
		// the guard below is looking for.
		$items = (array) Findings_Store::load( Watch_URLs::FINDINGS_PROBLEMS );

		if ( empty( $items ) ) {
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
	 * Re-check one flagged URL, and only that one.
	 *
	 * @return void
	 */
	public function handle_recheck_one(): void {
		check_admin_referer( self::ACTION_RECHECK_ONE );

		if ( ! current_user_can( self::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'lwtv' ), '', array( 'response' => 403 ) );
		}

		$term_id = isset( $_GET['term'] ) ? absint( $_GET['term'] ) : 0;
		$url     = isset( $_GET['url'] ) ? esc_url_raw( rawurldecode( wp_unslash( $_GET['url'] ) ) ) : '';

		$items  = (array) Findings_Store::load( Watch_URLs::FINDINGS_PROBLEMS );
		$target = null;

		foreach ( $items as $item ) {
			if ( (int) ( $item['id'] ?? 0 ) === $term_id && (string) ( $item['url'] ?? '' ) === $url ) {
				$target = $item;
				break;
			}
		}

		if ( null === $target ) {
			self::set_notice( 'info', __( 'That URL is no longer flagged — nothing to re-check.', 'lwtv' ) );
			self::redirect_back();
		}

		$result = ( new Watch_URLs() )->recheck_one( $items, $target, Watch_Hosts::UI_TIMEOUT );

		if ( $result['resolved'] ) {
			self::set_notice( 'success', __( 'That URL now passes and has been removed from the report.', 'lwtv' ) );
			self::redirect_back();
		}

		$problem = wp_strip_all_tags( (string) ( $result['row']['problem'] ?? '' ) );

		self::set_notice(
			'info',
			'' !== $problem
				? sprintf(
					/* translators: %s: the problem now reported for this URL. */
					__( 'Still flagged: %s', 'lwtv' ),
					$problem
				)
				: __( 'Still flagged. See the updated row below.', 'lwtv' )
		);

		self::redirect_back();
	}

	/**
	 * Confirm a term's provider despite a published-name mismatch.
	 *
	 * Term-wide: sets the ACF override on the term (the source of truth --
	 * an editor can also set or clear it from the term edit screen), then
	 * drops every currently-flagged name-mismatch row for that term so the
	 * report reflects it immediately rather than waiting for the next scan.
	 *
	 * @return void
	 */
	public function handle_confirm_provider(): void {
		check_admin_referer( self::ACTION_CONFIRM_PROVIDER );

		if ( ! current_user_can( self::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'lwtv' ), '', array( 'response' => 403 ) );
		}

		$term_id = isset( $_GET['term'] ) ? absint( $_GET['term'] ) : 0;

		if ( ! $term_id || ! get_term( $term_id, Theme_Ways_To_Watch::TAXONOMY ) ) {
			self::set_notice( 'error', __( 'That provider term no longer exists.', 'lwtv' ) );
			self::redirect_back();
		}

		update_term_meta( $term_id, Watch_Hosts::META_CONFIRMED_NAME, '1' );

		$items   = (array) Findings_Store::load( Watch_URLs::FINDINGS_PROBLEMS );
		$dropped = ( new Watch_URLs() )->drop_reason( $items, $term_id, Watch_Url_Health::REASON_NAME_MISMATCH );

		self::set_notice(
			'success',
			$dropped
				? __( 'Provider confirmed. Name-mismatch reviews for this term will no longer be flagged.', 'lwtv' )
				: __( 'Provider confirmed for future scans. Nothing currently flagged needed clearing.', 'lwtv' )
		);

		self::redirect_back();
	}

	/**
	 * Retire a provider term nothing reaches.
	 *
	 * Deletes the term, so the guards matter more than the action does:
	 *
	 *   - The show count is re-derived live, never read off the row. Findings are
	 *     as of the last sweep, and a term that has gained shows since must not be
	 *     deleted on the strength of a stale zero.
	 *   - A term still holding object relationships is refused. `lez_watch_urls`
	 *     is registered against shows (see CPTs\Shows::ALL_TAXONOMIES) even though
	 *     the front end resolves providers by host, so a term *can* be assigned to
	 *     posts -- and deleting it would drop those relationships silently, which
	 *     is not something a triage link gets to do.
	 *
	 * Both refusals say what they found rather than failing quietly, because the
	 * disagreement between the report and the live count is the interesting part.
	 *
	 * @return void
	 */
	public function handle_retire(): void {
		$term_id = isset( $_GET['term'] ) ? absint( $_GET['term'] ) : 0;

		check_admin_referer( self::ACTION_RETIRE . '_' . $term_id );

		if ( ! current_user_can( self::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'lwtv' ), '', array( 'response' => 403 ) );
		}

		$term = $term_id ? get_term( $term_id, Theme_Ways_To_Watch::TAXONOMY ) : null;

		if ( ! $term instanceof \WP_Term ) {
			self::set_notice( 'info', __( 'That provider term no longer exists — nothing to retire.', 'lwtv' ) );
			self::redirect_back();
		}

		$name = Theme_Ways_To_Watch::term_name( $term->name );
		$live = (int) ( Watch_Hosts::shows_per_term()[ $term_id ] ?? 0 );

		/*
		 * Not $term->count. That is maintained by _update_post_term_count(),
		 * which counts published posts only -- so a term assigned to a draft or
		 * pending show reports zero, and $live (host matching, also published
		 * only) reports zero too. Both guards would wave through a delete that
		 * silently drops a real relationship, which is the one thing this must
		 * not do. get_objects_in_term() counts the relationship rows themselves,
		 * whatever status the post is in.
		 */
		$objects  = get_objects_in_term( $term_id, Theme_Ways_To_Watch::TAXONOMY );
		$assigned = is_wp_error( $objects ) ? 0 : count( $objects );

		if ( is_wp_error( $objects ) ) {
			self::set_notice(
				'error',
				sprintf(
					/* translators: %s: provider name. */
					__( 'Could not check what “%s” is still assigned to, so it was not retired.', 'lwtv' ),
					$name
				)
			);
			self::redirect_back();
		}

		if ( $live > 0 ) {
			self::set_notice(
				'error',
				sprintf(
					/* translators: 1: provider name, 2: number of published shows. */
					_n( '“%1$s” now serves %2$d published show, so it was not retired. Re-check its URL instead.', '“%1$s” now serves %2$d published shows, so it was not retired. Re-check its URL instead.', $live, 'lwtv' ),
					$name,
					$live
				)
			);
			self::redirect_back();
		}

		if ( $assigned > 0 ) {
			self::set_notice(
				'error',
				sprintf(
					/* translators: 1: provider name, 2: number of posts the term is assigned to. */
					_n( '“%1$s” is still assigned to %2$d post, so it was not retired. Nothing reaches it by URL, but deleting it would drop that assignment — including on a draft or pending show. Unassign it first.', '“%1$s” is still assigned to %2$d posts, so it was not retired. Nothing reaches them by URL, but deleting it would drop those assignments — including on draft or pending shows. Unassign it first.', $assigned, 'lwtv' ),
					$name,
					$assigned
				)
			);
			self::redirect_back();
		}

		$deleted = wp_delete_term( $term_id, Theme_Ways_To_Watch::TAXONOMY );

		if ( is_wp_error( $deleted ) || true !== $deleted ) {
			self::set_notice(
				'error',
				sprintf(
					/* translators: %s: provider name. */
					__( '“%s” could not be retired.', 'lwtv' ),
					$name
				)
			);
			self::redirect_back();
		}

		// A term can hold several URLs, and every one of them was a separate
		// finding. All of them go with the term.
		$items   = (array) Findings_Store::load( Watch_URLs::FINDINGS_PROBLEMS );
		$dropped = ( new Watch_URLs() )->drop_term( $items, $term_id );

		self::set_notice(
			'success',
			sprintf(
				/* translators: 1: provider name, 2: number of rows removed from the report. */
				_n( 'Retired “%1$s” and removed %2$d row from the report.', 'Retired “%1$s” and removed %2$d rows from the report.', $dropped, 'lwtv' ),
				$name,
				$dropped
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
