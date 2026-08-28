<?php
/*
 * Validation: Watch Providers For LezWatch.TV
 *
 * The problems with host-to-provider resolution, and the controls to fix them.
 * Two problem classes:
 *
 *   - A host in use with no term, so the front end guesses its name. Fixable
 *     here: assign an existing term, or create one.
 *   - A host claimed by two terms. Reported only -- deciding which term is right
 *     needs a human, so there is no button.
 *
 * The two classes are cached differently, on purpose.
 *
 * Hosts needing a term are a **stored worklist** (Watch_Hosts::scan_unregistered,
 * TRANSIENT_UNREGISTERED) behind the same Run Scan / Recheck button every other
 * validator tab has, with the same nonce and field names. Recheck re-tests only
 * the listed hosts and drops the ones that now have a term; it does not look for
 * hosts that have appeared since. That is what makes it a worklist rather than a
 * readout -- it shrinks as you work down it and does not grow under you.
 *
 * The saving is not the point and would not justify a cache: host matching made
 * this two queries either way. Consistency with the other ten tabs is the point,
 * and so is a list that holds still.
 *
 * Contested hosts are read **live** from Watch_Hosts::host_collisions(), a free
 * byproduct of the map the scan already builds. Caching a free thing would only
 * add staleness, and a collision is urgent in a way a missing term is not.
 *
 * The `watchhosts` debugger check (Debugger\Watch_Host_Collisions) covers the
 * same collisions for cron, the CLI and this tab's count badge, which need a
 * stored number. This tab does not read its transient.
 *
 * Three actions, and they are not the same shape:
 *
 *   - Assigning or creating a term is a local write. Instant, safe in a request.
 *   - Looking up names fetches third-party hosts over HTTP. That is capped hard
 *     (Watch_Hosts::UI_BATCH) so a button press can't sit for minutes; the
 *     unbounded version lives in `wp lwtv waystowatch enrich` and on cron.
 */

namespace LWTV\Validator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\CPTs\Shows\Watching\Watch_Host_Names;
use LWTV\CPTs\Shows\Watching\Watch_Hosts;
use LWTV\CPTs\Shows\Watching\Watch_Term_Match;
use LWTV\Theme\Ways_To_Watch as Theme_Ways_To_Watch;

class Watch_Providers {

	/**
	 * admin-post actions.
	 */
	const ACTION_CREATE = 'lwtv_watch_create_term';
	const ACTION_ASSIGN = 'lwtv_watch_assign_term';
	const ACTION_LOOKUP = 'lwtv_watch_lookup_names';

