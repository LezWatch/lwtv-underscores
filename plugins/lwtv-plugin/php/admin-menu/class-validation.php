<?php
/*
 * Data Sync Checks For LezWatch.TV
 *
 * @since 2.4
 */

namespace LWTV\Admin_Menu;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\Validator\Report;
use LWTV\CPTs\Shows\Watching\Watch_Hosts as CPT_Watch_Hosts;
use LWTV\Validator\Watch_Providers;
use LWTV\Validator\Watch_Term_Check;

use LWTV\Debugger\Actors;
use LWTV\Debugger\Build\Baseline;
use LWTV\Debugger\Build\Findings;
use LWTV\Debugger\Characters;
use LWTV\Debugger\Dupes;
use LWTV\Debugger\Findings_Store;
use LWTV\Debugger\OnAir;
use LWTV\Debugger\Queers;
use LWTV\Debugger\Repair;
use LWTV\Debugger\Shows;
use LWTV\Debugger\Status;
use LWTV\Debugger\Watch_Host_Collisions;
use LWTV\Debugger\Watch_URLs;

class Validation {

	/**
	 * Cached copy of the debugger status option.
	 */
	private static $options = array();

	/**
	 * Request-level memo of tab_counts().
	 *
	 * The tab picker and the intro's Current Status table both want it, and it
	 * reads one option per check.
	 *
	 * @var array<string, array{count: int, new: int, cached: bool, stored: int, last: int}>|null
	 */
	private static $counts = null;

