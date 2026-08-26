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

use LWTV\Validator\Actor_Checker;
use LWTV\Validator\Actor_IMDb;
use LWTV\Validator\BYQ_Checker;
use LWTV\Validator\Character_Checker;
use LWTV\Validator\Duplicates;
use LWTV\Validator\Queer_Checker;
use LWTV\Validator\Show_Checker;
use LWTV\Validator\Show_IMDb;
use LWTV\Validator\OnAir_Checker;
use LWTV\Validator\Watch_Providers;
use LWTV\Validator\Watch_Term_Check;

use LWTV\Debugger\Build\Baseline;
use LWTV\Debugger\Repair;
use LWTV\Debugger\Status;
use LWTV\Debugger\Watch_URLs;

class Validation {

	/**
	 * Cached copy of the debugger status option.
	 */
	private static $options = array();

	/**
	 * Tool Tabs
	 */
	private const TOOL_TABS = array(
		'queer_checker'     => array(
			'name'   => 'QIRL Characters have Queer Actors',
			'desc'   => 'Checks that all characters with queer actors have the queer cliché, and all actors with queer characters are, in fact, queer.',
			'option' => 'queercheck',
		),
		'dupe_checker'      => array(
			'name'   => 'Duplicate Actors and Show',
			'desc'   => 'Actors and Shows that are duplicates.',
			'option' => 'duplicates',
		),
		'byq_checker'       => array(
			'name'   => 'Bury Your Queers',
			'desc'   => 'Checks all characters with death cliché have proper death year meta data and shows have dead-queers trope. This may be okay, because Sara Lance.',
			'option' => 'byq_problems',
		),
		'actor_checker'     => array(
			'name'   => 'Actors Info',
			'desc'   => 'Checks that all information for actors appears correct. This includes social media and links.',
			'option' => 'actor_problems',
		),
		'character_checker' => array(
			'name'   => 'Characters Info',
			'desc'   => 'Checks that all information for characters appears correct, like if they have a show and years-on-air added.',
			'option' => 'character_problems',
		),
		'show_checker'      => array(
			'name'   => 'Shows Info',
			'desc'   => 'Checks that all information for shows appears correct. Like do they have characters and ratings etc, does intersectionality seem to match.',
			'option' => 'show_problems',
		),
		'actor_imdb'        => array(
			'name'   => 'Actors missing IMDb',
			'desc'   => 'Actors who have no IMDb value. This may actually be okay as not all webseries/international shows are listed.',
			'option' => 'actor_imdb',
		),
		'show_imdb'         => array(
			'name'   => 'Shows missing IMDb',
			'desc'   => 'Shows that have no IMDb value. This may actually be okay as not all webseries/international shows are listed.',
			'option' => 'show_imdb',
		),
		'onair_checker'     => array(
			'name'   => 'On Air',
			'desc'   => 'Checks that all shows have the correct on-air status.',
			'option' => 'onair_problems',
		),
		'watch_providers'   => array(
			'name'   => 'Watch Providers',
			'desc'   => 'Ways to Watch hosts with no provider term, so the front end is guessing their name. Create the term in one click.',
			// No badge: this isn't a cron scan, and counting it would mean a query on every view of every tab.
			'option' => '',
		),
		'watch_term_check'  => array(
			'name'   => 'Watch Term Check',
			'desc'   => 'The other half of Watch Providers: of the terms we do have, do their URLs still work and still belong to that provider? A shut-down service whose domain was resold still answers HTTP 200.',
			'option' => Watch_URLs::STATUS_KEY,
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
					foreach ( self::TOOL_TABS as $tab => $value ) {
						$count = ( ! empty( $value['option'] ) && isset( $options[ $value['option'] ]['count'] ) )
							? (int) $options[ $value['option'] ]['count']
							: 0;

						// Checks that diff against a baseline can say how much of
						// that count turned up since the last run; a raw number
						// cannot be acted on, "4 new" can.
						$new = ( ! empty( $value['option'] ) && isset( $options[ $value['option'] ]['summary']['new'] ) )
							? (int) $options[ $value['option'] ]['summary']['new']
							: 0;

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

				switch ( $active_tab ) {
					case 'tab_actor_checker':
						( new Actor_Checker() )->make();
						break;
					case 'tab_actor_imdb':
						( new Actor_IMDb() )->make();
						break;
					case 'tab_byq_checker':
						( new BYQ_Checker() )->make();
						break;
					case 'tab_dupe_checker':
						( new Duplicates() )->make();
						break;
					case 'tab_character_checker':
						( new Character_Checker() )->make();
						break;
					case 'tab_queer_checker':
						( new Queer_Checker() )->make();
						break;
					case 'tab_show_checker':
						( new Show_Checker() )->make();
						break;
					case 'tab_show_imdb':
						( new Show_IMDb() )->make();
						break;
					case 'tab_onair_checker':
						( new OnAir_Checker() )->make();
						break;
					case 'tab_watch_providers':
						Watch_Providers::make();
						break;
					case 'tab_watch_term_check':
						Watch_Term_Check::make();
						break;
					default:
						self::tab_introduction();
						break;
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
				<td><strong><a href="' . esc_url( get_edit_post_link( (int) $item['id'] ) ) . '" target="_new">' . wp_kses_post( get_the_title( (int) $item['id'] ) ) . '</a></strong>

				<div class="row-actions"><span class="edit"><a href="' . esc_url( get_edit_post_link( (int) $item['id'] ) ) . '" aria-label="Edit ' . wp_kses_post( get_the_title( (int) $item['id'] ) ) . '">Edit</a>
				| </span><span class="view"><a href="' . esc_url( get_permalink( (int) $item['id'] ) ) . '" rel="bookmark" aria-label="View ' . wp_kses_post( get_the_title( (int) $item['id'] ) ) . '">View</a></span></div>
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
	 * Static Introduction to what the hell is going on...
	 */
	public static function tab_introduction() {
		?>

		<div class="tab-block"><div class="lwtv-tools-container">
			<h3>LezWatch.TV Data Validation Checks</h3>
			<p>If data gets out of sync or we update things incorrectly, these checkers can help identify those errors before people notice. They run on an automated cycle, each check once a week, to try and catch things early.</p>

			<p>When visiting the individual checker, it will show you the status of the last run. To re-run the tool, press the 'Run Scan' button at the bottom of the page.</p>

			<?php
				self::last_run( 'intro' );
				self::current_status();
			?>

			<hr>

			<ul>
				<?php
				foreach ( self::TOOL_TABS as $tab => $value ) {
					echo '<li>&bull; <a href="?page=lwtv_data_check&tab=tab_' . esc_attr( $tab ) . '">' . esc_html( $value['name'] ) . '</a> - ' . esc_html( $value['desc'] ) . '</li>';
				}
				?>
			</ul>

		</div></div>

		<?php
	}

	private static function current_status(): void {

		$options = is_array( self::$options ) ? self::$options : Status::all();

		if ( empty( $options ) ) {
			return;
		}

		// Build the list.
		$items = '';
		foreach ( $options as $an_option ) {
			// Skip the global 'timestamp' member, which is an int not an array.
			if ( ! is_array( $an_option ) || empty( $an_option['name'] ) ) {
				continue;
			}

			$number = isset( $an_option['count'] ) ? (int) $an_option['count'] : 0;
			if ( $number > 0 ) {
				$items .= '<li>&bull; ' . esc_html( $an_option['name'] ) . ' - ' . (int) $number . '</li>';
			}
		}

		// Nothing over zero means nothing to report.
		if ( '' === $items ) {
			return;
		}

		echo wp_kses_post( '<p><strong>Current Status</strong></p><ul>' . $items . '</ul>' );
	}
}