	/**
	 * Nonce for the scan form.
	 *
	 * Spelled out rather than derived, but deliberately identical to what
	 * Validator\Report builds from a tab slug ('run_' . $tab . '_clicked'), so
	 * this tab's scan form is indistinguishable from the other ten.
	 */
	const NONCE = 'run_watch_providers_clicked';

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
		add_action( 'admin_post_' . self::ACTION_ASSIGN, array( $this, 'handle_assign' ) );
		add_action( 'admin_post_' . self::ACTION_LOOKUP, array( $this, 'handle_lookup' ) );
	}

	/**
	 * Render the tab.
	 *
	 * @return void
	 */
	public static function make(): void {
		self::show_notice();

		$items = lwtv_plugin()->get_stored( Watch_Hosts::TRANSIENT_UNREGISTERED );

		/*
		 * Same shape as Validator\Report, deliberately: same nonce naming, same
		 * `rerun` / `recheck` field names, same auto-scan on a cold cache. Ten
		 * tabs behaving one way and this one behaving another would be a worse
		 * problem than anything it could buy.
		 */
		if ( ( isset( $_POST['rerun'] ) && check_admin_referer( self::NONCE ) ) || false === $items ) {
			$items = Watch_Hosts::scan_unregistered();
		}

		if ( isset( $_POST['recheck'] ) && check_admin_referer( self::NONCE ) && ! empty( $items ) ) {
			$items = Watch_Hosts::scan_unregistered( (array) $items );
		}

		$items        = is_array( $items ) ? $items : array();
		$unregistered = array();

		foreach ( $items as $item ) {
			$host = (string) ( $item['host'] ?? '' );

			if ( '' !== $host ) {
				$unregistered[ $host ] = (int) ( $item['shows'] ?? 0 );
			}
		}

		// Collisions stay live. They are a byproduct of the host map the scan
		// already built, so caching them would add staleness to something free,
		// and a contested host is urgent in a way a missing term is not.
		$collisions = Watch_Hosts::host_collisions();
		$total      = count( Watch_Hosts::in_use() );
		$can_manage = current_user_can( self::CAP_MANAGE );

		self::render_summary( count( $unregistered ), count( $collisions ), $total );

		if ( ! empty( $collisions ) ) {
			self::render_collisions( $collisions );
		}

		if ( ! empty( $unregistered ) ) {
			self::render_lookup_form( $unregistered );

			$terms = get_terms(
				array(
					'taxonomy'   => Theme_Ways_To_Watch::TAXONOMY,
					'hide_empty' => false,
					'orderby'    => 'name',
					'fields'     => 'id=>name',
				)
			);
			$terms = is_wp_error( $terms ) ? array() : $terms;

			if ( $can_manage ) {
				self::render_bulk_toggle();
			}
			?>
			<div class="lwtv-tools-table">
				<table class="widefat fixed" cellspacing="0">
					<?php
					/*
					 * Explicit widths because the provider-term cell holds a
					 * select and a text field. Four equal columns leave it too
					 * narrow, and `fixed` means the contents overflow the cell
					 * rather than widening it -- which is exactly what the first
					 * version of this column did.
					 */
					?>
					<colgroup>
						<col style="width:26%" />
						<col style="width:8%" />
						<col style="width:22%" />
						<col style="width:44%" />
					</colgroup>
					<thead><tr>
						<th class="manage-column column-title column-primary" scope="col"><?php esc_html_e( 'Host', 'lwtv' ); ?></th>
						<th class="manage-column column-comments num" scope="col"><?php esc_html_e( 'Shows', 'lwtv' ); ?></th>
						<th class="manage-column column-author" scope="col"><?php esc_html_e( 'Renders as', 'lwtv' ); ?></th>
						<th class="manage-column column-watchurl_term" scope="col"><?php esc_html_e( 'Provider term', 'lwtv' ); ?></th>
					</tr></thead>
					<tbody>
						<?php
						$number = 0;
						foreach ( $unregistered as $host => $count ) {
							++$number;
							self::render_row( $host, $count, $can_manage, 0 === $number % 2, $terms );
						}
						?>
					</tbody>
				</table>
			</div>

			<?php
			if ( $can_manage ) {
				self::render_term_options( $terms );
			} else {
				?>
				<p><em><?php esc_html_e( 'You need permission to manage categories to create or assign provider terms.', 'lwtv' ); ?></em></p>
				<?php
			}
		}

		// Last, below the table, where Validator\Report puts it.
		self::render_scan_form( ! empty( $unregistered ) );
	}

	/**
	 * The count line above the table.
	 *
	 * Every host in use is accounted for here, which is why the table below can
	 * be problems-only: a host with a term is not a problem and does not need a
	 * row.
	 *
	 * @param int $unresolved Hosts with no term.
	 * @param int $contested  Hosts claimed by more than one term.
	 * @param int $total      Hosts in use.
	 * @return void
	 */
	private static function render_summary( int $unresolved, int $contested, int $total ): void {
		$clean = ( 0 === $unresolved && 0 === $contested );
		?>
		<div class="lwtv-tools-container lwtv-tools-container__alert">
			<h3>
				<span class="dashicons <?php echo esc_attr( $clean ? 'dashicons-yes' : 'dashicons-warning' ); ?>"></span>
				<?php
				if ( $clean ) {
					esc_html_e( 'Excellent!', 'lwtv' );
				} else {
					printf(
						/* translators: %d: number of hosts. */
						esc_html( _n( '%d host needs a provider term', '%d hosts need a provider term', $unresolved, 'lwtv' ) ),
						absint( $unresolved )
					);
				}
				?>
			</h3>
			<div id="lwtv-tools-alerts">
				<p>
					<?php
					printf(
						/* translators: 1: hosts with no term, 2: total hosts in use. */
						esc_html__( '%1$d of %2$d hosts in use have no term, so the front end guesses a name from the hostname. A term fixes the name permanently and lets you use Hide Display.', 'lwtv' ),
						absint( $unresolved ),
						absint( $total )
					);

					if ( $contested ) {
						echo ' ';
						printf(
							/* translators: %d: number of contested hosts. */
							esc_html( _n( '%d host is claimed by more than one term.', '%d hosts are claimed by more than one term.', $contested, 'lwtv' ) ),
							absint( $contested )
						);
					}
					?>
				</p>
				<p>
					<?php esc_html_e( 'Web series each live on their own domain, so this list will never be empty. Work down from the top: those are the hosts most readers actually reach.', 'lwtv' ); ?>
				</p>
				<p class="description">
					<?php esc_html_e( 'Contested hosts below are always current. The list of hosts needing a term is stored, so press Recheck after creating terms elsewhere — it clears anything that has since been given one, whoever did it.', 'lwtv' ); ?>
				</p>
			</div>
		</div>
		<?php
	}

	/**
	 * Run Scan / Recheck, as every other validator tab has it.
	 *
	 * Self-POSTs back to the tab rather than through admin-post.php, matching
	 * Validator\Report. The two write actions on this tab do go through
	 * admin-post.php, because they redirect with a notice; a scan just re-renders.
	 *
	 * Rendered below the table, also matching Report. The table is what an editor
	 * came for; the button is what they reach for once they have worked through
	 * it, so it belongs at the end of the list rather than above it.
	 *
	 * @param bool $has_items Whether the worklist currently holds anything.
	 * @return void
	 */
	private static function render_scan_form( bool $has_items ): void {
		$field = $has_items ? 'recheck' : 'rerun';
		$label = $has_items ? __( 'Recheck', 'lwtv' ) : __( 'Run Scan', 'lwtv' );
		?>
		<form action="<?php echo esc_url( admin_url( 'admin.php?page=lwtv_data_check&tab=tab_watch_providers' ) ); ?>" method="post">
			<?php wp_nonce_field( self::NONCE ); ?>
			<input type="hidden" value="true" name="<?php echo esc_attr( $field ); ?>" />
			<p class="submit">
				<?php submit_button( $label, 'primary', '', false ); ?>
			</p>
			<p class="description">
				<?php
				if ( $has_items ) {
					esc_html_e( 'Re-checks the hosts already listed and drops any that now have a term, however it got one. It will not look for hosts that have appeared since the last full scan — use Run Scan for that.', 'lwtv' );
				} else {
					esc_html_e( 'Checks every host in the Ways to Watch fields against the provider terms.', 'lwtv' );
				}
				?>
			</p>
		</form>
		<?php
	}

	/**
	 * Hosts claimed by more than one provider term.
	 *
	 * Read live from the same host map the list above uses, not from the
	 * `watchhosts` check's transient — so this can never disagree with what the
	 * front end is actually resolving. That check exists to put a number in the
	 * status option for cron, the CLI and this tab's badge.
	 *
	 * No fix button on purpose. Resolving a collision means deciding which term
	 * is right, and nothing here can decide that.
	 *
	 * @param array<string, array<int, string>> $collisions host => term_id => name.
	 * @return void
	 */
	private static function render_collisions( array $collisions ): void {
		?>
		<div class="lwtv-tools-table">
			<h3><?php esc_html_e( 'Contested hosts', 'lwtv' ); ?></h3>
			<p class="description">
				<?php esc_html_e( 'Two terms claim the same host. The front end renders whichever sorts first by name, which is stable but arbitrary. Remove the URL from whichever term is wrong, or merge them with wp lwtv waystowatch merge.', 'lwtv' ); ?>
			</p>
			<table class="widefat fixed" cellspacing="0">
				<thead><tr>
					<th class="manage-column column-title column-primary" scope="col"><?php esc_html_e( 'Host', 'lwtv' ); ?></th>
					<th class="manage-column column-author" scope="col"><?php esc_html_e( 'Claimed by', 'lwtv' ); ?></th>
				</tr></thead>
				<tbody>
					<?php
					$number = 0;
					foreach ( $collisions as $host => $terms ) {
						++$number;
						?>
						<tr class="<?php echo esc_attr( 0 === $number % 2 ? 'alternate' : '' ); ?>">
							<td><strong><?php echo esc_html( $host ); ?></strong></td>
							<td>
								<?php
								$links   = array();
								$winning = array_key_first( $terms );

								foreach ( $terms as $term_id => $term_name ) {
									$edit  = get_edit_term_link( (int) $term_id, Theme_Ways_To_Watch::TAXONOMY );
									$label = Theme_Ways_To_Watch::term_name( $term_name );

									if ( $term_id === $winning ) {
										/* translators: %s: provider term name. */
										$label = sprintf( __( '%s (wins)', 'lwtv' ), $label );
									}

									$links[] = $edit
										? '<a href="' . esc_url( $edit ) . '">' . esc_html( $label ) . '</a>'
										: esc_html( $label );
								}

								echo wp_kses_post( implode( ' &middot; ', $links ) );
								?>
							</td>
						</tr>
						<?php
					}
					?>
				</tbody>
			</table>
		</div>
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
	private static function render_row( string $host, int $count, bool $can_manage, bool $alt, array $terms = array() ): void {
		$proposed   = Watch_Hosts::proposed_name( $host );
		$discovered = Watch_Host_Names::get( $host );
		$slug       = md5( $host );
		$field_id   = 'lwtv-watch-name-' . $slug;
		$select_id  = 'lwtv-watch-term-' . $slug;
		$panel_id   = 'lwtv-watch-panel-' . $slug;

		/*
		 * Does a term already exist under a slightly different spelling? This is
		 * how the data grew "Lesflicks" beside "LezFlicks", so the tab looks
		 * before it offers to create. Exact-after-canonicalisation only -- see
		 * Watch_Term_Match on why this is not fuzzy.
		 */
		$suggested_id   = Watch_Term_Match::suggest( $host, $proposed, $terms );
		$suggested_name = $suggested_id ? Theme_Ways_To_Watch::term_name( (string) ( $terms[ $suggested_id ] ?? '' ) ) : '';
		?>
		<tr class="<?php echo esc_attr( $alt ? 'alternate' : '' ); ?>">
			<td>
				<strong><a href="<?php echo esc_url( 'https://' . $host ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $host ); ?></a></strong>
			</td>
			<td><?php echo esc_html( (string) $count ); ?></td>
			<td>
				<?php echo esc_html( $proposed ); ?>
				<span class="lwtv-watch-provenance">
					<?php
					echo ' &middot; ';

					/*
					 * Three provenances, not two. "Guessed" alone does not say
					 * whether asking the site would help -- and for a host that has
					 * stopped answering, it never will. Worth saying so on the row
					 * rather than leaving it to be rediscovered.
					 */
					if ( null !== $discovered && '' !== $discovered ) {
						esc_html_e( 'from the site', 'lwtv' );
					} elseif ( ! Watch_Host_Names::should_ask( $host ) && Watch_Host_Names::attempts( $host ) ) {
						esc_html_e( 'guessed — host does not answer', 'lwtv' );
					} else {
						esc_html_e( 'guessed', 'lwtv' );
					}
					?>
				</span>
				<?php if ( $suggested_id ) : ?>
					<span class="lwtv-watch-suggestion">
						<?php
						printf(
							/* translators: %s: existing provider term name. */
							esc_html__( 'Looks like the existing term “%s”.', 'lwtv' ),
							esc_html( $suggested_name )
						);
						?>
					</span>
				<?php endif; ?>
			</td>
			<td>
				<?php if ( ! $can_manage ) : ?>
					&mdash;
				<?php else : ?>
					<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" class="lwtv-watch-assign">
						<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_ASSIGN ); ?>" />
						<input type="hidden" name="host" value="<?php echo esc_attr( $host ); ?>" />
						<?php wp_nonce_field( self::ACTION_ASSIGN . '_' . $host ); ?>

						<div class="lwtv-watch-primary">
							<?php
							/*
							 * Submits are told apart by `do`, never by which fields
							 * happen to be filled in. A select left on a real term
							 * while the editor meant to create cannot then quietly
							 * assign instead.
							 *
							 * `suggest` carries its term in a server-rendered hidden
							 * field rather than reading the select, so the one-click
							 * path works with no JavaScript -- the select is empty
							 * until the script fills it.
							 */
							if ( $suggested_id ) :
								?>
								<input type="hidden" name="suggested_term_id" value="<?php echo esc_attr( (string) $suggested_id ); ?>" />
								<button type="submit" name="do" value="suggest" class="button button-primary">
									<?php
									printf(
										/* translators: %s: existing provider term name. */
										esc_html__( 'Assign to “%s”', 'lwtv' ),
										esc_html( $suggested_name )
									);
									?>
								</button>
							<?php else : ?>
								<button type="submit" name="do" value="create" class="button button-secondary lwtv-watch-create">
									<?php
									printf(
										/* translators: %s: proposed provider name. */
										esc_html__( 'Create “%s”', 'lwtv' ),
										esc_html( $proposed )
									);
									?>
								</button>
							<?php endif; ?>
							<button type="button" class="button-link lwtv-watch-toggle" aria-expanded="false" aria-controls="<?php echo esc_attr( $panel_id ); ?>" hidden>
								<?php esc_html_e( 'more', 'lwtv' ); ?>
							</button>
						</div>

						<div class="lwtv-watch-panel" id="<?php echo esc_attr( $panel_id ); ?>" hidden>
							<p class="lwtv-watch-line">
								<label class="screen-reader-text" for="<?php echo esc_attr( $select_id ); ?>">
									<?php
									printf(
										/* translators: %s: hostname. */
										esc_html__( 'Existing provider term for %s', 'lwtv' ),
										esc_html( $host )
									);
									?>
								</label>
								<select id="<?php echo esc_attr( $select_id ); ?>" name="term_id" class="lwtv-watch-term">
									<option value="0"><?php esc_html_e( 'Assign to an existing term…', 'lwtv' ); ?></option>
								</select>
								<button type="submit" name="do" value="assign" class="button"><?php esc_html_e( 'Assign', 'lwtv' ); ?></button>
							</p>
							<p class="lwtv-watch-line">
								<label class="screen-reader-text" for="<?php echo esc_attr( $field_id ); ?>">
									<?php
									printf(
										/* translators: %s: hostname. */
										esc_html__( 'Name for the new term for %s', 'lwtv' ),
										esc_html( $host )
									);
									?>
								</label>
								<input type="text" id="<?php echo esc_attr( $field_id ); ?>" name="provider_name" value="<?php echo esc_attr( $proposed ); ?>" class="lwtv-watch-name" />
								<?php
								/*
								 * Always present, even when the primary button is
								 * also Create: a suggested row's primary is Assign,
								 * so this is the only way to say "no, make a new
								 * one" -- and refusing the suggestion has to stay
								 * one click away.
								 */
								?>
								<button type="submit" name="do" value="create" class="button"><?php esc_html_e( 'Create', 'lwtv' ); ?></button>
							</p>
						</div>
					</form>
				<?php endif; ?>
			</td>
		</tr>
		<?php
	}

	/**
	 * The one copy of the provider-term options, plus the script that clones it.
	 *
	 * Rendered once and cloned into every row's select, rather than echoed ~130
	 * times. With 80-odd terms the difference is ten thousand DOM nodes.
	 *
	 * Inline rather than an enqueued asset, matching the tab picker's script on
	 * this same screen (Admin_Menu\Validation) and its reasoning: it is
	 * progressive enhancement, it is a dozen lines, and an enqueued file would
	 * need a hook gate and a version constant to say the same thing.
	 *
	 * Degrades honestly. With no JavaScript every select holds only "create a
	 * new term" and the name field stays visible, which is exactly the behaviour
	 * this tab had before assignment existed.
	 *
	 * @param array<int, string> $terms term_id => name.
	 * @return void
	 */
	private static function render_term_options( array $terms ): void {
		?>
		<template id="lwtv-watch-term-options">
			<?php foreach ( $terms as $term_id => $term_name ) : ?>
				<option value="<?php echo esc_attr( (string) $term_id ); ?>"><?php echo esc_html( Theme_Ways_To_Watch::term_name( $term_name ) ); ?></option>
			<?php endforeach; ?>
		</template>
		<noscript>
			<p class="description">
				<?php esc_html_e( 'Assigning a host to an existing term, and renaming before you create one, both need JavaScript. Without it the Create button still works with the proposed name, and the Watch Urls taxonomy screen can do the rest.', 'lwtv' ); ?>
			</p>
		</noscript>
		<script>
		( function () {
			var options = document.getElementById( 'lwtv-watch-term-options' );
			var forms   = document.querySelectorAll( '.lwtv-watch-assign' );
			var bulk    = document.getElementById( 'lwtv-watch-showall' );
			var labels  =
			<?php
				echo wp_json_encode(
					array(
						'more' => __( 'more', 'lwtv' ),
						'less' => __( 'less', 'lwtv' ),
						'show' => __( 'Show all options', 'lwtv' ),
						'hide' => __( 'Hide all options', 'lwtv' ),
						/* translators: %s: provider name. */
						'make' => __( 'Create “%s”', 'lwtv' ),
					)
				);
			?>
			;

			if ( ! forms.length ) {
				return;
			}

			var rows = [];

			forms.forEach( function ( form ) {
				var toggle = form.querySelector( '.lwtv-watch-toggle' );
				var panel  = form.querySelector( '.lwtv-watch-panel' );
				var select = form.querySelector( '.lwtv-watch-term' );
				var name   = form.querySelector( '.lwtv-watch-name' );
				var create = form.querySelector( '.lwtv-watch-create' );

				if ( ! toggle || ! panel ) {
					return;
				}

				// The options exist once in the document and are cloned per row.
				// Echoing eighty of them into each of forty-five selects is
				// thousands of nodes for no gain.
				if ( options && select ) {
					select.appendChild( options.content.cloneNode( true ) );

					// A suggested row already names its term on the primary
					// button; pre-selecting it here means opening the panel shows
					// the same answer rather than an empty control that looks like
					// the suggestion was lost.
					var suggested = form.querySelector( 'input[name="suggested_term_id"]' );

					if ( suggested ) {
						select.value = suggested.value;
					}
				}

				// Only offered now we know the panel can be reached again.
				toggle.hidden = false;

				var show = function ( open ) {
					panel.hidden = ! open;
					toggle.setAttribute( 'aria-expanded', String( open ) );
					toggle.textContent = open ? labels.less : labels.more;
				};

				toggle.addEventListener( 'click', function () {
					show( panel.hidden );
				} );

				// Keep the primary button honest about what it will create.
				if ( name && create ) {
					name.addEventListener( 'input', function () {
						var value = name.value.trim();

						create.textContent = labels.make.replace( '%s', value );
						create.disabled    = ( '' === value );
					} );
				}

				rows.push( show );
			} );

			if ( bulk ) {
				bulk.hidden = false;

				bulk.addEventListener( 'click', function () {
					var opening = 'true' !== bulk.getAttribute( 'aria-expanded' );

					rows.forEach( function ( show ) {
						show( opening );
					} );

					bulk.setAttribute( 'aria-expanded', String( opening ) );
					bulk.textContent = opening ? labels.hide : labels.show;
				} );
			}
		}() );
		</script>
		<?php
	}

	/**
	 * Open every row's options at once.
	 *
	 * The per-row toggle is right for working down the list one host at a time,
	 * which is the normal way this page gets used. It is wrong for a session
	 * spent assigning a batch of hosts to terms that already exist, where it
	 * means the same click forty times.
	 *
	 * Hidden until the script unhides it: with no JavaScript there is nothing to
	 * expand, so offering the control would be a dead button.
	 *
	 * @return void
	 */
	private static function render_bulk_toggle(): void {
		?>
		<p class="lwtv-watch-bulk">
			<button type="button" class="button-link" id="lwtv-watch-showall" aria-expanded="false" hidden>
				<?php esc_html_e( 'Show all options', 'lwtv' ); ?>
			</button>
		</p>
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
			if ( Watch_Host_Names::should_ask( $host ) ) {
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
				/*
				 * "Still to check" is not "will clear if you press again". A host
				 * that fails to answer is deliberately not recorded, so a blip can
				 * retry -- which means a host that is permanently unreachable stays
				 * on this list for good, and `enrich --all` will not shift it
				 * either. Say so, rather than implying one more press finishes it.
				 */
				printf(
					/* translators: 1: number of hosts still to check, 2: WP-CLI command, already wrapped in a code element. */
					wp_kses_post( __( 'Asks each site what it calls itself, a few at a time. %1$d still to check; %2$s does the rest. Hosts that never answer are not recorded, so they stay on this list and are retried each run.', 'lwtv' ) ),
					(int) $pending,
					'<code>wp lwtv waystowatch enrich --all</code>'
				);
				?>
			</span>
		</form>
		<?php
	}

	/**
	 * Create a term for one host.
	 *
	 * Kept registered although the tab no longer posts here: a page loaded before
	 * the assign control shipped still has the old form in it, and dropping the
	 * action would give that editor a blank screen instead of a created term.
	 *
	 * @return void
	 */
	public function handle_create(): void {
		$host = isset( $_POST['host'] ) ? sanitize_text_field( wp_unslash( $_POST['host'] ) ) : '';

		check_admin_referer( self::ACTION_CREATE . '_' . $host );

		if ( ! current_user_can( self::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to create provider terms.', 'lwtv' ), '', array( 'response' => 403 ) );
		}

		$this->create_and_notify( $host );
	}

	/**
	 * Create a term named by the request and point it at a host.
	 *
	 * Shared by handle_create() and handle_assign(); the caller has already
	 * checked the nonce and the capability, and which nonce is theirs to know.
	 *
	 * @param string $host Hostname.
	 * @return void
	 */
	private function create_and_notify( string $host ): void {
		$name   = isset( $_POST['provider_name'] ) ? sanitize_text_field( wp_unslash( $_POST['provider_name'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified by the calling handler.
		$result = Watch_Hosts::create_term( $host, $name );

		if ( is_wp_error( $result ) ) {
			self::set_notice( 'error', $result->get_error_message() );
			self::redirect_back();
		}

		$term = get_term( (int) $result, Theme_Ways_To_Watch::TAXONOMY );
		$link = ( $term instanceof \WP_Term ) ? get_edit_term_link( $term->term_id, Theme_Ways_To_Watch::TAXONOMY ) : '';

		Watch_Hosts::forget_unregistered( $host );

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
	 * Point a host at a term: an existing one, or a new one.
	 *
	 * One handler for both, because from the editor's side it is one decision
	 * made in one control. `term_id` of 0 means "create", which is what the
	 * select's first option submits.
	 *
	 * @return void
	 */
	public function handle_assign(): void {
		$host = isset( $_POST['host'] ) ? sanitize_text_field( wp_unslash( $_POST['host'] ) ) : '';

		check_admin_referer( self::ACTION_ASSIGN . '_' . $host );

		if ( ! current_user_can( self::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to assign provider terms.', 'lwtv' ), '', array( 'response' => 403 ) );
		}

		/*
		 * Which button was pressed decides, not which fields are filled in. The
		 * select keeps its value when a row is collapsed and reopened, so
		 * inferring intent from a non-zero term_id could assign a host the editor
		 * meant to create a fresh term for.
		 */
		$action = isset( $_POST['do'] ) ? sanitize_key( wp_unslash( $_POST['do'] ) ) : '';

		/*
		 * Three intents, one form.
		 *
		 * `suggest` takes its term from a hidden field the server rendered, so the
		 * one-click path works without JavaScript -- the select is empty until the
		 * script fills it. `assign` takes the select. Both are still checked
		 * against the taxonomy by attach_host(), because a POSTed ID is a POSTed
		 * ID however it arrived.
		 */
		if ( 'suggest' === $action ) {
			$term_id = isset( $_POST['suggested_term_id'] ) ? absint( $_POST['suggested_term_id'] ) : 0;
		} elseif ( 'assign' === $action ) {
			$term_id = isset( $_POST['term_id'] ) ? absint( $_POST['term_id'] ) : 0;
		} else {
			$this->create_and_notify( $host );
			return;
		}

		if ( ! $term_id ) {
			self::set_notice( 'error', __( 'Pick a provider term to assign, or use the Create button.', 'lwtv' ) );
			self::redirect_back();
		}

		$result = Watch_Hosts::attach_host( $term_id, $host );

		if ( is_wp_error( $result ) ) {
			self::set_notice( 'error', $result->get_error_message() );
			self::redirect_back();
		}

		$term = get_term( (int) $result, Theme_Ways_To_Watch::TAXONOMY );
		$name = ( $term instanceof \WP_Term ) ? Theme_Ways_To_Watch::term_name( $term->name ) : '';

		Watch_Hosts::forget_unregistered( $host );

		self::set_notice(
			'success',
			sprintf(
				/* translators: 1: hostname, 2: provider name. */
				__( 'Pointed %1$s at “%2$s”.', 'lwtv' ),
				$host,
				$name
			),
			( $term instanceof \WP_Term ) ? get_edit_term_link( $term->term_id, Theme_Ways_To_Watch::TAXONOMY ) : ''
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

		// Belt: ask for more head-room where the host allows it. Braces: the
		// budget below, since this can be disabled and does nothing for a
		// web-server or proxy timeout anyway.
		if ( function_exists( 'set_time_limit' ) ) {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			@set_time_limit( Watch_Hosts::UI_TIME_BUDGET * 2 );
		}

		$started   = microtime( true );
		$found     = 0;
		$asked     = 0;
		$failed    = 0;
		$remaining = 0;

		foreach ( array_keys( Watch_Hosts::unregistered() ) as $host ) {
			if ( ! Watch_Host_Names::should_ask( $host ) ) {
				continue;
			}

			// Stop before starting a request that could run past the budget,
			// rather than after one already has. Slow hosts are the actual
			// failure mode here, not the number of them.
			$elapsed     = microtime( true ) - $started;
			$out_of_time = ( $elapsed + Watch_Hosts::UI_TIMEOUT ) > Watch_Hosts::UI_TIME_BUDGET;

			if ( $asked >= Watch_Hosts::UI_BATCH || $out_of_time ) {
				++$remaining;
				continue;
			}

			++$asked;
			$result = Watch_Hosts::discover_name( $host, Watch_Hosts::UI_TIMEOUT );

			if ( 'error' === $result['status'] ) {
				++$failed;

				// Recorded now, where it used to be dropped. Counting the failure
				// is what lets a host that is genuinely gone stop appearing on the
				// "still to check" list after MAX_ATTEMPTS.
				Watch_Host_Names::fail( $host );
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

		$message = sprintf(
			/* translators: 1: hosts asked, 2: names found, 3: hosts unreachable. */
			__( 'Asked %1$d host(s): %2$d published a name, %3$d were unreachable and will be retried.', 'lwtv' ),
			$asked,
			$found,
			$failed
		);

		if ( $remaining ) {
			$message .= ' ' . sprintf(
				/* translators: 1: hosts not yet asked, 2: WP-CLI command in a code element. */
				__( '%1$d still to check — press the button again, or run %2$s for the rest.', 'lwtv' ),
				$remaining,
				'<code>wp lwtv waystowatch enrich --all</code>'
			);
		}

		self::set_notice( $found ? 'success' : 'info', $message );
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
				<?php
				// wp_kses_post, not esc_html: these messages are ours and some
				// carry a <code> element naming a WP-CLI command. Nothing here is
				// user input.
				echo wp_kses_post( $notice['message'] );
				?>
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
