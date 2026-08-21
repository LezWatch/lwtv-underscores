<?php
/*
 * Validation: Watch Providers For LezWatch.TV
 *
 * Lists Ways to Watch hosts that have no lez_watch_urls term, with a proposed
 * name, and lets an editor create the term in one click.
 *
 * Two actions, and they are not the same shape:
 *
 *   - Creating a term is a local write. Instant, safe in a page request.
 *   - Looking up names fetches third-party hosts over HTTP. That is capped hard
 *     (Watch_Hosts::UI_BATCH) so a button press can't sit for minutes; the
 *     unbounded version lives in `wp lwtv waystowatch enrich` and on cron.
 */

namespace LWTV\Validator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\CPTs\Shows\Watch_Host_Names;
use LWTV\CPTs\Shows\Watch_Hosts;
use LWTV\Theme\Ways_To_Watch as Theme_Ways_To_Watch;

class Watch_Providers {

	/**
	 * admin-post actions.
	 */
	const ACTION_CREATE = 'lwtv_watch_create_term';
	const ACTION_LOOKUP = 'lwtv_watch_lookup_names';

	/**
	 * Transient prefix for one-shot admin notices, per user.
	 */
	const NOTICE_PREFIX = 'lwtv_watch_notice_';

	/**
	 * Capability needed to create terms.
	 *
	 * Reading the list only needs the page's own cap; writing to a taxonomy
	 * should need taxonomy rights.
	 */
	const CAP_MANAGE = 'manage_categories';

	/**
	 * Hook the admin-post handlers.
	 *
	 * Must be called from somewhere that runs on *every* admin request.
	 * Admin_Menu\Validation::init() will not do -- it fires on `admin_menu`,
	 * which admin-post.php never triggers.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_post_' . self::ACTION_CREATE, array( $this, 'handle_create' ) );
		add_action( 'admin_post_' . self::ACTION_LOOKUP, array( $this, 'handle_lookup' ) );
	}

	/**
	 * Render the tab.
	 *
	 * @return void
	 */
	public static function make(): void {
		$unregistered = Watch_Hosts::unregistered();
		$total        = count( Watch_Hosts::in_use() );
		$can_manage   = current_user_can( self::CAP_MANAGE );

		self::show_notice();

		?>
		<div class="lwtv-tools-container lwtv-tools-container__alert">
			<?php if ( empty( $unregistered ) ) : ?>
				<h3><span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'Excellent!', 'lwtv' ); ?></h3>
				<div id="lwtv-tools-alerts">
					<p><?php esc_html_e( 'Every host in the Ways to Watch fields resolves to a provider term.', 'lwtv' ); ?></p>
				</div>
			<?php else : ?>
				<h3>
					<span class="dashicons dashicons-warning"></span>
					<?php
					printf(
						/* translators: %d: number of hosts. */
						esc_html( _n( '%d host without a provider term', '%d hosts without a provider term', count( $unregistered ), 'lwtv' ) ),
						count( $unregistered )
					);
					?>
				</h3>
				<div id="lwtv-tools-alerts">
					<p>
						<?php
						printf(
							/* translators: 1: unregistered host count, 2: total host count. */
							esc_html__( '%1$d of %2$d hosts in use have no term, so the front end guesses a name from the hostname. Creating a term fixes the name permanently and lets you use Hide Display.', 'lwtv' ),
							count( $unregistered ),
							absint( $total )
						);
						?>
					</p>
					<p>
						<?php esc_html_e( 'Web series each live on their own domain, so this list will never be empty. Work down from the top: those are the hosts most readers actually reach.', 'lwtv' ); ?>
					</p>
				</div>
			<?php endif; ?>
		</div>

		<?php if ( ! empty( $unregistered ) ) : ?>
			<?php self::render_lookup_form( $unregistered ); ?>

