<?php
/**
 * Site Pulse dashboard widget.
 *
 * Companion to the "LezWatch.TV Tools" (Data Health) widget: what's happening
 * rather than what's broken. Editors see unread messages and what was added
 * this week; admins also see the API routes that are failing.
 *
 * Reading Gravity Forms, WP_Query and the API usage report happens here; what
 * to show is decided by the pure LWTV\Admin_Menu\Build\Site_Pulse.
 *
 * @package LWTV
 */

namespace LWTV\_Components;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\Admin_Menu\Api_Usage;
use LWTV\Admin_Menu\Build\Api_Known_Consumers;
use LWTV\Admin_Menu\Build\Api_Route_Matcher;
use LWTV\Admin_Menu\Build\Site_Pulse as Pulse;

class Site_Pulse implements Component {

	/**
	 * Init.
	 */
	public function init() {
		add_action( 'wp_dashboard_setup', array( $this, 'register' ) );
	}

	/**
	 * Register the widget for editors and up.
	 */
	public function register(): void {
		if ( current_user_can( 'upload_files' ) ) {
			wp_add_dashboard_widget( 'lwtv_site_pulse_widget', __( 'Site Pulse', 'lwtv-underscores' ), array( $this, 'render' ) );
		}
	}

	/**
	 * Echo the widget.
	 */
	public function render(): void {
		echo '<div class="main lwtv-site-pulse">';

		// upload_files includes Authors; only show the inbox to people who can open the entries.
		if ( class_exists( '\GFAPI' ) && \GFAPI::current_user_can_any( 'gravityforms_view_entries' ) ) {
			$this->render_inbox( Pulse::inbox( $this->forms(), $this->feeds() ) );
		}

		$this->render_added();

		if ( current_user_can( 'manage_options' ) ) {
			$this->render_api();
		}

		echo '</div>';
	}

	/**
	 * Gravity Forms with their unread counts.
	 *
	 * @return list<array{id:int,title:string,unread:int,is_active:bool}>
	 */
	private function forms(): array {
		$forms = array();
		foreach ( (array) \GFAPI::get_forms() as $form ) {
			$id      = (int) $form['id'];
			$forms[] = array(
				'id'        => $id,
				'title'     => (string) $form['title'],
				'unread'    => (int) \GFAPI::count_entries(
					$id,
					array(
						'status'        => 'active',
						'field_filters' => array(
							array(
								'key'   => 'is_read',
								'value' => 0,
							),
						),
					)
				),
				'is_active' => ! empty( $form['is_active'] ),
			);
		}
		return $forms;
	}

	/**
	 * Active feed add-on slugs per form ID.
	 *
	 * @return array<int, list<string>>
	 */
	private function feeds(): array {
		$feeds = \GFAPI::get_feeds( null, null, null, true );
		if ( is_wp_error( $feeds ) ) {
			return array();
		}

		$by_form = array();
		foreach ( (array) $feeds as $feed ) {
			$by_form[ (int) $feed['form_id'] ][] = (string) $feed['addon_slug'];
		}
		return $by_form;
	}

