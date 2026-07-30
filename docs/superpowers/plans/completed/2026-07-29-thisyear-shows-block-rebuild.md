# This Year Shows Block Rebuild — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rebuild the shared This Year Shows block (On Air / New / Canceled) from card-grids into a sticky jump bar + hanging-gutter two-column list, driven by a new pure transform.

**Architecture:** A pure, unit-tested transform (`LWTV\This_Year\Build\Shows_Block`) produces the per-pane jump-bar model; the shared template (`show-block.php`) emits the jump bar + list markup with per-pane id prefixes; theme SCSS restyles the retained group classes and adds jump-bar/gutter/dot rules. No JavaScript. No builder/query changes.

**Tech Stack:** PHP 8.1+, WordPress theme partial, PHPUnit 11 (no-WP harness), SCSS (dart-sass via `npm run buildquick`, Node 24).

## Global Constraints

- **Never `git commit` without the user's explicit approval in the same turn.** Each task ends by *staging* and pausing; the user says when to commit. (Repo/user rule — overrides the skill's auto-commit steps.)
- **Zero net-new palette values; no new fonts.** Colours are existing `colors.$lwtv-*` tokens only; fonts are the theme's Open Sans / Oswald stacks — do not import Inter.
- **Nothing renders below `0.75rem` (12px).** Font sizes in `rem`; structural values (padding, gap, grid tracks, radii, dot sizes) in px, per `_stats.scss` convention.
- **Pure transforms only in `build/`** — no WP globals, `$wpdb`, meta reads, output, or i18n in `class-shows-block.php`. All i18n (`_n()`, `number_format_i18n()`, `esc_*`) stays in the template.
- **Data contract unchanged** — `$sb_by_name` / `$sb_by_format` / `$sb_by_country` = `[ groupKey => [ showName => {url,name,country,format,airdates} ] ]`, pre-sorted. Preserve group order (By Name / By Country alphabetical, markers first; By Format size-ordered).
- **PHP standard:** WordPress-Extra via `phpcs.xml.dist`; text domain `'lwtv'`. Class files named `class-*.php`, one class per file, namespace mirrors path under `LWTV\`.
- **Node build:** `nvm` fails silently in non-interactive shells — always `export NVM_DIR="$HOME/.nvm"; . "$NVM_DIR/nvm.sh"; nvm use; hash -r` before any `npm` build (needs Node 24.15 per `.nvmrc`). Build = `npm run buildquick`.
- **Live verification uses real Chrome** (`mcp__claude-in-chrome__*`) against `https://lwtv.local/...` — the in-app browser (`mcp__Claude_Browser__*`) cannot reach `lwtv.local`.

---

### Task 1: Pure transform `Shows_Block` + unit tests

**Files:**
- Create: `plugins/lwtv-plugin/php/this-year/build/class-shows-block.php`
- Create: `tests/unit/This_Year/ShowsBlockTest.php`
- Modify: `tests/bootstrap.php` (register the new class after line 26)

