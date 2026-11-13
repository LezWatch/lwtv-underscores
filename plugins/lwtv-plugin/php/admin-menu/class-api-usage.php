<?php
/**
 * API Usage: who calls lwtv/v1, what they call, and what's broken.
 *
 * Reads the daily rollup CSV that cron/api-roundup.sh builds from the nginx
 * logs. GA can't see API calls, so this is the only usage record, and the raw
 * logs only last about a week. The page is read-only on that file: it never
 * writes, truncates, or rotates it.
 *
 * Everything in the CSV comes from request headers somebody else controls
 * (paths, user agents), so every field is escaped on the way out.
 *
 * The pure logic lives in Admin_Menu\Build (Api_Usage_Csv, Api_Route_Matcher,
 * Api_Usage_Report, Api_Known_Consumers) and is unit-tested. This class keeps
 * the WordPress half: the file read, the cache, the menu, the markup, and the
 * known-consumers save.
 *
 * @package LWTV
 */

namespace LWTV\Admin_Menu;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\Admin_Menu\Build\Api_Known_Consumers;
use LWTV\Admin_Menu\Build\Api_Route_Matcher;
use LWTV\Admin_Menu\Build\Api_Usage_Csv;
use LWTV\Admin_Menu\Build\Api_Usage_Report;

class Api_Usage {

	const SLUG       = 'lwtv_api_usage';
	const CAPABILITY = 'activate_plugins';
	const OPTION     = 'lwtv_api_known_consumers';
	const ACTION     = 'lwtv_api_usage_consumers';

	/**
	 * The wp-config.php constant holding the CSV's absolute path. No default:
	 * each environment points at its own file, and no server path or account
	 * name lives in the code.
	 */
	const PATH_CONSTANT = 'LWTV_API_USAGE_CSV';

	/**
	 * Part of the report cache key. Bump whenever the report's shape changes,
	 * or a cached report from before the deploy can be served for up to a day
	 * and be missing the new keys.
	 */
	const CACHE_VERSION = 1;

	/**
	 * How old the data can get before we say the cron job may have stopped.
	 */
	const STALE_HOURS = 36;

	/**
	 * Consumers first seen within this many days get a "New" badge.
	 */
	const NEW_DAYS = 14;

	/**
	 * Days in the default range, counting back from the newest date in the file.
	 */
	const RANGE_DAYS = 30;

	/**
	 * Tab slugs, in display order. Labels come from tab_labels().
	 */
	const TABS = array( 'routes', 'consumers', 'no-hits', 'known' );

	/**
	 * Add the submenu. Called from Admin_Menu on `admin_menu`, admins only.
	 */
	public function init(): void {
		$title = __( 'API Usage', 'lwtv-underscores' );
		add_submenu_page( 'lwtv', $title, $title, self::CAPABILITY, self::SLUG, array( $this, 'settings_page' ) );
	}

	/**
	 * Translated tab labels, slug => label.
	 *
	 * @return array<string,string>
	 */
	private static function tab_labels(): array {
		return array(
			'routes'    => __( 'By Route', 'lwtv-underscores' ),
			'consumers' => __( 'By Consumer', 'lwtv-underscores' ),
			'no-hits'   => __( 'No Hits In This Range', 'lwtv-underscores' ),
			'known'     => __( 'Known Consumers', 'lwtv-underscores' ),
		);
	}

	/**
	 * Translated outreach status labels, slug => label.
	 *
	 * @return array<string,string>
	 */
	private static function status_labels(): array {
		return array(
			'none'       => __( 'Not started', 'lwtv-underscores' ),
			'to-contact' => __( 'To contact', 'lwtv-underscores' ),
			'contacted'  => __( 'Contacted', 'lwtv-underscores' ),
			'replied'    => __( 'Replied', 'lwtv-underscores' ),
			'no-contact' => __( 'No way to contact', 'lwtv-underscores' ),
		);
	}

	/**
	 * Hooks that must exist outside `admin_menu` (admin-post.php never fires it).
	 */
	public static function register_handlers(): void {
		add_action( 'admin_post_' . self::ACTION, array( self::class, 'save_consumers' ) );
	}