			<div class="lwtv-tools-table">
				<table class="widefat fixed" cellspacing="0">
					<thead><tr>
						<th class="manage-column column-title column-primary" scope="col"><?php esc_html_e( 'Host', 'lwtv' ); ?></th>
						<th class="manage-column column-comments num" scope="col"><?php esc_html_e( 'Shows', 'lwtv' ); ?></th>
						<th class="manage-column column-author" scope="col"><?php esc_html_e( 'Renders as', 'lwtv' ); ?></th>
						<th class="manage-column column-watchurl_term" scope="col"><?php esc_html_e( 'Create provider term', 'lwtv' ); ?></th>
					</tr></thead>
					<tbody>
						<?php
						$number = 0;
						foreach ( $unregistered as $host => $count ) {
							++$number;
							self::render_row( $host, $count, $can_manage, 0 === $number % 2 );
						}
						?>
					</tbody>
				</table>
			</div>

			<?php if ( ! $can_manage ) : ?>
				<p><em><?php esc_html_e( 'You need permission to manage categories to create provider terms.', 'lwtv' ); ?></em></p>
			<?php endif; ?>
		<?php endif; ?>
		<?php
	}

	/**
	 * One table row.
	 *
	 * @param string $host       Hostname.
	 * @param int    $count      Shows using it.
	 * @param bool   $can_manage Whether the user may create terms.
	 * @param bool   $alt        Zebra striping.
	 * @return void
	 */
	private static function render_row( string $host, int $count, bool $can_manage, bool $alt ): void {
		$proposed   = Watch_Hosts::proposed_name( $host );
		$discovered = Watch_Host_Names::get( $host );
		$field_id   = 'lwtv-watch-name-' . md5( $host );
		?>
		<tr class="<?php echo esc_attr( $alt ? 'alternate' : '' ); ?>">
			<td>
				<strong><a href="<?php echo esc_url( 'https://' . $host ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $host ); ?></a></strong>
			</td>
			<td><?php echo esc_html( (string) $count ); ?></td>
			<td>
				<?php echo esc_html( $proposed ); ?>
				<?php if ( null !== $discovered && '' !== $discovered ) : ?>
					<br /><small><?php esc_html_e( 'from the site itself', 'lwtv' ); ?></small>
				<?php else : ?>
					<br /><small><?php esc_html_e( 'guessed from the hostname', 'lwtv' ); ?></small>
				<?php endif; ?>
			</td>
			<td>
				<?php if ( $can_manage ) : ?>
					<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
						<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_CREATE ); ?>" />
						<input type="hidden" name="host" value="<?php echo esc_attr( $host ); ?>" />
						<?php wp_nonce_field( self::ACTION_CREATE . '_' . $host ); ?>
						<label class="screen-reader-text" for="<?php echo esc_attr( $field_id ); ?>">
							<?php esc_html_e( 'Provider name', 'lwtv' ); ?>
						</label>
						<input type="text" id="<?php echo esc_attr( $field_id ); ?>" name="provider_name" value="<?php echo esc_attr( $proposed ); ?>" class="regular-text" />
						<button type="submit" class="button button-secondary"><?php esc_html_e( 'Create term', 'lwtv' ); ?></button>
					</form>
				<?php else : ?>
					&mdash;
				<?php endif; ?>
			</td>
		</tr>
		<?php
	}

	/**
	 * The bounded "go and ask the hosts" form.
	 *
	 * @param array $unregistered host => count.
	 * @return void
	 */
	private static function render_lookup_form( array $unregistered ): void {
		$pending = 0;
		foreach ( array_keys( $unregistered ) as $host ) {
			if ( ! Watch_Host_Names::is_checked( $host ) ) {
				++$pending;
			}
		}

		if ( ! $pending || ! current_user_can( self::CAP_MANAGE ) ) {
			return;
		}

		$batch = min( $pending, Watch_Hosts::UI_BATCH );
		?>
		<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" style="margin: 1em 0;">
			<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_LOOKUP ); ?>" />
			<?php wp_nonce_field( self::ACTION_LOOKUP ); ?>
			<button type="submit" class="button">
				<?php
				printf(
					/* translators: %d: number of hosts. */
					esc_html( _n( 'Look up %d name', 'Look up %d names', $batch, 'lwtv' ) ),
					(int) $batch
				);
				?>
			</button>
			<span class="description">
				<?php
				printf(
					/* translators: %d: number of hosts still to check. */
					esc_html__( 'Asks each site what it calls itself. %d still to check — this page does a few at a time; `wp lwtv waystowatch enrich --all` does the lot.', 'lwtv' ),
					(int) $pending
				);
				?>
			</span>
		</form>
		<?php
	}

	/**
	 * Create a term for one host.
	 *
	 * @return void
	 */
	public function handle_create(): void {
		$host = isset( $_POST['host'] ) ? sanitize_text_field( wp_unslash( $_POST['host'] ) ) : '';

		check_admin_referer( self::ACTION_CREATE . '_' . $host );

		if ( ! current_user_can( self::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to create provider terms.', 'lwtv' ), '', array( 'response' => 403 ) );
		}

		$name   = isset( $_POST['provider_name'] ) ? sanitize_text_field( wp_unslash( $_POST['provider_name'] ) ) : '';
		$result = Watch_Hosts::create_term( $host, $name );

		if ( is_wp_error( $result ) ) {
			self::set_notice( 'error', $result->get_error_message() );
			self::redirect_back();
		}

		$term = get_term( (int) $result, Theme_Ways_To_Watch::TAXONOMY );
		$link = ( $term instanceof \WP_Term ) ? get_edit_term_link( $term->term_id, Theme_Ways_To_Watch::TAXONOMY ) : '';

		self::set_notice(
			'success',
			sprintf(
				/* translators: 1: provider name, 2: hostname. */
				__( 'Created “%1$s” and pointed it at %2$s.', 'lwtv' ),
				$name,
				$host
			),
			$link
		);

		self::redirect_back();
	}

	/**
	 * Look up a small batch of host names.
	 *
	 * @return void
	 */
	public function handle_lookup(): void {
		check_admin_referer( self::ACTION_LOOKUP );

		if ( ! current_user_can( self::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'lwtv' ), '', array( 'response' => 403 ) );
		}

		$found  = 0;
		$asked  = 0;
		$failed = 0;

		foreach ( array_keys( Watch_Hosts::unregistered() ) as $host ) {
			if ( $asked >= Watch_Hosts::UI_BATCH ) {
				break;
			}

			if ( Watch_Host_Names::is_checked( $host ) ) {
				continue;
			}

			++$asked;
			$result = Watch_Hosts::discover_name( $host );

			if ( 'error' === $result['status'] ) {
				++$failed;
				continue;
			}

			if ( '' !== $result['name'] ) {
				++$found;
				Watch_Host_Names::set( $host, $result['name'], $result['source'] );
				continue;
			}

			// Asked, published nothing usable. Recorded so we don't re-ask;
			// errors are deliberately not recorded, so a blip can retry.
			Watch_Host_Names::set( $host, '', Watch_Host_Names::SOURCE_NONE );
		}

		if ( ! $asked ) {
			self::set_notice( 'info', __( 'Every unregistered host has already been asked.', 'lwtv' ) );
			self::redirect_back();
		}

		self::set_notice(
			$found ? 'success' : 'info',
			sprintf(
				/* translators: 1: hosts asked, 2: names found, 3: hosts unreachable. */
				__( 'Asked %1$d host(s): %2$d published a name, %3$d were unreachable and will be retried.', 'lwtv' ),
				$asked,
				$found,
				$failed
			)
		);

		self::redirect_back();
	}

	/**
	 * Stash a one-shot notice for the current user.
	 *
	 * Replaces the old ?message= scheme, which never worked and could only
	 * express four hardcoded strings. See DEBUGGER-REVIEW.md section 1.9a.
	 *
	 * @param string $type    'success', 'error' or 'info'.
	 * @param string $message Text.
	 * @param string $link    Optional URL to offer afterwards.
	 * @return void
	 */
	private static function set_notice( string $type, string $message, string $link = '' ): void {
		set_transient(
			self::NOTICE_PREFIX . get_current_user_id(),
			array(
				'type'    => $type,
				'message' => $message,
				'link'    => $link,
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
			<p>
				<?php echo esc_html( $notice['message'] ); ?>
				<?php if ( ! empty( $notice['link'] ) ) : ?>
					<a href="<?php echo esc_url( $notice['link'] ); ?>"><?php esc_html_e( 'Edit the term', 'lwtv' ); ?></a>
				<?php endif; ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Back to the tab.
	 *
	 * @return void
	 */
	private static function redirect_back(): void {
		wp_safe_redirect( admin_url( 'admin.php?page=lwtv_data_check&tab=tab_watch_providers' ) );
		exit;
	}
}