	/**
	 * Tool Tabs
	 *
	 * One entry per tab, and for the report tabs this is the whole definition:
	 * Validator\Report renders them all from this config, so a check cannot have
	 * a tab without a scanner or copy. That is what stops 1.2 (findings keys
	 * drifting) and 1.6 (tabs with nothing behind them) recurring.
	 *
	 * - name / desc: shown in the tab picker and the intro.
	 * - option:      key inside the debugger status option. Drives the badge and
	 *                the "last run" line.
	 * - findings:    where the findings live.
	 * - scanner:     array( class, method ) to produce fresh findings. Takes an
	 *                optional findings array, for a recheck.
	 * - column:      heading for the first table column.
	 * - clean:       what to say when there is nothing to report.
	 * - dirty:       array( singular sentence, plural sentence ).
	 * - note:        optional extra paragraph.
	 * - render:      for the two tabs that are not findings reports.
	 */
	private const TOOL_TABS = array(
		'queer_checker'     => array(
			'name'     => 'QIRL Characters have Queer Actors',
			'desc'     => 'Checks that all characters with queer actors have the queer cliché, and all actors with queer characters are, in fact, queer.',
			'option'   => 'queercheck',
			'findings' => Queers::FINDINGS_QUEERCHECK,
			'scanner'  => array( Queers::class, 'find_queer_chars' ),
			'column'   => 'Character',
			'clean'    => "Every character's queerness matches their actors.",
			'dirty'    => array(
				'The following character needs your attention. Please edit the actor or character queerness as indicated.',
				'The following characters need your attention. Please edit the actor or character queerness as indicated.',
			),
		),
		'dupe_checker'      => array(
			'name'     => 'Duplicate Actors and Shows',
			'desc'     => 'Actors and Shows that are duplicates.',
			'option'   => 'duplicates',
			'findings' => Dupes::FINDINGS_DUPES,
			'scanner'  => array( Dupes::class, 'find_duplicates' ),
			'column'   => 'Duplicate',
			'clean'    => 'We have no duplicate content!',
			'dirty'    => array(
				"The following duplicate has been found. Please review and update as needed. If the flagged show/actor is not a duplicate, edit it and check the 'Not a Duplicate' flag.",
				"The following duplicates have been found. Please review and update as needed. If a flagged show/actor is not a duplicate, edit it and check the 'Not a Duplicate' flag.",
			),
		),
		'byq_checker'       => array(
			'name'     => 'Bury Your Queers',
			'desc'     => 'Checks all characters with death cliché have proper death year meta data and shows have dead-queers trope. This may be okay, because Sara Lance.',
			'option'   => 'byq_problems',
			'findings' => Characters::FINDINGS_BYQ,
			'scanner'  => array( Characters::class, 'find_byq_problems' ),
			'column'   => 'Character',
			'clean'    => 'All the death data looks good and the data looks sane.',
			'dirty'    => array(
				'The following character needs your attention.',
				'The following characters need your attention.',
			),
		),
		'actor_checker'     => array(
			'name'     => 'Actors Info',
			'desc'     => 'Checks that all information for actors appears correct. This includes social media and links.',
			'option'   => 'actor_problems',
			'findings' => Actors::FINDINGS_PROBLEMS,
			'scanner'  => array( Actors::class, 'find_actors_problems' ),
			'column'   => 'Actor',
			'clean'    => 'Every actor has at least one character and their data looks sane.',
			'dirty'    => array(
				'The following actor needs your attention.',
				'The following actors need your attention.',
			),
		),
		'character_checker' => array(
			'name'     => 'Characters Info',
			'desc'     => 'Checks that all information for characters appears correct, like if they have a show and years-on-air added.',
			'option'   => 'character_problems',
			'findings' => Characters::FINDINGS_PROBLEMS,
			'scanner'  => array( Characters::class, 'find_characters_problems' ),
			'column'   => 'Character',
			'clean'    => 'All characters look good and their data looks sane. Even Sara Lance.',
			'dirty'    => array(
				'The following character needs your attention.',
				'The following characters need your attention.',
			),
		),
		'show_checker'      => array(
			'name'     => 'Shows Info',
			'desc'     => 'Checks that all information for shows appears correct. Like do they have characters and ratings etc, does intersectionality seem to match.',
			'option'   => 'show_problems',
			'findings' => Shows::FINDINGS_PROBLEMS,
			'scanner'  => array( Shows::class, 'find_shows_problems' ),
			'column'   => 'Show',
			'clean'    => 'All shows look good and the data looks sane.',
			'dirty'    => array(
				'The following show needs your attention.',
				'The following shows need your attention.',
			),
			'note'     => 'Note: Remember that intersectionality is meant to be a <em>positive</em> representation. If it\'s bad disability rep (like Grey\'s Anatomy with Arizona), do not list them.',
		),
		'actor_empty'       => array(
			'name'     => 'Incomplete Actors',
			'desc'     => 'Actors with no photo or no biography. A completeness report rather than a fault report - a brand new actor legitimately has neither yet.',
			'option'   => 'actor_empty',
			'findings' => Actors::FINDINGS_EMPTY,
			'scanner'  => array( Actors::class, 'find_actors_incomplete' ),
			'column'   => 'Actor',
			'clean'    => 'Every actor has a photo and a biography.',
			'dirty'    => array(
				'The following actor is missing a photo, a biography, or both.',
				'The following actors are missing a photo, a biography, or both.',
			),
		),
		'actor_imdb'        => array(
			'name'     => 'Actors missing IMDb',
			'desc'     => 'Actors who have no IMDb value. This may actually be okay as not all webseries/international shows are listed.',
			'option'   => 'actor_imdb',
			'findings' => Actors::FINDINGS_IMDB,
			'scanner'  => array( Actors::class, 'find_actors_no_imdb' ),
			'column'   => 'Actor',
			'clean'    => 'All actors have an IMDb entry.',
			'dirty'    => array(
				"The following actor has invalid IMDb data, or none at all. Not all will be possible to fix, as many webseries and international shows aren't listed on IMDb.",
				"The following actors have invalid IMDb data, or none at all. Not all will be possible to fix, as many webseries and international shows aren't listed on IMDb.",
			),
		),
		'show_imdb'         => array(
			'name'     => 'Shows missing IMDb',
			'desc'     => 'Shows that have no IMDb value. This may actually be okay as not all webseries/international shows are listed.',
			'option'   => 'show_imdb',
			'findings' => Shows::FINDINGS_IMDB,
			'scanner'  => array( Shows::class, 'find_shows_no_imdb' ),
			'column'   => 'Show',
			'clean'    => 'All shows have an IMDb entry. (Web series are exempt from this check, so some may still have none.)',
			'dirty'    => array(
				"The following show has invalid IMDb data, or none at all. Not all will be possible to fix, as many webseries and international shows aren't listed on IMDb.",
				"The following shows have invalid IMDb data, or none at all. Not all will be possible to fix, as many webseries and international shows aren't listed on IMDb.",
			),
		),
		'onair_checker'     => array(
			'name'     => 'On Air',
			'desc'     => 'Checks that all shows have the correct on-air status.',
			'option'   => 'onair_problems',
			'findings' => OnAir::FINDINGS_PROBLEMS,
			'scanner'  => array( OnAir::class, 'find_on_air_problems' ),
			// Was "Duplicate", copy-pasted from the duplicates view.
			'column'   => 'Show',
			'clean'    => 'All shows have the correct on-air status.',
			'dirty'    => array(
				'The following show has an on-air status that does not match its airdates. Please review and update as needed.',
				'The following shows have on-air statuses that do not match their airdates. Please review and update as needed.',
			),
		),
		'watch_providers'   => array(
			'name'     => 'Watch Providers',
			'desc'     => 'Ways to Watch hosts with no provider term, so the front end is guessing their name. Assign an existing term or create one.',
			/*
			 * Counts hosts with no term, written by Watch_Hosts::scan_unregistered().
			 *
			 * An earlier version badged *contested* hosts instead, on the grounds
			 * that a number which never reaches zero stops being read. Host
			 * matching took that number from ~130 to 35 and it now falls as the
			 * list is worked, so it is worth seeing. Contested hosts keep their own
			 * status entry from the `watchhosts` check and their own section at
			 * the top of the tab, which is louder than a badge anyway.
			 */
			'option'   => CPT_Watch_Hosts::STATUS_KEY,
			'findings' => CPT_Watch_Hosts::FINDINGS_UNREGISTERED,
			'render'   => array( Watch_Providers::class, 'make' ),
		),
		'watch_hosts'       => array(
			/*
			 * A real check with a real count -- weekly cron, `wp lwtv debug
			 * watchhosts` -- whose findings are rendered inside the Watch
			 * Providers tab rather than on a page of their own, because a
			 * contested host and a host with no term are the same editor's
			 * problem in the same sitting.
			 *
			 * `show_tab => false` keeps it out of the picker, since a dropdown
			 * option leading nowhere would be worse than no option. `tab` says
			 * where its row links instead.
			 */
			'name'     => 'Contested Watch Hosts',
			'desc'     => 'Hosts claimed by more than one provider term. The front end has to pick one, and it picks whichever sorts first by name — stable, but arbitrary. Reported on the Watch Providers tab.',
			'option'   => Watch_Host_Collisions::STATUS_KEY,
			'findings' => Watch_Host_Collisions::FINDINGS_PROBLEMS,
			'show_tab' => false,
			'tab'      => 'watch_providers',
		),
		'watch_term_check'  => array(
			'name'     => 'Watch Term Check',
			'desc'     => 'The other half of Watch Providers: of the terms we do have, do their URLs still work and still belong to that provider? A shut-down service whose domain was resold still answers HTTP 200.',
			'option'   => Watch_URLs::STATUS_KEY,
			'findings' => Watch_URLs::FINDINGS_PROBLEMS,
			'render'   => array( Watch_Term_Check::class, 'make' ),
		),
	);