	/**
	 * Where the CSV lives, from LWTV_API_USAGE_CSV in wp-config.php.
	 *
	 * @return string Absolute path, or '' when the constant isn't set.
	 */
	public static function csv_path(): string {
		$path = defined( self::PATH_CONSTANT ) ? constant( self::PATH_CONSTANT ) : '';
		return is_string( $path ) ? trim( $path ) : '';
	}

	/**
	 * The known-consumers list, or the seed until someone saves it.
	 *
	 * @return array<string, array{label:string,name:string,contact:string,notes:string,status:string,internal:bool}>
	 */
	public static function known_consumers(): array {
		$known = get_option( self::OPTION, null );
		return is_array( $known ) ? Api_Known_Consumers::normalize( array_values( $known ) ) : Api_Known_Consumers::seed();
	}

	/**
	 * Page content.
	 */
	public function settings_page(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'lwtv-underscores' ) );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only view filters.
		$tab      = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'routes';
		$range    = ( isset( $_GET['range'] ) && 'all' === $_GET['range'] ) ? 'all' : (string) self::RANGE_DAYS;
		$internal = isset( $_GET['internal'] ) && '1' === $_GET['internal'];
		$orderby  = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : '';
		$order    = ( isset( $_GET['order'] ) && 'asc' === $_GET['order'] ) ? 'asc' : 'desc';
		$updated  = isset( $_GET['updated'] );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$tab   = in_array( $tab, self::TABS, true ) ? $tab : 'routes';
		$view  = array(
			'tab'      => $tab,
			'range'    => $range,
			'internal' => $internal ? '1' : '',
		);
		$known = self::known_consumers();
		?>
		<div class="wrap lwtv-api-usage">
			<h1><?php esc_html_e( 'API Usage', 'lwtv-underscores' ); ?></h1>
			<p class="lwtv-tools-intro"><?php esc_html_e( 'Calls to the lwtv/v1 REST API, from the daily rollup of the server logs. Analytics cannot see API traffic; this can. Broken routes are listed first, because hits to a broken route are people who wanted something and did not get it.', 'lwtv-underscores' ); ?></p>
			<?php
			if ( $updated ) {
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Known consumers saved.', 'lwtv-underscores' ) . '</p></div>';
			}

			$data = self::load( $range, $internal ? array() : Api_Known_Consumers::internal_labels( $known ) );
			if ( isset( $data['error'] ) ) {
				echo '<div class="notice notice-error"><p>' . esc_html( $data['error'] ) . '</p></div></div>';
				return;
			}

			self::last_run( $data['mtime'], $data['newest'] );
			self::filters( $view, $data );
			self::tabs( $view );

			$report = $data['report'];
			switch ( $tab ) {
				case 'consumers':
					self::tab_consumers( Api_Usage_Report::sort( $report['consumers'], $orderby ?: 'hits', $order ), $known, $view, $orderby ?: 'hits', $order );
					break;
				case 'no-hits':
					self::tab_no_hits( $report['no_hits'] );
					break;
				case 'known':
					self::tab_known( $known, $report['consumers'] );
					break;
				default:
					self::tab_routes( Api_Usage_Report::sort( $report['routes'], $orderby ?: 's5xx', $order ), $view, $orderby ?: 's5xx', $order );
			}
			?>
		</div>
		<?php
	}

	/**
	 * Read, parse and aggregate the CSV, cached on the file's mtime and the view.
	 *
	 * The cache holds the finished report, not the parsed rows: a year of rows
	 * is tens of thousands of arrays, far too big for a transient, while the
	 * report is a few hundred. A new cron run changes the mtime, which changes
	 * the key, so nothing stale is ever served.
	 *
	 * @param string            $range   'all' or a number of days.
	 * @param array<int,string> $exclude Consumer labels to leave out.
	 * @return array{error?:string, mtime?:int, newest?:string|null, from?:string, to?:string, report?:array<string,mixed>}
	 */
	private static function load( string $range, array $exclude ): array {
		$path = self::csv_path();

		if ( '' === $path ) {
			return array(
				/* translators: %s: constant name */
				'error' => sprintf( __( 'The usage file is not configured. Add %s to wp-config.php, set to the full path of usage.csv.', 'lwtv-underscores' ), self::PATH_CONSTANT ),
			);
		}

		if ( ! is_readable( $path ) ) {
			return array(
				/* translators: %s: file path */
				'error' => sprintf( __( 'Cannot read the usage file at %s. Check that the cron job has run and that the web server can read the file.', 'lwtv-underscores' ), $path ),
			);
		}

		clearstatcache( true, $path );
		$mtime  = (int) filemtime( $path );
		$routes = array_keys( rest_get_server()->get_routes( 'lwtv/v1' ) );
		$key    = 'lwtv_api_usage_' . md5( wp_json_encode( array( self::CACHE_VERSION, $path, $mtime, filesize( $path ), $range, $exclude, $routes ) ) );

		$cached = get_transient( $key );
		if ( is_array( $cached ) && isset( $cached['report'] ) ) {
			return $cached;
		}

		$lines = file( $path, FILE_IGNORE_NEW_LINES );
		$rows  = Api_Usage_Csv::parse( false === $lines ? array() : $lines );
		unset( $lines );

		$newest = Api_Usage_Csv::newest_date( $rows );
		$to     = $newest ?? gmdate( 'Y-m-d' );
		$from   = ( 'all' === $range ) ? '0000-00-00' : gmdate( 'Y-m-d', strtotime( $to . ' -' . ( self::RANGE_DAYS - 1 ) . ' days' ) );
		$since  = gmdate( 'Y-m-d', strtotime( $to . ' -' . ( self::NEW_DAYS - 1 ) . ' days' ) );

		$matcher = new Api_Route_Matcher( $routes, rest_get_url_prefix() );
		$data    = array(
			'mtime'  => $mtime,
			'newest' => $newest,
			'from'   => $from,
			'to'     => $to,
			'report' => Api_Usage_Report::build( $rows, $matcher, $from, $to, $exclude, $since ),
		);

		set_transient( $key, $data, DAY_IN_SECONDS );
		return $data;
	}

	/**
	 * When the cron job last wrote, with a warning if it looks stopped.
	 *
	 * Two clocks, because either can stall on its own: the file can be touched
	 * with nothing new in it, or a run can fail before writing at all.
	 *
	 * @param int         $mtime  File modification time.
	 * @param string|null $newest Newest date in the file (Y-m-d, server log date, -0800).
	 */
	private static function last_run( int $mtime, ?string $newest ): void {
		$limit      = time() - self::STALE_HOURS * HOUR_IN_SECONDS;
		$newest_end = $newest ? strtotime( $newest . ' 23:59:59 -0800' ) : 0;
		$stale      = $mtime < $limit || $newest_end < $limit;

		$written = wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $mtime );
		$latest  = $newest ? $newest : __( 'none', 'lwtv-underscores' );

		if ( $stale ) {
			echo '<div class="notice notice-warning"><p><strong>' . esc_html__( 'The usage data looks stale.', 'lwtv-underscores' ) . '</strong> ';
			/* translators: %d: hours */
			echo esc_html( sprintf( __( 'Nothing new in the last %d hours. The daily rollup (cron/api-roundup.sh, 01:15) may have stopped running.', 'lwtv-underscores' ), self::STALE_HOURS ) );
			echo '</p></div>';
		}

		echo '<p class="lwtv-api-usage__lastrun">';
		/* translators: 1: date and time the file was written, 2: newest day of data */
		echo esc_html( sprintf( __( 'File last written %1$s. Newest day of data: %2$s.', 'lwtv-underscores' ), $written, $latest ) );
		echo '</p>';
	}

	/**
	 * Range and internal-traffic toggles.
	 *
	 * @param array<string,string> $view Current view args.
	 * @param array<string,mixed>  $data Loaded data.
	 */
	private static function filters( array $view, array $data ): void {
		$totals = $data['report']['totals'];
		$range  = ( '0000-00-00' === $data['from'] )
			/* translators: %s: last date */
			? sprintf( __( 'All data through %s', 'lwtv-underscores' ), $data['to'] )
			/* translators: 1: first date, 2: last date */
			: sprintf( __( '%1$s to %2$s', 'lwtv-underscores' ), $data['from'], $data['to'] );

		echo '<p class="lwtv-api-usage__filters">';
		echo '<strong>' . esc_html( $range ) . '</strong> &middot; ';
		/* translators: 1: number of hits, 2: data size */
		echo esc_html( sprintf( __( '%1$s hits, %2$s sent', 'lwtv-underscores' ), number_format_i18n( $totals['hits'] ), size_format( $totals['bytes'] ) ?: '0 B' ) );
		echo ' &middot; ';

		$other_range = ( 'all' === $view['range'] ) ? '' : 'all';
		$range_label = ( 'all' === $view['range'] )
			/* translators: %d: number of days */
			? sprintf( __( 'Show last %d days', 'lwtv-underscores' ), self::RANGE_DAYS )
			: __( 'Show all data', 'lwtv-underscores' );
		echo '<a href="' . esc_url( self::url( $view, array( 'range' => $other_range ) ) ) . '">' . esc_html( $range_label ) . '</a> &middot; ';

		$internal_label = $view['internal'] ? __( 'Hide internal traffic', 'lwtv-underscores' ) : __( 'Show internal traffic', 'lwtv-underscores' );
		echo '<a href="' . esc_url( self::url( $view, array( 'internal' => $view['internal'] ? '' : '1' ) ) ) . '">' . esc_html( $internal_label ) . '</a>';
		echo '</p>';
	}

	/**
	 * Tab bar.
	 *
	 * @param array<string,string> $view Current view args.
	 */
	private static function tabs( array $view ): void {
		echo '<h2 class="nav-tab-wrapper">';
		foreach ( self::tab_labels() as $slug => $label ) {
			$class = ( $slug === $view['tab'] ) ? 'nav-tab nav-tab-active' : 'nav-tab';
			echo '<a href="' . esc_url( self::url( $view, array( 'tab' => $slug ) ) ) . '" class="' . esc_attr( $class ) . '">' . esc_html( $label ) . '</a>';
		}
		echo '</h2>';
	}

	/**
	 * By Route.
	 *
	 * @param list<array<string,mixed>> $routes  Sorted route rows.
	 * @param array<string,string>      $view    Current view args.
	 * @param string                    $orderby Current sort key.
	 * @param string                    $order   asc|desc.
	 */
	private static function tab_routes( array $routes, array $view, string $orderby, string $order ): void {
		if ( empty( $routes ) ) {
			echo '<p>' . esc_html__( 'No API hits in this range.', 'lwtv-underscores' ) . '</p>';
			return;
		}

		$columns = array(
			'route'      => __( 'Route', 'lwtv-underscores' ),
			'hits'       => __( 'Hits', 'lwtv-underscores' ),
			''           => '2xx',
			'_4xx'       => '4xx',
			'_499'       => '499',
			's5xx'       => '5xx',
			'_other'     => __( 'Other', 'lwtv-underscores' ),
			'error_rate' => __( 'Error rate', 'lwtv-underscores' ),
			'bytes'      => __( 'Sent', 'lwtv-underscores' ),
			'consumers'  => __( 'Consumers', 'lwtv-underscores' ),
			'last_seen'  => __( 'Last seen', 'lwtv-underscores' ),
		);

		echo '<p class="description">' . esc_html__( 'Error rate counts 4xx and 5xx. 499 means the caller gave up waiting, so it is shown on its own. Other is redirects (3xx) and anything unusual. A route that answers 404 for a slug that does not exist is not necessarily broken.', 'lwtv-underscores' ) . '</p>';
		echo '<table class="widefat striped lwtv-api-usage__table"><thead><tr>';
		self::headers( $columns, $view, $orderby, $order );
		echo '</tr></thead><tbody>';

		foreach ( $routes as $r ) {
			$class = ( $r['s5xx'] > 0 ) ? 'lwtv-api-usage__broken' : '';
			echo '<tr class="' . esc_attr( $class ) . '">';
			echo '<td>';
			echo '<details><summary><code>' . esc_html( Api_Route_Matcher::label( $r['route'] ) ) . '</code></summary><ul>';
			foreach ( $r['top_paths'] as $p ) {
				echo '<li><code>' . esc_html( $p['path'] ) . '</code> &times; ' . esc_html( number_format_i18n( $p['hits'] ) ) . '</li>';
			}
			echo '</ul></details>';
			echo '</td>';
			self::num( $r['hits'] );
			self::num( $r['s2xx'] );
			self::num( $r['s4xx'] );
			self::num( $r['s499'] );
			self::num( $r['s5xx'] );
			self::num( $r['s3xx'] + $r['other'] );
			echo '<td>' . esc_html( self::percent( $r['error_rate'] ) ) . '</td>';
			echo '<td>' . esc_html( size_format( $r['bytes'] ) ?: '0 B' ) . '</td>';
			self::num( $r['consumers'] );
			echo '<td>' . esc_html( $r['last_seen'] ) . '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	/**
	 * By Consumer.
	 *
	 * @param list<array<string,mixed>> $consumers Sorted consumer rows.
	 * @param array<string,array>       $known     Known consumers.
	 * @param array<string,string>      $view      Current view args.
	 * @param string                    $orderby   Current sort key.
	 * @param string                    $order     asc|desc.
	 */
	private static function tab_consumers( array $consumers, array $known, array $view, string $orderby, string $order ): void {
		if ( empty( $consumers ) ) {
			echo '<p>' . esc_html__( 'No API hits in this range.', 'lwtv-underscores' ) . '</p>';
			return;
		}

		$columns = array(
			'consumer'   => __( 'Consumer', 'lwtv-underscores' ),
			'hits'       => __( 'Hits', 'lwtv-underscores' ),
			's5xx'       => '5xx',
			'error_rate' => __( 'Error rate', 'lwtv-underscores' ),
			'_routes'    => __( 'Top routes', 'lwtv-underscores' ),
			'_first'     => __( 'First seen', 'lwtv-underscores' ),
			'last_seen'  => __( 'Last seen', 'lwtv-underscores' ),
		);

		echo '<p class="description">' . esc_html__( 'Consumers are grouped by user agent in the cron script. Names that identify themselves are the best leads for outreach; bare-mozilla, chrome113, node and curl are anonymous.', 'lwtv-underscores' ) . '</p>';
		echo '<table class="widefat striped lwtv-api-usage__table"><thead><tr>';
		self::headers( $columns, $view, $orderby, $order );
		echo '</tr></thead><tbody>';

		foreach ( $consumers as $c ) {
			$info = $known[ $c['consumer'] ] ?? null;
			echo '<tr><td><code>' . esc_html( $c['consumer'] ) . '</code>';
			if ( $c['is_new'] ) {
				echo ' <span class="lwtv-tools-pill">' . esc_html__( 'New', 'lwtv-underscores' ) . '</span>';
			}
			if ( $info && $info['internal'] ) {
				echo ' <span class="lwtv-tools-pill lwtv-api-usage__pill--internal">' . esc_html__( 'Internal', 'lwtv-underscores' ) . '</span>';
			}
			if ( $info && '' !== $info['name'] ) {
				echo '<br><span class="description">' . esc_html( $info['name'] ) . '</span>';
			}
			echo '</td>';
			self::num( $c['hits'] );
			self::num( $c['s5xx'] );
			echo '<td>' . esc_html( self::percent( $c['error_rate'] ) ) . '</td>';
			echo '<td>';
			foreach ( array_slice( $c['routes'], 0, 3 ) as $r ) {
				echo '<code>' . esc_html( Api_Route_Matcher::label( $r['route'] ) ) . '</code> &times; ' . esc_html( number_format_i18n( $r['hits'] ) ) . '<br>';
			}
			$more = count( $c['routes'] ) - 3;
			if ( $more > 0 ) {
				/* translators: %d: number of other routes */
				echo '<span class="description">' . esc_html( sprintf( _n( '+ %d more route', '+ %d more routes', $more, 'lwtv-underscores' ), $more ) ) . '</span>';
			}
			echo '</td>';
			echo '<td>' . esc_html( $c['first_seen'] ) . '</td>';
			echo '<td>' . esc_html( $c['last_seen'] ) . '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	/**
	 * Registered routes with no hits in the range.
	 *
	 * @param list<string> $routes Route keys.
	 */
	private static function tab_no_hits( array $routes ): void {
		echo '<h3>' . esc_html__( 'No hits in this range', 'lwtv-underscores' ) . '</h3>';
		echo '<p class="description">' . esc_html__( 'These routes are registered but nobody called them in the selected range (with the current internal-traffic setting). A few weeks of data cannot tell a dead route from one used once a quarter or once a year, so this is not a list of routes that are safe to remove.', 'lwtv-underscores' ) . '</p>';

		if ( empty( $routes ) ) {
			echo '<p>' . esc_html__( 'Every registered route had at least one hit.', 'lwtv-underscores' ) . '</p>';
			return;
		}

		echo '<ul class="ul-disc">';
		foreach ( $routes as $route ) {
			echo '<li><code>' . esc_html( Api_Route_Matcher::label( $route ) ) . '</code></li>';
		}
		echo '</ul>';
	}

	/**
	 * Known consumers editor.
	 *
	 * @param array<string,array>       $known     Known consumers.
	 * @param list<array<string,mixed>> $consumers Consumers seen in range.
	 */
	private static function tab_known( array $known, array $consumers ): void {
		$rows   = array_values( $known );
		$blanks = 3;
		for ( $i = 0; $i < $blanks; $i++ ) {
			$rows[] = array(
				'label'    => '',
				'name'     => '',
				'contact'  => '',
				'notes'    => '',
				'status'   => 'none',
				'internal' => false,
			);
		}

		echo '<p class="description">' . esc_html__( 'Who each consumer label is and how to reach them. The label must match the Consumer column exactly. Ticking Internal hides that label\'s traffic unless "Show internal traffic" is on. Clear a label to remove its row.', 'lwtv-underscores' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '">';
		wp_nonce_field( self::ACTION );
		echo '<table class="widefat striped lwtv-api-usage__known"><thead><tr>';
		foreach ( array( __( 'Label', 'lwtv-underscores' ), __( 'Name', 'lwtv-underscores' ), __( 'Contact', 'lwtv-underscores' ), __( 'Notes', 'lwtv-underscores' ), __( 'Outreach', 'lwtv-underscores' ), __( 'Internal', 'lwtv-underscores' ) ) as $head ) {
			echo '<th scope="col">' . esc_html( $head ) . '</th>';
		}
		echo '</tr></thead><tbody>';

		foreach ( $rows as $i => $row ) {
			$name = 'consumers[' . $i . ']';
			echo '<tr>';
			echo '<td><input type="text" class="regular-text" name="' . esc_attr( $name . '[label]' ) . '" value="' . esc_attr( $row['label'] ) . '" aria-label="' . esc_attr__( 'Label', 'lwtv-underscores' ) . '"></td>';
			echo '<td><input type="text" name="' . esc_attr( $name . '[name]' ) . '" value="' . esc_attr( $row['name'] ) . '" aria-label="' . esc_attr__( 'Name', 'lwtv-underscores' ) . '"></td>';
			echo '<td><input type="text" name="' . esc_attr( $name . '[contact]' ) . '" value="' . esc_attr( $row['contact'] ) . '" placeholder="' . esc_attr__( 'URL or email', 'lwtv-underscores' ) . '" aria-label="' . esc_attr__( 'Contact', 'lwtv-underscores' ) . '">';
			self::contact_link( $row['contact'] );
			echo '</td>';
			echo '<td><textarea rows="2" name="' . esc_attr( $name . '[notes]' ) . '" aria-label="' . esc_attr__( 'Notes', 'lwtv-underscores' ) . '">' . esc_textarea( $row['notes'] ) . '</textarea></td>';
			echo '<td><select name="' . esc_attr( $name . '[status]' ) . '" aria-label="' . esc_attr__( 'Outreach status', 'lwtv-underscores' ) . '">';
			$labels = self::status_labels();
			foreach ( Api_Known_Consumers::STATUSES as $status ) {
				echo '<option value="' . esc_attr( $status ) . '"' . selected( $row['status'], $status, false ) . '>' . esc_html( $labels[ $status ] ?? $status ) . '</option>';
			}
			echo '</select></td>';
			echo '<td><input type="checkbox" name="' . esc_attr( $name . '[internal]' ) . '" value="1"' . checked( $row['internal'], true, false ) . ' aria-label="' . esc_attr__( 'Internal traffic', 'lwtv-underscores' ) . '"></td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
		submit_button( __( 'Save known consumers', 'lwtv-underscores' ) );
		echo '</form>';

		$unknown = array();
		foreach ( $consumers as $c ) {
			if ( ! isset( $known[ $c['consumer'] ] ) ) {
				$unknown[] = $c['consumer'];
			}
		}
		if ( $unknown ) {
			echo '<h3>' . esc_html__( 'Seen in this range but not described', 'lwtv-underscores' ) . '</h3><ul class="ul-disc">';
			foreach ( $unknown as $label ) {
				echo '<li><code>' . esc_html( $label ) . '</code></li>';
			}
			echo '</ul>';
		}
	}

	/**
	 * Save the known-consumers form.
	 */
	public static function save_consumers(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'lwtv-underscores' ), 403 );
		}
		check_admin_referer( self::ACTION );

		// Each field is sanitized below; the array itself only gets unslashed.
		$raw  = isset( $_POST['consumers'] ) && is_array( $_POST['consumers'] ) ? wp_unslash( $_POST['consumers'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$rows = array();
		foreach ( $raw as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$rows[] = array(
				'label'    => sanitize_text_field( (string) ( $row['label'] ?? '' ) ),
				'name'     => sanitize_text_field( (string) ( $row['name'] ?? '' ) ),
				'contact'  => sanitize_text_field( (string) ( $row['contact'] ?? '' ) ),
				'notes'    => sanitize_textarea_field( (string) ( $row['notes'] ?? '' ) ),
				'status'   => sanitize_key( (string) ( $row['status'] ?? '' ) ),
				'internal' => isset( $row['internal'] ) && '1' === $row['internal'] ? '1' : '',
			);
		}

		update_option( self::OPTION, Api_Known_Consumers::normalize( $rows ), false );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => self::SLUG,
					'tab'     => 'known',
					'updated' => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Sortable table headers. Keys starting with _ (or empty) aren't sortable.
	 *
	 * @param array<string,string> $columns Key => label.
	 * @param array<string,string> $view    Current view args.
	 * @param string               $orderby Current sort key.
	 * @param string               $order   asc|desc.
	 */
	private static function headers( array $columns, array $view, string $orderby, string $order ): void {
		foreach ( $columns as $key => $label ) {
			if ( '' === $key || str_starts_with( $key, '_' ) ) {
				echo '<th scope="col">' . esc_html( $label ) . '</th>';
				continue;
			}
			$active = ( $key === $orderby );
			$next   = ( $active && 'desc' === $order ) ? 'asc' : 'desc';
			$arrow  = $active ? ( 'desc' === $order ? ' &darr;' : ' &uarr;' ) : '';
			$aria   = $active ? ( 'desc' === $order ? 'descending' : 'ascending' ) : 'none';
			echo '<th scope="col" aria-sort="' . esc_attr( $aria ) . '"><a href="' . esc_url(
				self::url(
					$view,
					array(
						'orderby' => $key,
						'order'   => $next,
					)
				)
			) . '">' . esc_html( $label ) . '</a>' . $arrow . '</th>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $arrow is a fixed entity.
		}
	}

	/**
	 * Link a contact value when it's a URL or an email address.
	 *
	 * @param string $contact Stored contact.
	 */
	private static function contact_link( string $contact ): void {
		if ( '' === $contact ) {
			return;
		}
		$kind = Api_Known_Consumers::contact_kind( $contact );
		if ( 'email' === $kind ) {
			echo '<br><a href="' . esc_url( 'mailto:' . $contact ) . '">' . esc_html__( 'email', 'lwtv-underscores' ) . '</a>';
		} elseif ( 'url' === $kind ) {
			echo '<br><a href="' . esc_url( $contact ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'open', 'lwtv-underscores' ) . '</a>';
		}
	}

	/**
	 * A numeric cell.
	 *
	 * @param int $value Number.
	 */
	private static function num( int $value ): void {
		echo '<td class="num">' . esc_html( number_format_i18n( $value ) ) . '</td>';
	}

	/**
	 * 0.125 => "12.5%".
	 *
	 * @param float $rate Fraction.
	 * @return string
	 */
	private static function percent( float $rate ): string {
		return number_format_i18n( $rate * 100, 1 ) . '%';
	}

	/**
	 * This page's URL with the current view, changed by $changes.
	 *
	 * @param array<string,string> $view    Current view args.
	 * @param array<string,string> $changes Args to change; '' removes one.
	 * @return string
	 */
	private static function url( array $view, array $changes ): string {
		$args = array_merge( array( 'page' => self::SLUG ), $view, $changes );
		if ( isset( $changes['tab'] ) ) {
			unset( $args['orderby'], $args['order'] );
		}
		if ( (string) self::RANGE_DAYS === ( $args['range'] ?? '' ) ) {
			$args['range'] = '';
		}
		return add_query_arg( array_filter( $args, static fn( $v ) => '' !== $v ), admin_url( 'admin.php' ) );
	}
}