**Interfaces:**
- Produces:
  - `LWTV\This_Year\Build\Shows_Block::initial_of( string $key ): ?string` — uppercase Latin initial, or `null` (numeric / punctuation / non-Latin lead).
  - `LWTV\This_Year\Build\Shows_Block::jump_bar( array $group_keys, string $mode, array $counts = array() ): array` — ordered list of chip entries, each `[ 'label' => string, 'target' => ?int, 'struck' => bool, 'count' => ?int ]`. `$mode` is `'name'|'country'|'format'`. `target` is the zero-based group index (matches the template's iteration order), or `null` when `struck`. `count` is non-null only in `'format'` mode.

- [ ] **Step 1: Write the failing tests**

Create `tests/unit/This_Year/ShowsBlockTest.php`:

```php
<?php
/**
 * Unit tests for the Shows block view transforms.
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\This_Year;

use PHPUnit\Framework\TestCase;
use LWTV\This_Year\Build\Shows_Block;

class ShowsBlockTest extends TestCase {

	// ---- initial_of(): Latin initial or null. ----

	public function test_initial_of_latin_uppercased(): void {
		$this->assertSame( 'A', Shows_Block::initial_of( 'australia' ) );
		$this->assertSame( 'Z', Shows_Block::initial_of( 'Zambia' ) );
	}

	public function test_initial_of_non_letter_is_null(): void {
		$this->assertNull( Shows_Block::initial_of( '#' ) );
		$this->assertNull( Shows_Block::initial_of( '-' ) );
		$this->assertNull( Shows_Block::initial_of( '9-1-1' ) );
		$this->assertNull( Shows_Block::initial_of( 'รักสุดท้าย' ) );
	}

	public function test_initial_of_trims_whitespace(): void {
		$this->assertSame( 'B', Shows_Block::initial_of( '  belgium' ) );
	}

	// ---- jump_bar('name'): markers first, then A–Z with absent struck. ----

	public function test_jump_bar_name_markers_first_then_az(): void {
		$bar = Shows_Block::jump_bar( array( '#', '-', 'A', 'C' ), 'name' );

		// Two marker chips first, in key order, never struck.
		$this->assertSame( '#', $bar[0]['label'] );
		$this->assertSame( 0, $bar[0]['target'] );
		$this->assertFalse( $bar[0]['struck'] );
		$this->assertSame( '-', $bar[1]['label'] );
		$this->assertSame( 1, $bar[1]['target'] );

		// A–Z follows: A→2, B struck, C→3, D–Z struck.
		$az = array_slice( $bar, 2 );
		$this->assertCount( 26, $az );
		$this->assertSame( array( 'label' => 'A', 'target' => 2, 'struck' => false, 'count' => null ), $az[0] );
		$this->assertSame( array( 'label' => 'B', 'target' => null, 'struck' => true, 'count' => null ), $az[1] );
		$this->assertSame( array( 'label' => 'C', 'target' => 3, 'struck' => false, 'count' => null ), $az[2] );
		$this->assertTrue( $az[3]['struck'] ); // D
	}

	public function test_jump_bar_name_no_markers_when_none_present(): void {
		$bar = Shows_Block::jump_bar( array( 'A', 'B' ), 'name' );
		$this->assertCount( 26, $bar ); // A–Z only, no marker chips.
		$this->assertSame( 'A', $bar[0]['label'] );
	}

	// ---- jump_bar('country'): A–Z → first group of each initial, no markers. ----

	public function test_jump_bar_country_first_initial_wins(): void {
		$bar = Shows_Block::jump_bar( array( 'Australia', 'Austria', 'Belgium' ), 'country' );
		$this->assertCount( 26, $bar ); // A–Z only.
		$this->assertSame( 0, $bar[0]['target'] ); // A → first Australia, not Austria.
		$this->assertTrue( $bar[2]['struck'] );     // C absent.
		$this->assertSame( 2, $bar[1]['target'] );   // B → Belgium.
	}

	// ---- jump_bar('format'): one chip per group in order, with count. ----

	public function test_jump_bar_format_carries_counts_in_order(): void {
		$bar = Shows_Block::jump_bar(
			array( 'TV Show', 'Mini-Series', 'Web Series' ),
			'format',
			array( 'TV Show' => 50, 'Mini-Series' => 7, 'Web Series' => 3 )
		);
		$this->assertSame(
			array(
				array( 'label' => 'TV Show', 'target' => 0, 'struck' => false, 'count' => 50 ),
				array( 'label' => 'Mini-Series', 'target' => 1, 'struck' => false, 'count' => 7 ),
				array( 'label' => 'Web Series', 'target' => 2, 'struck' => false, 'count' => 3 ),
			),
			$bar
		);
	}

	// ---- Edge cases. ----

	public function test_jump_bar_empty_keys(): void {
		$name = Shows_Block::jump_bar( array(), 'name' );
		$this->assertCount( 26, $name );
		$this->assertTrue( $name[0]['struck'] );

		$this->assertSame( array(), Shows_Block::jump_bar( array(), 'format' ) );
	}
}
```

- [ ] **Step 2: Register the class in the bootstrap so the test can load it**

Add after `tests/bootstrap.php:26`:

```php
require_once __DIR__ . '/../plugins/lwtv-plugin/php/this-year/build/class-shows-block.php';
```

- [ ] **Step 3: Run the tests to verify they fail**

Run: `vendor/bin/phpunit --filter ShowsBlock`
Expected: FAIL — class `LWTV\This_Year\Build\Shows_Block` not found.

- [ ] **Step 4: Write the transform**

Create `plugins/lwtv-plugin/php/this-year/build/class-shows-block.php`:

```php
<?php
/**
 * Shows block view transforms for This Year.
 *
 * Pure array-in / array-out helpers that build the jump-bar model for the
 * Shows On Air / New Shows / Canceled Shows panes. No WordPress runtime
 * dependency — all i18n and WP calls stay in the template.
 *
 * @package LezWatch.TV
 */

namespace LWTV\This_Year\Build;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shapes the jump-bar model for the shared Shows block.
 */
class Shows_Block {

	/**
	 * The uppercase Latin initial of a group key, or null.
	 *
	 * @param string $key Group key (a letter marker, a country, or a format).
	 * @return string|null One of A–Z, or null for numeric / punctuation / non-Latin leads.
	 */
	public static function initial_of( string $key ): ?string {
		$first = mb_strtoupper( mb_substr( trim( $key ), 0, 1 ) );
		return ( 1 === preg_match( '/^[A-Z]$/', $first ) ) ? $first : null;
	}

	/**
	 * Ordered jump-bar chips for a pane.
	 *
	 * `target` is the zero-based index of the group to anchor to — the same
	 * order the template iterates the groups — or null when the chip is inert.
	 * Do not slugify keys into ids: '#' and '-' both sanitise to empty and
	 * collide, so the template anchors on this index (`g<target>`) instead.
	 *
	 * @param array  $group_keys Ordered pane group keys, exactly as rendered.
	 * @param string $mode       'name' | 'country' | 'format'.
	 * @param array  $counts     [ key => int ] show counts; used in 'format' mode only.
	 * @return array Ordered list of [ label, target, struck, count ].
	 */
	public static function jump_bar( array $group_keys, string $mode, array $counts = array() ): array {
		$keys = array_values( $group_keys );

		// Format: one chip per group, in the given (size) order, carrying its count.
		if ( 'format' === $mode ) {
			$chips = array();
			foreach ( $keys as $i => $key ) {
				$chips[] = array(
					'label'  => (string) $key,
					'target' => $i,
					'struck' => false,
					'count'  => (int) ( $counts[ (string) $key ] ?? 0 ),
				);
			}
			return $chips;
		}

		// Name / Country: marker chips (name only) then A–Z. First group with a
		// given initial wins the letter, since keys are pre-sorted alphabetically.
		$marker_chips  = array();
		$letter_index  = array();
		foreach ( $keys as $i => $key ) {
			$initial = self::initial_of( (string) $key );
			if ( null === $initial ) {
				if ( 'name' === $mode ) {
					$marker_chips[] = array(
						'label'  => (string) $key,
						'target' => $i,
						'struck' => false,
						'count'  => null,
					);
				}
				continue;
			}
			if ( ! isset( $letter_index[ $initial ] ) ) {
				$letter_index[ $initial ] = $i;
			}
		}

		$az = array();
		foreach ( range( 'A', 'Z' ) as $letter ) {
			$present = isset( $letter_index[ $letter ] );
			$az[]    = array(
				'label'  => $letter,
				'target' => $present ? $letter_index[ $letter ] : null,
				'struck' => ! $present,
				'count'  => null,
			);
		}

		return array_merge( $marker_chips, $az );
	}
}
```

- [ ] **Step 5: Run the tests to verify they pass**

Run: `vendor/bin/phpunit --filter ShowsBlock`
Expected: PASS (all cases green).

- [ ] **Step 6: Run the full suite + PHP lint**

Run: `vendor/bin/phpunit` then `composer lint`
Expected: full suite green (new tests added, no regressions); phpcs clean.

- [ ] **Step 7: Stage; pause for commit approval**

```bash
git add plugins/lwtv-plugin/php/this-year/build/class-shows-block.php tests/unit/This_Year/ShowsBlockTest.php tests/bootstrap.php
```
Ask the user before `git commit`.

---

### Task 2: Template markup rebuild

**Files:**
- Modify: `plugins/lwtv-plugin/php/this-year/templates/partials/show-block.php:51-110` (the `$lwtv_sb_render_pane` closure) and the three pane-render call sites at lines 216-226.

**Interfaces:**
- Consumes: `Shows_Block::jump_bar()` from Task 1.
- Produces: per-pane DOM ids `{slug}-jump`, `{slug}-g{i}` (slug ∈ `byname|byformat|bycountry`) that Task 3/4 SCSS targets via classes (not ids).

- [ ] **Step 1: Add the transform import guard at the top of the file**

The template runs in WordPress (autoloaded), so reference the class with its FQN. After the `ABSPATH` guard (around line 5), no `require` is needed — PSR-4 autoload covers `LWTV\This_Year\Build\Shows_Block`. Confirm by grepping how sibling templates call `Characters_On_Air` (they use the FQN directly). Use `\LWTV\This_Year\Build\Shows_Block::jump_bar(...)`.

- [ ] **Step 2: Rewrite the `$lwtv_sb_render_pane` closure**

Replace lines 51-110 with a closure that takes a pane slug and emits jump bar + list. The meta switch and empty-state are preserved; the card structure is replaced:

```php
/**
 * Render one pane: a sticky jump bar + a hanging-gutter two-column list
 * (or an empty-state line).
 *
 * @param array  $lwtv_sb_groups      [ groupKey => [ showName => {url,name,country,format} ] ].
 * @param string $lwtv_sb_meta_mode   'name'|'format'|'country' — which meta to show per item.
 * @param string $lwtv_sb_pane_accent Accent slug for the group-key colour.
 * @param string $lwtv_sb_slug        Pane slug ('byname'|'byformat'|'bycountry') — id prefix.
 */
$lwtv_sb_render_pane = static function ( array $lwtv_sb_groups, string $lwtv_sb_meta_mode, string $lwtv_sb_pane_accent, string $lwtv_sb_slug ) {
	if ( empty( $lwtv_sb_groups ) ) {
		?>
		<p class="lwtv-ty-group-empty"><?php esc_html_e( 'None this year.', 'lwtv' ); ?></p>
		<?php
		return;
	}

	$lwtv_sb_keys = array_keys( $lwtv_sb_groups );

	// Jump-bar model. Counts only matter for the format pane.
	$lwtv_sb_counts = array();
	if ( 'format' === $lwtv_sb_meta_mode ) {
		foreach ( $lwtv_sb_groups as $lwtv_sb_k => $lwtv_sb_v ) {
			$lwtv_sb_counts[ (string) $lwtv_sb_k ] = count( (array) $lwtv_sb_v );
		}
	}
	$lwtv_sb_mode = ( 'format' === $lwtv_sb_meta_mode ) ? 'format' : ( ( 'country' === $lwtv_sb_meta_mode ) ? 'country' : 'name' );
	$lwtv_sb_bar  = \LWTV\This_Year\Build\Shows_Block::jump_bar( $lwtv_sb_keys, $lwtv_sb_mode, $lwtv_sb_counts );

	// Eyebrow per pane.
	$lwtv_sb_eyebrow = ( 'format' === $lwtv_sb_mode )
		? __( 'Jump to a format', 'lwtv' )
		: ( ( 'country' === $lwtv_sb_mode ) ? __( 'Jump to a country', 'lwtv' ) : __( 'Jump to a letter', 'lwtv' ) );

	// Format dot class per format name.
	$lwtv_sb_dot_class = static function ( string $format ): string {
		switch ( $format ) {
			case 'TV Show':
				return 'lwtv-ty-sb-dot--tv';
			case 'Mini-Series':
				return 'lwtv-ty-sb-dot--mini';
			case 'Web Series':
				return 'lwtv-ty-sb-dot--web';
			default:
				return '';
		}
	};
	?>
	<nav class="lwtv-ty-sb-jump" id="<?php echo esc_attr( $lwtv_sb_slug ); ?>-jump" aria-label="<?php echo esc_attr( $lwtv_sb_eyebrow ); ?>">
		<span class="lwtv-stats-eyebrow"><?php echo esc_html( $lwtv_sb_eyebrow ); ?></span>
		<div class="lwtv-ty-sb-chips">
			<?php foreach ( $lwtv_sb_bar as $lwtv_sb_chip ) : ?>
				<?php
				$lwtv_sb_chip_label = $lwtv_sb_chip['label'];
				if ( null !== $lwtv_sb_chip['count'] ) {
					$lwtv_sb_chip_label .= ' ' . number_format_i18n( (int) $lwtv_sb_chip['count'] );
				}
				?>
				<?php if ( $lwtv_sb_chip['struck'] ) : ?>
					<span class="lwtv-ty-sb-chip lwtv-ty-sb-chip--empty"><?php echo esc_html( $lwtv_sb_chip_label ); ?></span>
				<?php else : ?>
					<a class="lwtv-ty-sb-chip" href="#<?php echo esc_attr( $lwtv_sb_slug . '-g' . $lwtv_sb_chip['target'] ); ?>"><?php echo esc_html( $lwtv_sb_chip_label ); ?></a>
				<?php endif; ?>
			<?php endforeach; ?>
		</div>
	</nav>

	<div class="lwtv-ty-group-grid lwtv-ty-group-grid--<?php echo esc_attr( $lwtv_sb_pane_accent ); ?>">
		<?php $lwtv_sb_i = 0; ?>
		<?php foreach ( $lwtv_sb_groups as $lwtv_sb_group_key => $lwtv_sb_shows ) : ?>
			<div class="lwtv-ty-sb-row" id="<?php echo esc_attr( $lwtv_sb_slug . '-g' . $lwtv_sb_i ); ?>">
				<div class="lwtv-ty-sb-gutter">
					<div class="lwtv-ty-sb-gutter-label">
						<span class="lwtv-ty-group-key"><?php echo esc_html( (string) $lwtv_sb_group_key ); ?></span>
						<span class="lwtv-ty-group-count">
							<?php
							/* translators: %s: number of shows in this group. */
							echo esc_html( sprintf( _n( '%s show', '%s shows', count( $lwtv_sb_shows ), 'lwtv' ), number_format_i18n( count( $lwtv_sb_shows ) ) ) );
							?>
						</span>
					</div>
					<a class="lwtv-ty-sb-top" href="#<?php echo esc_attr( $lwtv_sb_slug ); ?>-jump">
						<span aria-hidden="true">&uarr;</span> <?php esc_html_e( 'Top', 'lwtv' ); ?>
					</a>
				</div>

				<div class="lwtv-ty-sb-shows">
					<?php
					foreach ( $lwtv_sb_shows as $lwtv_sb_show ) :
						switch ( $lwtv_sb_meta_mode ) {
							case 'name':
								$lwtv_sb_meta = implode(
									' · ',
									array_filter(
										array(
											(string) ( $lwtv_sb_show['country'] ?? '' ),
											(string) ( $lwtv_sb_show['format'] ?? '' ),
										)
									)
								);
								break;
							case 'format':
								$lwtv_sb_meta = (string) ( $lwtv_sb_show['country'] ?? '' );
								break;
							default: // 'country'.
								$lwtv_sb_meta = (string) ( $lwtv_sb_show['format'] ?? '' );
								break;
						}
						$lwtv_sb_dot = $lwtv_sb_dot_class( (string) ( $lwtv_sb_show['format'] ?? '' ) );
						?>
						<div class="lwtv-ty-sb-item">
							<span class="lwtv-ty-sb-dot <?php echo esc_attr( $lwtv_sb_dot ); ?>" aria-hidden="true"></span>
							<a class="lwtv-ty-sb-title" href="<?php echo esc_url( $lwtv_sb_show['url'] ); ?>"><?php echo esc_html( $lwtv_sb_show['name'] ); ?></a>
							<?php if ( '' !== $lwtv_sb_meta ) : ?>
								<span class="lwtv-ty-group-meta"><?php echo esc_html( $lwtv_sb_meta ); ?></span>
							<?php endif; ?>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
			<?php ++$lwtv_sb_i; ?>
		<?php endforeach; ?>
	</div>
	<?php
};
```

- [ ] **Step 3: Update the three pane call sites to pass the slug**

Replace lines 216-226 call sites:

```php
<div class="tab-pane fade show active" id="lwtv-ty-sb-byname" role="tabpanel" aria-labelledby="lwtv-ty-sb-byname-tab">
	<?php $lwtv_sb_render_pane( $sb_by_name, 'name', $lwtv_sb_accent, 'byname' ); ?>
</div>

<div class="tab-pane fade" id="lwtv-ty-sb-byformat" role="tabpanel" aria-labelledby="lwtv-ty-sb-byformat-tab">
	<?php $lwtv_sb_render_pane( $sb_by_format, 'format', $lwtv_sb_accent, 'byformat' ); ?>
</div>

<div class="tab-pane fade" id="lwtv-ty-sb-bycountry" role="tabpanel" aria-labelledby="lwtv-ty-sb-bycountry-tab">
	<?php $lwtv_sb_render_pane( $sb_by_country, 'country', $lwtv_sb_accent, 'bycountry' ); ?>
</div>
```

- [ ] **Step 4: PHP lint**

Run: `composer lint`
Expected: clean (watch for escaping — the auto-escaped-function list in `phpcs.xml.dist` covers `lwtv_plugin`/`get_symbolicon`; everything else is `esc_*`-wrapped above).

- [ ] **Step 5: Confirm the class autoloads in the running theme**

Load `https://lwtv.local/this-year/2025/shows-on-air/` in real Chrome. Expected: no PHP fatal (`class not found`); page renders the jump bar + list (unstyled/rough is fine — SCSS is Task 3). Read the DOM to confirm per-pane ids: `byname-jump`, `byname-g0`, `bycountry-g0` all exist and are distinct.

- [ ] **Step 6: Stage; pause for commit approval**

```bash
git add plugins/lwtv-plugin/php/this-year/templates/partials/show-block.php
```
Ask the user before `git commit`.

---

### Task 3: SCSS — light mode

**Files:**
- Modify: `scss/addons/_stats.scss:1858-1936` (the Shows group block).
- Generated by build: `style.css`, `style.min.css`.

**Interfaces:**
- Consumes: the class names emitted in Task 2 (`lwtv-ty-sb-jump`, `-chips`, `-chip`, `-chip--empty`, `-row`, `-gutter`, `-gutter-label`, `-top`, `-shows`, `-item`, `-dot`, `-dot--{tv,mini,web}`) plus retained `lwtv-ty-group-grid`, `--{accent}`, `-key`, `-count`, `-meta`, `-empty`.

- [ ] **Step 1: Replace the card rules; restyle retained classes; add new rules**

In `scss/addons/_stats.scss`, within the same enclosing block, replace the `.lwtv-ty-group-card / -head / -list / -item` rules (keep `-grid`, `-key`, `-count`, `-meta`, `-empty`, and the `--{accent}` accent rules) and add the jump-bar/gutter/list rules. Target shape:

```scss
// Shows On Air / New Shows / Canceled Shows — jump bar + hanging-gutter list.
.lwtv-ty-group-grid {
	display: block; // vertical stack of rows now, not a 2-col grid.
}

.lwtv-ty-sb-jump {
	position: sticky;
	top: 64px; // provisional — re-measured in Task 4 against the real header offset.
	z-index: 5;
	display: flex;
	align-items: center;
	gap: 14px;
	flex-wrap: wrap;
	margin-bottom: 6px;
	padding: 11px 14px;
	border: 1px solid colors.$lwtv-grey-border;
	border-radius: 14px;
	background-color: colors.$white;
	box-shadow: 0 2px 10px rgba(15, 23, 42, 0.06);

	@media (max-width: 767px) {
		flex-wrap: nowrap;
		overflow-x: auto;
		scrollbar-width: none;
	}
}

.lwtv-ty-sb-chips {
	display: flex;
	flex-wrap: wrap;
	gap: 6px;

	@media (max-width: 767px) {
		flex-wrap: nowrap;
	}
}

.lwtv-ty-sb-chip {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	min-width: 28px;
	height: 28px;
	padding: 0 8px;
	border-radius: 6px;
	background-color: colors.$lwtv-grey;
	font-size: 0.812rem;
	font-weight: 700;
	text-decoration: none;
	white-space: nowrap;
}

.lwtv-ty-sb-chip--empty {
	background-color: transparent;
	color: colors.$lwtv-grey-medium;
	text-decoration: line-through;
}

.lwtv-ty-sb-row {
	display: grid;
	grid-template-columns: 150px 1fr;
	gap: 22px;
	padding: 14px 0;
	border-bottom: 1px solid colors.$lwtv-grey-border;
	scroll-margin-top: 72px; // provisional — re-measured in Task 4 (bar height + 8px).

	@media (max-width: 767px) {
		grid-template-columns: 1fr;
		gap: 8px;
	}
}

.lwtv-ty-sb-gutter {
	display: flex;
	flex-direction: column;
	justify-content: space-between;
	align-items: flex-end;
	text-align: right;

	@media (max-width: 767px) {
		align-items: flex-start;
		text-align: left;
	}
}

.lwtv-ty-group-key {
	font-family: $headingfontfamily;
	font-size: 1.25rem;
	font-weight: 500;
	line-height: 1.2;
}

.lwtv-ty-group-count {
	display: block;
	font-size: 0.75rem;
	font-variant-numeric: tabular-nums;
	color: colors.$lwtv-grey-medium;
}

.lwtv-ty-sb-top {
	margin-top: 8px;
	font-size: 0.75rem;
	font-weight: 700;
	letter-spacing: 0.06em;
	text-transform: uppercase;
	color: colors.$lwtv-grey-medium;
	text-decoration: none;
}

.lwtv-ty-sb-shows {
	columns: 2;
	column-gap: 26px;

	@media (max-width: 767px) {
		columns: 1;
	}
}

.lwtv-ty-sb-item {
	display: grid;
	grid-template-columns: 6px 1fr;
	gap: 2px 7px;
	padding: 4px 0;
	break-inside: avoid;
}

.lwtv-ty-sb-dot {
	grid-row: 1;
	grid-column: 1;
	width: 6px;
	height: 6px;
	margin-top: 7px;
	border-radius: 50%;
}

.lwtv-ty-sb-dot--tv { background-color: colors.$lwtv-blue-deep; }
.lwtv-ty-sb-dot--mini { background-color: colors.$lwtv-purple; }
.lwtv-ty-sb-dot--web { background-color: colors.$lwtv-green; }

.lwtv-ty-sb-title {
	grid-row: 1;
	grid-column: 2;
	font-size: 0.95rem;
	font-weight: 600;
	line-height: 1.35;
}

.lwtv-ty-group-meta {
	grid-column: 2;
	font-size: 0.75rem;
	color: colors.$lwtv-grey-medium;
}

.lwtv-ty-group-empty {
	margin: 0;
	font-size: 0.85rem;
	color: colors.$lwtv-grey-deep;
}

// Accent follows the view: group key + show links pick up the block-title colour.
.lwtv-ty-group-grid--blue .lwtv-ty-group-key,
.lwtv-ty-group-grid--blue .lwtv-ty-sb-title {
	color: colors.$lwtv-blue-deep;
}
.lwtv-ty-group-grid--pink .lwtv-ty-group-key,
.lwtv-ty-group-grid--pink .lwtv-ty-sb-title {
	color: colors.$lwtv-pink-deep;
}
.lwtv-ty-group-grid--amber .lwtv-ty-group-key,
.lwtv-ty-group-grid--amber .lwtv-ty-sb-title {
	color: colors.$lwtv-yellow-deep;
}
```

Confirm `$headingfontfamily` is in scope here (it is a global in `_fonts.scss`); if the enclosing block can't see it, reference the Oswald stack the file already uses for `.lwtv-ty-block-title`.

- [ ] **Step 2: Build the CSS (Node 24)**

Run:
```bash
export NVM_DIR="$HOME/.nvm"; . "$NVM_DIR/nvm.sh"; nvm use; hash -r
npm run buildquick
```
Expected: builds without `crypto is not defined` (that error = wrong Node). `style.css` / `style.min.css` regenerate.

- [ ] **Step 3: CSS + JS lint**

Run: `npm run lint:css` and `npm run lint:js`
Expected: clean.

- [ ] **Step 4: Live check (light mode)**

Reload `https://lwtv.local/this-year/2025/shows-on-air/` in real Chrome. Expected: jump bar renders as a sticky card of chips; groups are hanging-gutter rows with 2-balanced-column show lists; single-show groups are one short row; format dots show the three colours. Switch panes via the pills; each pane has its own bar.

- [ ] **Step 5: Stage; pause for commit approval**

```bash
git add scss/addons/_stats.scss style.css style.min.css
```
Ask the user before `git commit`.

---

### Task 4: Dark mode + measure-first anchoring + full live verification

**Files:**
- Modify: `scss/partials/_colors-dark.scss:751-780` (extend the existing Shows group dark rules).
- Modify: `scss/addons/_stats.scss` (finalise `.lwtv-ty-sb-jump { top }` and `.lwtv-ty-sb-row { scroll-margin-top }` from measurement).
- Generated by build: `style.css`, `style.min.css`.

**Interfaces:**
- Consumes: everything from Tasks 2–3. Reuses existing dark rules for `.lwtv-ty-group-grid--{accent} .lwtv-ty-group-key`, `-count`, `-meta`, `-empty`.

- [ ] **Step 1: Add the dark-mode rules**

In `scss/partials/_colors-dark.scss`, inside the same `color-mode(dark)` `#masthead`-nested scope as the existing Shows rules, add:

```scss
.lwtv-ty-sb-jump {
	background-color: colors.$lwtv-grey-deep;
	border-color: rgba(colors.$white, 0.12);
}

.lwtv-ty-sb-chip {
	background-color: rgba(colors.$white, 0.16);
}

// Struck letters keep the LIGHT grey (#999) — the dark grey (#6a5d5d) is
// unreadable on $lwtv-grey-deep, defeating the "which letters are empty" job.
.lwtv-ty-sb-jump span.lwtv-ty-sb-chip--empty {
	background-color: transparent;
	color: colors.$lwtv-grey-medium;
}

.lwtv-ty-sb-top:hover,
.lwtv-ty-sb-top:focus {
	color: colors.$lwtv-pink-medium;
}

.lwtv-ty-sb-dot--tv { background-color: colors.$lwtv-blue-light; }
.lwtv-ty-sb-dot--mini { background-color: colors.$lwtv-purple-light; }
.lwtv-ty-sb-dot--web { background-color: colors.$lwtv-green-light; }

.lwtv-ty-sb-row {
	border-bottom-color: rgba(colors.$white, 0.12);
}
```

Confirm the retained `.lwtv-ty-group-key` / `-count` / `-meta` dark rules still match the new markup (they select by class, which is preserved) — if the existing dark key rule scoped to `.lwtv-ty-group-grid--{accent} .lwtv-ty-group-key`, the `--{accent}` wrapper is still emitted, so it holds. Update the show-link dark selector from `.lwtv-ty-group-item a` to `.lwtv-ty-sb-title` if the existing rule referenced the old class.

- [ ] **Step 2: Build + lint**

Run:
```bash
export NVM_DIR="$HOME/.nvm"; . "$NVM_DIR/nvm.sh"; nvm use; hash -r
npm run buildquick && npm run lint:css
```
Expected: clean.

- [ ] **Step 3: Measure the sticky bar and fix the two magic numbers**

In real Chrome on the Shows On Air page (By Name pane, the tallest bar), measure via `javascript_tool`:
- the header/nav offset the bar must sit below → set `.lwtv-ty-sb-jump { top }`.
- the rendered bar height → set `.lwtv-ty-sb-row { scroll-margin-top: <bar height + 8px> }`.

Update both values in `_stats.scss`, rebuild (`npm run buildquick`), reload.

- [ ] **Step 4: Verify anchors land correctly**

Click By Name `#`, `-`, `A`, and a mid-alphabet letter; click a group's ↑ TOP. Expected: each jump lands with the group's **key and first show rows visible below** the sticky bar (not hidden behind it), and ↑ TOP returns to the bar. Confirm `#` and `-` land on distinct, correct groups (no id collision). On By Country, confirm each initial lands on the *first* country of that letter.

- [ ] **Step 5: Full cross-view / theme / viewport sweep**

Verify in real Chrome:
- **Shows On Air** (~225), **New Shows** (~30), **Canceled Shows** (~30) — layout holds at each real count (the size-independence claim); single-show groups are short rows, not empty cards.
- **Light and dark** — chips, struck letters (readable grey), dots (vivid in dark), ↑ TOP hover pink, key/meta/count colours.
- **Mobile (<768px)** — single column, gutter collapses to a left-aligned label row, jump bar scrolls horizontally rather than stacking to 4 rows.
- Anchors never resolve into a hidden pane (per-pane prefixes).

- [ ] **Step 6: Final gate — full suite + all linters**

Run:
```bash
vendor/bin/phpunit
composer lint
export NVM_DIR="$HOME/.nvm"; . "$NVM_DIR/nvm.sh"; nvm use; hash -r
npm run lint:css && npm run lint:js
```
Expected: all green/clean.

- [ ] **Step 7: Stage; pause for commit approval**

```bash
git add scss/partials/_colors-dark.scss scss/addons/_stats.scss style.css style.min.css
```
Ask the user before `git commit`.

---

## Notes for the implementer

- The old `.lwtv-ty-group-card / -head / -list / -item` classes have **no consumers** outside this partial and its SCSS (verified by grep) — safe to delete.
- Do **not** touch the callout derivation, count-up splice, pill markup, subtitle, footnote, or the three callers' empty-state guards.
- If `$headingfontfamily` / `colors.*` aren't visible at the point you're editing in `_stats.scss`, match the surrounding rules' existing references — don't hardcode hex or font names.
