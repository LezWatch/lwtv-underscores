<?php
/**
 * Name: Admin Notice
 * Description: Tells admins when a Meilisearch index isn't set up here.
 *
 * Sync never creates an index, so an environment with the code but no index
 * silently skips that index's sync. This notice is how anyone finds out. It
 * reads the cached state; on a cache miss it checks once (short timeout) and
 * caches the answer, so admin pages aren't slowed.
 *
 * @package LWTV
 */

namespace LWTV\Meilisearch;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\Meilisearch\Build\Index_State;

class Admin_Notice {

	const CAPABILITY = 'manage_options';

	private Indexes $indexes;

	/**
	 * @param Indexes $indexes Every index.
	 */
	public function __construct( Indexes $indexes ) {
		$this->indexes = $indexes;
	}

	/**
	 * Register the hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_notices', array( $this, 'render' ) );
	}

	/**
	 * Print the notice on the Dashboard and LezWatch.TV screens.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( self::CAPABILITY ) || ! $this->is_our_screen() ) {
			return;
		}

		$missing = array_filter( $this->indexes->all(), fn( Index $index ) => Index_State::MISSING === $this->state( $index ) );
		if ( empty( $missing ) ) {
			return;
		}

		echo '<div class="notice notice-warning"><ul>';
		foreach ( $missing as $type => $index ) {
			echo '<li>';
			printf(
				/* translators: 1: index label, e.g. Shows; 2: index name; 3: WP-CLI command. */
				esc_html__( 'Meilisearch: the %1$s index (%2$s) isn\'t set up on this site. Run %3$s to create it.', 'lwtv-underscores' ),
				esc_html( $index->label() ),
				'<code>' . esc_html( $index->uid() ) . '</code>',
				'<code>' . esc_html( 'wp lwtv meili settings --type=' . $type ) . '</code>'
			);
			echo '</li>';
		}
		echo '</ul></div>';
	}

	/**
	 * An index's state, asking the server at most once per recheck window
	 * while it's unreachable.
	 *
	 * @param Index $index Index.
	 * @return string An Index_State constant.
	 */
	private function state( Index $index ): string {
		$key = 'lwtv_meili_notice_recheck_' . $index->uid();
		if ( get_transient( $key ) ) {
			return $index->state( false );
		}

		$state = $index->state();
		$wait  = Index_State::recheck_after( $state );
		if ( $wait > 0 ) {
			set_transient( $key, 1, $wait );
		}

		return $state;
	}

	/**
	 * Dashboard or a LezWatch.TV admin screen.
	 *
	 * @return bool
	 */
	private function is_our_screen(): bool {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		return $screen && ( 'dashboard' === $screen->id || false !== strpos( (string) $screen->id, 'lwtv' ) );
	}
}