	/**
	 * Published posts of a type between two local dates.
	 *
	 * @param string      $post_type Post type.
	 * @param string      $from      Y-m-d, inclusive.
	 * @param string|null $before    Y-m-d, exclusive; null for "until now".
	 */
	private function count_published( string $post_type, string $from, ?string $before ): int {
		// Separate clauses: 'inclusive' applies to both bounds of one clause, which
		// would count a post at exactly midnight on the week-start day in both weeks.
		$range = array(
			array(
				'after'     => $from . ' 00:00:00',
				'inclusive' => true,
			),
		);
		if ( null !== $before ) {
			$range[] = array(
				'before'    => $before . ' 00:00:00',
				'inclusive' => false,
			);
		}

		$query = new \WP_Query(
			array(
				'post_type'              => $post_type,
				'post_status'            => 'publish',
				'posts_per_page'         => 1,
				'fields'                 => 'ids',
				'date_query'             => $range,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		return (int) $query->found_posts;
	}

	/**
	 * Render the unread-messages list.
	 *
	 * @param array{rows: list<array{id:int,title:string,unread:int}>, total:int} $inbox From Pulse::inbox().
	 */
	private function render_inbox( array $inbox ): void {
		echo '<h3><strong>' . esc_html__( 'Inbox', 'lwtv-underscores' ) . '</strong></h3>';

		if ( empty( $inbox['rows'] ) ) {
			echo '<p>' . esc_html__( 'No message forms found.', 'lwtv-underscores' ) . '</p>';
			return;
		}

		echo '<ul>';
		foreach ( $inbox['rows'] as $row ) {
			echo '<li>';
			if ( $row['unread'] > 0 ) {
				$url = admin_url( 'admin.php?page=gf_entries&id=' . $row['id'] . '&filter=unread' );
				/* translators: %s: number of unread entries */
				$count = sprintf( _n( '%s unread', '%s unread', $row['unread'], 'lwtv-underscores' ), number_format_i18n( $row['unread'] ) );
				echo '<a href="' . esc_url( $url ) . '">' . esc_html( $row['title'] ) . '</a> &mdash; <strong>' . esc_html( $count ) . '</strong>';
			} else {
				echo esc_html( $row['title'] ) . ' &mdash; ' . esc_html__( 'all read', 'lwtv-underscores' );
			}
			echo '</li>';
		}
		echo '</ul>';
	}

	/**
	 * Render shows/characters/actors added this week vs last week.
	 */
	private function render_added(): void {
		$weeks     = Pulse::week_bounds( current_time( 'Y-m-d' ), (int) get_option( 'start_of_week', 1 ) );
		$this_week = array();
		$last_week = array();
		foreach ( Pulse::TYPES as $type ) {
			$this_week[ $type ] = $this->count_published( 'post_type_' . $type, $weeks['this_start'], null );
			$last_week[ $type ] = $this->count_published( 'post_type_' . $type, $weeks['last_start'], $weeks['this_start'] );
		}

		$labels = array(
			'shows'      => __( 'Shows', 'lwtv-underscores' ),
			'characters' => __( 'Characters', 'lwtv-underscores' ),
			'actors'     => __( 'Actors', 'lwtv-underscores' ),
		);

		echo '<h3><strong>' . esc_html__( 'Added this week', 'lwtv-underscores' ) . '</strong></h3><ul>';
		foreach ( Pulse::added( $this_week, $last_week ) as $row ) {
			$url = admin_url( 'edit.php?post_type=post_type_' . $row['type'] . '&orderby=date&order=desc' );
			/* translators: %s: number published last week */
			$last = sprintf( __( '(last week: %s)', 'lwtv-underscores' ), number_format_i18n( $row['last'] ) );
			echo '<li><a href="' . esc_url( $url ) . '">' . esc_html( $labels[ $row['type'] ] ) . '</a> &mdash; <strong>' . esc_html( number_format_i18n( $row['this'] ) ) . '</strong> ' . esc_html( $last ) . '</li>';
		}
		echo '</ul>';
	}

	/**
	 * Render the failing API routes (admins only; caller checks).
	 */
	private function render_api(): void {
		/* translators: %d: number of days */
		echo '<h3><strong>' . esc_html( sprintf( __( 'API: most errors, last %d days', 'lwtv-underscores' ), Api_Usage::RANGE_DAYS ) ) . '</strong></h3>';

		// Same arguments as the API Usage page's default view, so the two share a cache and agree.
		$data = Api_Usage::load( (string) Api_Usage::RANGE_DAYS, Api_Known_Consumers::internal_labels( Api_Usage::known_consumers() ) );
		if ( isset( $data['error'] ) || ! isset( $data['report']['routes'] ) ) {
			echo '<p>' . esc_html__( 'API usage data unavailable.', 'lwtv-underscores' ) . '</p>';
		} else {
			$rows = Pulse::api( $data['report']['routes'] );
			if ( empty( $rows ) ) {
				/* translators: %d: number of days */
				echo '<p>' . esc_html( sprintf( __( 'No failing routes in the last %d days.', 'lwtv-underscores' ), Api_Usage::RANGE_DAYS ) ) . '</p>';
			} else {
				echo '<ul>';
				foreach ( $rows as $row ) {
					$detail = sprintf(
						/* translators: 1: 5xx count, 2: 404 count, 3: date of last 5xx or a dash */
						__( '%1$s × 5xx · %2$s × 404 · last 5xx %3$s', 'lwtv-underscores' ),
						number_format_i18n( $row['s5xx'] ),
						number_format_i18n( $row['s404'] ),
						$row['last_5xx'] ?: '—'
					);
					echo '<li><code>' . esc_html( Api_Route_Matcher::label( $row['route'] ) ) . '</code><br />' . esc_html( $detail ) . '</li>';
				}
				echo '</ul>';
			}
		}

		echo '<a href="' . esc_url( admin_url( 'admin.php?page=lwtv_api_usage' ) ) . '" class="button-secondary">' . esc_html__( 'Go to API Usage', 'lwtv-underscores' ) . '</a>';
	}
}