	public function __construct() {
		self::$options = Status::all();
	}

	/**
	 * Setup dashboard.
	 *
	 * @return void
	 */
	public function init() {
		add_submenu_page( 'lwtv', 'Data Validation', 'Data Validation', 'upload_files', 'lwtv_data_check', array( $this, 'settings_page' ) );
	}

	/**
	 * Last Run
	 *
	 * @param  string $tool
	 * @return string When was a tool last run.
	 */
	public static function last_run( $tool ) {
		$options = is_array( self::$options ) ? self::$options : Status::all();

		// Get the timestamp from the individual last run OR the global.
		if ( 'intro' !== $tool && isset( $options[ $tool ]['last'] ) ) {
			$timestamp = (int) $options[ $tool ]['last'];
			$tool      = 'checker';
		} else {
			$timestamp = (int) ( $options['timestamp'] ?? 0 );
		}

		// Nothing has ever run (fresh install, or the status option was reset).
		if ( ! $timestamp ) {
			return '<p>The ' . str_replace( '_', ' ', $tool ) . ' has not been run yet.</p>';
		}

		$last_run_time = '<strong>' . get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $timestamp ), 'F j, Y H:i:s' ) . '</strong> (' . human_time_diff( $timestamp ) . ' ago).';
		$last_run_echo = '<p>The ' . str_replace( '_', ' ', $tool ) . ' was last run on ' . $last_run_time . '</p>';

		return $last_run_echo;
	}

	/**
	 * Outstanding count per tab, taken from the findings each tab renders.
	 *
	 * The badge in the picker, the Current Status table on the intro, and the tab
	 * body itself all read this, so none of them can contradict another.
	 *
	 * **Counted from the findings themselves, not from the status option.** The
	 * status option never expires and the findings do, so a count read from the
	 * option could advertise a check whose detail had gone -- which is exactly
	 * what "Watch Term Check (47)" over an empty report was. Nine tabs hid it by
	 * silently re-scanning when the findings were missing; the one check too slow
	 * to do that showed it plainly.
	 *
	 * No more expensive than the status option it replaced: both are one
	 * non-autoloaded option per check. Memoised anyway, because the picker and the
	 * intro table both want it.
	 *
	 * `cached` is the third state the count alone cannot express: an empty array
	 * means the check ran and found nothing, `false` means there is nothing to
	 * read. "Clean" and "never run" deserve different words.
	 *
	 * Read through `Findings_Store`, which is an option rather than a transient
	 * precisely so this read cannot come back empty for reasons that have nothing
	 * to do with the data: a development environment setting
	 * `LWTV_DISABLE_TRANSIENTS`, or -- the production bug -- WP-CLI and web
	 * requests not sharing an object cache tier. See Debugger\Findings_Store.
	 *
	 * `stored` and `last` come from the status option and remain the fallback for
	 * when the findings really are gone. One reason for that, not two, now that
	 * both are options: the findings expire and the status entry does not, so a
	 * check nobody has looked at in ten days has a count on record and no detail
	 * behind it. (The other reason used to be that a fresh database copy brought
	 * options across but not transients. It no longer applies -- a copy now brings
	 * the findings too.) The tab picker still badges only `count`, so it never
	 * advertises a number whose detail has gone; the overview shows `stored` and
	 * says when it is from.
	 *
	 * @return array<string, array{count: int, new: int, cached: bool, stored: int, last: int}> Keyed by tab slug.
	 */
	private static function tab_counts(): array {
		if ( null !== self::$counts ) {
			return self::$counts;
		}

		$counts  = array();
		$options = is_array( self::$options ) && ! empty( self::$options ) ? self::$options : Status::all();

		foreach ( self::TOOL_TABS as $tab => $value ) {
			$items = ( ! empty( $value['findings'] ) )
				? Findings_Store::load( $value['findings'] )
				: false;

			$rows = is_array( $items ) ? $items : array();
			$new  = 0;

			// Rows carry their own new/open stamp from the baseline diff, so the
			// "N new" half of a badge comes from the same rows as the total. The
			// status summary counts findings where this counts rows; mixing the
			// two is what made "4 new / 41" incomparable.
			foreach ( $rows as $row ) {
				if ( is_array( $row ) && Baseline::NEW_ISSUE === ( $row['status'] ?? '' ) ) {
					++$new;
				}
			}

			$option = (string) ( $value['option'] ?? '' );
			$status = ( '' !== $option && isset( $options[ $option ] ) && is_array( $options[ $option ] ) )
				? $options[ $option ]
				: array();

			$counts[ $tab ] = array(
				'count'  => count( $rows ),
				'new'    => $new,
				'cached' => is_array( $items ),
				'stored' => (int) ( $status['count'] ?? 0 ),
				'last'   => (int) ( $status['last'] ?? 0 ),
			);
		}

		self::$counts = $counts;

		return self::$counts;
	}

	/*
	 * Settings Page Content
	 *
	 * @return void
	 */
	public static function settings_page() {
		// Get the active tab for later
		$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'intro'; // phpcs:ignore WordPress.Security.NonceVerification
		$options    = is_array( self::$options ) ? self::$options : Status::all();
		?>
		<div class="wrap">

			<h1>Validation Checks</h1>

			<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="lwtv-tools-tabpicker">
				<input type="hidden" name="page" value="lwtv_data_check" />
				<label for="lwtv-tools-tab"><strong><?php esc_html_e( 'Check:', 'lwtv' ); ?></strong></label>
				<select name="tab" id="lwtv-tools-tab">
					<option value="intro" <?php selected( 'intro', $active_tab ); ?>><?php esc_html_e( 'Introduction', 'lwtv' ); ?></option>
					<?php
					$counts = self::tab_counts();

					foreach ( self::TOOL_TABS as $tab => $value ) {
						// Reports into another tab; it has no page to pick.
						if ( isset( $value['show_tab'] ) && false === $value['show_tab'] ) {
							continue;
						}

						$count = $counts[ $tab ]['count'];
						$new   = $counts[ $tab ]['new'];

						if ( $count > 0 && $new > 0 ) {
							/* translators: 1: check name, 2: new items, 3: total outstanding. */
							$label = sprintf( '%1$s (%2$d new / %3$d)', $value['name'], $new, $count );
						} elseif ( $count > 0 ) {
							/* translators: 1: check name, 2: number of outstanding items. */
							$label = sprintf( '%1$s (%2$d)', $value['name'], $count );
						} else {
							$label = $value['name'];
						}
						?>
						<option value="<?php echo esc_attr( 'tab_' . $tab ); ?>" <?php selected( 'tab_' . $tab, $active_tab ); ?>>
							<?php echo esc_html( $label ); ?>
						</option>
						<?php
					}
					?>
				</select>
				<?php submit_button( __( 'Go', 'lwtv' ), 'secondary', '', false ); ?>
			</form>

			<script>
				// Progressive enhancement only -- the Go button works without it.
				document.getElementById( 'lwtv-tools-tab' ).addEventListener( 'change', function () {
					this.form.submit();
				} );
			</script>

			<div id="dashboard" class="lwtvtab">
				<?php
				// One place, so every tab reports the result of a repair.
				Repair::show_notice();

				/*
				 * One lookup, not a switch. Every report tab renders from its
				 * TOOL_TABS entry; the two that are not reports name their own
				 * renderer with a `render` key.
				 */
				$slug   = str_starts_with( $active_tab, 'tab_' ) ? substr( $active_tab, 4 ) : '';
				$config = self::TOOL_TABS[ $slug ] ?? array();

				/*
				 * An entry that reports into another tab has no page of its own,
				 * so a hand-typed or bookmarked URL for it gets sent where its
				 * findings actually render. Without this it would fall through to
				 * Report::make() with no scanner and no copy.
				 */
				if ( ! empty( $config['tab'] ) && $config['tab'] !== $slug ) {
					$slug   = $config['tab'];
					$config = self::TOOL_TABS[ $slug ] ?? array();
				}

				if ( empty( $config ) ) {
					self::tab_introduction();
				} elseif ( isset( $config['render'] ) ) {
					call_user_func( $config['render'] );
				} else {
					Report::make( $slug, $config );
				}
				?>
			</div>

		</div>
		<?php
	}

	/**
	 * Build Table content
	 *
	 * @param  array  $items
	 * @return string Table Content
	 */
	public static function table_content( $items ) {
		$number = 1;
		foreach ( $items as $item ) {
			/*
			 * This renderer dereferences `id` as a post — get_the_title(),
			 * get_edit_post_link(), get_permalink(). A term-shaped finding would
			 * pass all of those silently and render an empty row with working
			 * links to nothing, which is the worst way to be wrong. Watch URL
			 * findings have their own renderer for exactly this reason; skipping
			 * here means a future check that forgets that gets an obviously
			 * missing row rather than a plausible wrong one.
			 */
			if ( ! Findings::is_post( $item ) ) {
				continue;
			}

			$class     = ( 0 === $number % 2 ) ? '' : 'alternate';
			$modified  = get_post_timestamp( (int) $item['id'], 'modified' );
			$published = get_post_timestamp( (int) $item['id'], 'date' );
			$current   = current_datetime()->format( 'U' );
			$time_diff = 'never modified';
			if ( ! empty( $modified ) ) {
				$time      = date_i18n( get_option( 'date_format' ), $modified );
				$time_diff = human_time_diff( $modified, $current );
			} else {
				$time = date_i18n( get_option( 'date_format' ), $published );
			}

			$problem = self::problem_cell( $item );

			// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- problem_cell() escapes every part it assembles, and the repair buttons are form markup wp_kses_post() would strip.
			echo '
			<tr class="' . esc_attr( $class ) . '">
				<td><strong><a href="' . esc_url( get_edit_post_link( (int) $item['id'] ) ) . '" target="_blank" rel="noopener noreferrer">' . wp_kses_post( get_the_title( (int) $item['id'] ) ) . '</a></strong>

				<div class="row-actions"><span class="edit"><a href="' . esc_url( get_edit_post_link( (int) $item['id'] ) ) . '" target="_blank" rel="noopener noreferrer" aria-label="Edit ' . wp_kses_post( get_the_title( (int) $item['id'] ) ) . ' (opens in a new tab)">Edit</a>
				| </span><span class="view"><a href="' . esc_url( get_permalink( (int) $item['id'] ) ) . '" target="_blank" rel="bookmark noopener noreferrer" aria-label="View ' . wp_kses_post( get_the_title( (int) $item['id'] ) ) . ' (opens in a new tab)">View</a></span></div>
				</td>
				<td>' . $problem . '</td>
				<td>' . esc_html( $time ) . '<br/>(' . esc_html( $time_diff ) . ' ago)</td>
			</tr>
			';
			// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
			++$number;
		}
	}

	/**
	 * The Problem cell for one finding row.
	 *
	 * Typed rows are rendered one issue per line, each with its own repair
	 * button where a repair exists. Rows cached before findings were typed have
	 * no `issues` key, so they fall back to the pre-rendered `problem` blob --
	 * which is also what checks that have not been converted yet still send.
	 *
	 * @param  array  $item One finding row.
	 * @return string Escaped markup.
	 */
	private static function problem_cell( array $item ): string {
		$issues   = isset( $item['issues'] ) && is_array( $item['issues'] ) ? array_values( $item['issues'] ) : array();
		$messages = isset( $item['messages'] ) && is_array( $item['messages'] ) ? array_values( $item['messages'] ) : array();

		if ( empty( $issues ) || empty( $messages ) ) {
			return wp_kses_post( $item['problem'] ?? '' );
		}

		$post_id  = (int) $item['id'];
		$statuses = isset( $item['statuses'] ) && is_array( $item['statuses'] ) ? array_values( $item['statuses'] ) : array();
		$lines    = array();

		foreach ( $messages as $index => $message ) {
			$issue_type = (string) ( $issues[ $index ] ?? '' );
			$button     = Repair::button( $post_id, $issue_type );

			// Flag only what is new. Marking everything else "open" would be
			// noise on a report where most rows are long-standing by nature.
			$flag = ( Baseline::NEW_ISSUE === ( $statuses[ $index ] ?? '' ) )
				? '<span class="lwtv-debug-new" style="font-weight:600;">' . esc_html__( 'New', 'lwtv' ) . '</span> '
				: '';

			$lines[] = '<div class="lwtv-debug-issue">'
				. $flag
				. wp_kses_post( $message )
				. ( '' !== $button ? ' ' . $button : '' )
				. '</div>';
		}

		return implode( '', $lines );
	}

	/**
	 * The overview: every check, what it looks for, and what it last found.
	 *
	 * Replaced a bulleted list of links plus a separate "Current Status" list that
	 * repeated some of the same checks under different names, so you had to match
	 * them up by eye. One row per check, with its count in it, removes that
	 * translation step.
	 *
	 * Counts come from tab_counts(), the same place the tab picker's badges do, so
	 * this table cannot disagree with the badge or with the report it links to.
	 *
	 * @return void
	 */
	public static function tab_introduction() {
		$counts = self::tab_counts();
		?>
		<div class="tab-block">
			<p class="lwtv-tools-intro">
				<?php esc_html_e( 'If data gets out of sync or we update things incorrectly, these checkers can help identify those errors before people notice. They run on an automated cycle, each check once a week, to try and catch things early.', 'lwtv' ); ?>
			</p>

			<div class="lwtv-tools-callout">
				<p><?php esc_html_e( 'When visiting the individual checker, it will show you the status of the last run. To re-run the tool, press the \'Run Scan\' button at the bottom of the page.', 'lwtv' ); ?></p>
			</div>

			<h2 class="lwtv-tools-subhead"><?php esc_html_e( 'Current Status', 'lwtv' ); ?></h2>

			<table class="widefat striped lwtv-tools-checkers">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Checker', 'lwtv' ); ?></th>
						<th scope="col" class="lwtv-tools-checkers__count"><?php esc_html_e( 'Issues Found', 'lwtv' ); ?></th>
						<th scope="col" class="lwtv-tools-checkers__action">
							<span class="screen-reader-text"><?php esc_html_e( 'Actions', 'lwtv' ); ?></span>
						</th>
					</tr>
				</thead>
				<tbody>
					<?php
					foreach ( self::TOOL_TABS as $tab => $value ) {
						$url    = add_query_arg(
							array(
								'page' => 'lwtv_data_check',
								'tab'  => 'tab_' . ( $value['tab'] ?? $tab ),
							),
							admin_url( 'admin.php' )
						);
						$count  = $counts[ $tab ]['count'];
						$cached = $counts[ $tab ]['cached'];
						$stored = $counts[ $tab ]['stored'];
						$last   = $counts[ $tab ]['last'];
						?>
						<tr>
							<td>
								<a href="<?php echo esc_url( $url ); ?>" class="lwtv-tools-checkers__name"><?php echo esc_html( $value['name'] ); ?></a>
								<span class="lwtv-tools-checkers__desc"><?php echo esc_html( $value['desc'] ); ?></span>
							</td>
							<td class="lwtv-tools-checkers__count">
								<?php
								/*
								 * Four states. A count of zero with cached findings
								 * means the check ran and found nothing, which is
								 * good news and reads as an em-dash. No cached
								 * findings is an absence, not good news -- and
								 * collapsing the two would let a check quietly stop
								 * running while looking clean.
								 *
								 * The third state matters because the findings can
								 * genuinely be gone while the status option remains:
								 * findings expire after ten days and the status
								 * entry never does. Reporting a dozen checks as
								 * never run while the site knows what they last
								 * found would be worse than saying so and dating it.
								 */
								if ( $count > 0 ) {
									?>
									<span class="lwtv-tools-pill"><?php echo esc_html( (string) $count ); ?></span>
									<?php
								} elseif ( $cached ) {
									?>
									<span class="lwtv-tools-checkers__none" aria-label="<?php esc_attr_e( 'No issues', 'lwtv' ); ?>">&mdash;</span>
									<?php
								} elseif ( $last ) {
									/*
									 * `last` is the "has it run" signal, not
									 * `stored`. A check that ran and found nothing
									 * records a count of zero against a real
									 * timestamp -- testing the count instead
									 * reported a clean check as never run, which is
									 * both wrong and alarming in the wrong
									 * direction.
									 */
									if ( $stored > 0 ) {
										?>
										<span class="lwtv-tools-pill lwtv-tools-pill--stale"><?php echo esc_html( (string) $stored ); ?></span>
										<?php
									} else {
										?>
										<span class="lwtv-tools-checkers__none">&mdash;</span>
										<?php
									}
									?>
									<span class="lwtv-tools-checkers__asof">
										<?php
										printf(
											/* translators: %s: human-readable time difference, e.g. "3 days". */
											esc_html__( 'as of %s ago', 'lwtv' ),
											esc_html( human_time_diff( $last ) )
										);
										?>
									</span>
									<?php
								} else {
									?>
									<span class="lwtv-tools-checkers__none"><?php esc_html_e( 'Not run', 'lwtv' ); ?></span>
									<?php
								}
								?>
							</td>
							<td class="lwtv-tools-checkers__action">
								<a href="<?php echo esc_url( $url ); ?>" class="button button-small"><?php esc_html_e( 'View report', 'lwtv' ); ?></a>
							</td>
						</tr>
						<?php
					}
					?>
				</tbody>
			</table>

			<?php
			// Echoed, not just called: last_run() returns its markup, and the old
			// intro invoked it bare and printed nothing at all.
			echo wp_kses_post( self::last_run( 'intro' ) );
			?>
		</div>
		<?php
	}
}
