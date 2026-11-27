<?php
/**
 * Symbolicons
 *
 * Shows the symbolicons settings page, based on the contents in
 * /wp-content/uploads/lezpress-icons
 *
 * @version 2.0
 * @package library
 */

namespace LWTV\_Components;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Symbolicons implements Component, Templater {

	// Names of the files.
	const SPRITE_FILE = 'sprite.symbol.svg';
	const SPRITE_JSON = 'symbolicons.json';

	// Our admin icon collection (WP 7.2+). `core` and `core-admin` are reserved.
	const ICON_COLLECTION = 'lwtv';

	// The toaster outline for get_icon_svg(), the pre-7.2 admin menu icon (24x24 at scale 0.666667).
	const LOGO_PATH = 'M4,10c0,-4.411 3.589,-8 8,-8c4.411,0 8,3.589 8,8v2.08c0.706,0.102 1.378,0.308 2,0.605v-2.685c0,-5.514 -4.486,-10 -10,-10c-5.514,0 -10,4.486 -10,10v2.685c0.622,-0.297 1.294,-0.503 2,-0.605Zm8,-6c-3.309,0 -6,2.691 -6,6v1.025c0.578,-0.772 1.294,-1.43 2.112,-1.929c0.412,-1.77 1.994,-3.096 3.888,-3.096c1.894,0 3.476,1.326 3.888,3.096c0.819,0.499 1.534,1.157 2.112,1.929v-1.025c0,-3.309 -2.691,-6 -6,-6Zm7,10h-1.712c-0.654,-2.307 -2.771,-4 -5.288,-4c-2.517,0 -4.634,1.693 -5.288,4h-1.712c-2.761,0 -5,2.239 -5,5c0,2.761 2.239,5 5,5h14c2.761,0 5,-2.239 5,-5c0,-2.761 -2.239,-5 -5,-5Z';

	/**
	 * Icons in the `lwtv` collection that registered, keyed by full name.
	 * Null until register_icons() has run.
	 *
	 * @var array<string, true>|null
	 */
	protected $registered_icons = null;

	/*
	 * Init
	 */
	public function init(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
	}

	/**
	 * Gets tags to expose as methods accessible through `lwtv_plugin()`.
	 *
	 * @return array Associative array of $method_name => $callback_info pairs. Each $callback_info must either be
	 *               a callable or an array with key 'callable'. This approach is used to reserve the possibility of
	 *               adding support for further arguments in the future.
	 */
	public function get_template_tags(): array {
		return array(
			'get_icon_svg'   => array( $this, 'get_icon_svg' ),
			'get_menu_icon'  => array( $this, 'get_menu_icon' ),
			'get_admin_icon' => array( $this, 'get_admin_icon' ),
			'get_symbolicon' => array( $this, 'get_symbolicon' ),
		);
	}

	/**
	 * Get an admin icon name, with a fallback for older WordPress.
	 *
	 * On WP 7.2+ this returns `lwtv/<name>`, which core renders as an inline SVG
	 * that follows the admin color scheme. Before 7.2, or if the icon file is
	 * missing, it returns $fallback.
	 *
	 * @param string $name     Icon name in the lwtv collection, i.e. `show`.
	 * @param string $fallback What to use instead, i.e. `dashicons-video-alt`.
	 *
	 * @return string
	 */
	public function get_menu_icon( string $name, string $fallback = '' ): string {
		$icon = self::ICON_COLLECTION . '/' . $name;

		return isset( $this->register_icons()[ $icon ] ) ? $icon : $fallback;
	}

	/**
	 * Get an lwtv admin icon as inline SVG markup.
	 *
	 * For admin UI that core styles around an inline SVG, like At a Glance.
	 * Empty before WP 7.2 or if the icon is missing, so callers keep their
	 * Dashicon fallback.
	 *
	 * @param string $name Icon name in the lwtv collection, i.e. `show`.
	 * @param int    $size Width and height in pixels.
	 *
	 * @return string
	 */
	public function get_admin_icon( string $name, int $size = 20 ): string {
		$icon = $this->get_menu_icon( $name );

		if ( '' === $icon || ! function_exists( 'wp_get_icon' ) ) {
			return '';
		}

		return wp_get_icon( $icon, array( 'size' => $size ) );
	}

	/**
	 * Register the `lwtv` icon collection.
	 *
	 * The admin menu icons are SVGs in assets/images/admin-icons/: Font Awesome
	 * Free (CC BY 4.0, attribution kept in each file), plus the Search menu's
	 * meilisearch.svg from Bootstrap Icons (MIT). They are single colour, so
	 * core's `fill: currentColor` applies the scheme colors.
	 * The admin bar keeps the full colour toaster from the theme.
	 *
	 * Runs once per request, the first time an icon is asked for. Core only
	 * reads the files when an icon is rendered.
	 *
	 * @return array<string, true> Icons that registered, keyed by full name.
	 */
	public function register_icons(): array {
		if ( null !== $this->registered_icons ) {
			return $this->registered_icons;
		}

		$this->registered_icons = array();

		// wp_is_valid_icon_name() is new in 7.2. 7.1 has the registry but not the `public` arg or menu SVGs.
		if ( ! function_exists( 'wp_is_valid_icon_name' ) || ! function_exists( 'wp_register_icon_collection' ) || ! function_exists( 'wp_register_icon' ) ) {
			return $this->registered_icons;
		}

		$collection = wp_register_icon_collection(
			self::ICON_COLLECTION,
			array(
				'label'  => 'LezWatch.TV',
				'public' => false,
			)
		);

		if ( ! $collection ) {
			return $this->registered_icons;
		}

		$admin_icons = LWTV_PLUGIN_PATH . '/assets/images/admin-icons/';
		$icons       = array(
			'logo'        => array(
				'label'     => __( 'LezWatch.TV', 'lwtv-underscores' ),
				'file_path' => $admin_icons . 'logo.svg',
			),
			'toaster'     => array(
				'label'     => __( 'LezWatch.TV toaster', 'lwtv-underscores' ),
				'file_path' => get_theme_file_path( 'images/lwtv-toaster.svg' ),
			),
			'show'        => array(
				'label'     => __( 'Shows', 'lwtv-underscores' ),
				'file_path' => $admin_icons . 'show.svg',
			),
			'character'   => array(
				'label'     => __( 'Characters', 'lwtv-underscores' ),
				'file_path' => $admin_icons . 'character.svg',
			),
			'actor'       => array(
				'label'     => __( 'Actors', 'lwtv-underscores' ),
				'file_path' => $admin_icons . 'actor.svg',
			),
			'tvmaze'      => array(
				'label'     => __( 'TVMaze', 'lwtv-underscores' ),
				'file_path' => $admin_icons . 'tvmaze.svg',
			),
			'meilisearch' => array(
				'label'     => __( 'Search', 'lwtv-underscores' ),
				'file_path' => $admin_icons . 'meilisearch.svg',
			),
		);

		foreach ( $icons as $name => $args ) {
			// A missing file stays unregistered, so get_menu_icon() falls back.
			if ( ! is_readable( $args['file_path'] ) ) {
				continue;
			}

			$icon = self::ICON_COLLECTION . '/' . $name;
			if ( wp_register_icon( $icon, $args ) ) {
				$this->registered_icons[ $icon ] = true;
			}
		}

		return $this->registered_icons;
	}

	/*
	 * admin_enqueue_scripts
	 */
	public function admin_enqueue_scripts() {
		wp_register_style( 'symbolicons-admin', LWTV_PLUGIN_URL . '/assets/css/symbolicons-admin.css', array(), LWTV_PLUGIN_VERSION );
		wp_enqueue_style( 'symbolicons-admin' );
	}

	/*
	 * Settings
	 *
	 * Create our settings page
	 */
	public function add_settings_page() {
		add_theme_page( 'Symbolicons', 'Symbolicons', 'edit_posts', 'symbolicons', array( $this, 'settings_page' ) );
	}

	/*
	 * Settings Page Content
	 *
	 * A list of all the Symbolicons and how to use them. Kind of.
	 */
	public function settings_page() {
		?>
		<div class="wrap">

		<h2>Symbolicons</h2>

		<div class="notice notice-info">
			<p>Symbolics are used in code, not in the visual editor. To add them, use the wrapper function <code>lwtv_plugin()->get_symbolicon()</code> which has the following parameters:</p>
			<ul>
				<li><code>svg</code> - The name of the icon, i.e. <code>square</code>.</li>
				<li><code>icon</code> - The symbolicon icon name, i.e. <code>svg-square</code>.</li>
				<li><code>svg_class</code> - The SVG styling class name, i.e. <code>symbolicon</code>.</li>
				<li><code>max_size</code> - The <em>maximum</em> size of the icon, i.e. <code>32</code>.</li>
			</ul>

			<p>You can also select a symbolicon for certain taxonomies in the <a href="<?php echo esc_url( admin_url( 'edit-tags.php?taxonomy=lez_termsmeta&post_type=lez_terms' ) ); ?>">Terms</a> page.</p>
		</div>

		<?php
		// Make sure the sprite SVG and JSON files exist.
		if ( ! file_exists( LWTV_SYMBOLICONS_SPRITE_PATH . self::SPRITE_FILE ) || ! file_exists( LWTV_SYMBOLICONS_SPRITE_PATH . self::SPRITE_JSON ) ) {
			echo '<h3>OH NO!</h3>';
			echo '<p>The sprite SVG and/or JSON files do not exist. Please tell an Admin to upload them to the <code>/wp-content/uploads/lezpress-icons</code> directory.</p>';
		} else {
			echo '<h3>Available Symbolicons</h3>';
			$sprite_json = json_decode( file_get_contents( LWTV_SYMBOLICONS_SPRITE_PATH . self::SPRITE_JSON ), true );

			// for each icon in the sprite SVG, show the icon.
			foreach ( $sprite_json as $icon ) {
				$name = str_replace( '.svg', '', $icon['filename'] );
				?>
				<div class="symbolicon-admin-container">
					<div class="symbolicon-admin-icon">
						<?php echo $this->get_symbolicon( svg: $icon['filename'], max_size: 100 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</div>
					<div class="symbolicon-admin-name"><?php echo esc_html( $name ); ?></div>
				</div>
				<?php
			}
		}

		?>
		</div>
		<?php
	}

	/**
	 * Get the icon as SVG.
	 *
	 * Forked from Yoast SEO
	 *
	 * @access public
	 * @param bool   $base64 (default: true) - Use SVG, true/false.
	 * @param string $icon_color - What color to use.
	 * @return string
	 */
	public function get_icon_svg( $base64 = true, $icon_color = false ) {

		$fill = ( false !== $icon_color ) ? sanitize_hex_color( $icon_color ) : '#82878c';

		$svg = '<svg width="100%" height="100%" version="1.1" viewBox="0 0 16 16" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" style="fill:' . $fill . '"><path d="' . self::LOGO_PATH . '" transform="scale(0.666667)" fill="' . $fill . '"></path></svg>';

		if ( $base64 ) {
			return 'data:image/svg+xml;base64,' . base64_encode( $svg );
		}

		return $svg;
	}

	/**
	 * Symbolicons Output.
	 *
	 * Echos the default output-able symbolicon, based on the SVG and FA icon passed to it.
	 *
	 * data-no-image-dimensions="true" -- this prevents WP Rocket from adding dimensions to the SVG.
	 *
	 * @access public
	 * @param string $svg         (default: 'square.svg') - SVG name.
	 * @param string $icon        (default: 'svg-square') - Symbolicon icon name.
	 * @param string $svg_class   (default: 'symbolicon') - SVG styling class name.
	 * @param string $max_size    (default: '32')         - Maximum size of the icon.
	 *
	 * @return icon
	 */
	public function get_symbolicon( $svg = 'square.svg', $icon = 'svg-square', $svg_class = 'symbolicon', $max_size = '32' ) {

		// Font Awesome fallback.
		$icon_return = '<i class="' . esc_attr( strtolower( $icon ) ) . '" aria-hidden="true"></i>';

			// Extract icon ID from filename (strip `.svg` extension)
		$icon_id = sanitize_title( pathinfo( $svg, PATHINFO_FILENAME ) );

		$sprite_path = LWTV_SYMBOLICONS_SPRITE_PATH . self::SPRITE_FILE;
		$sprite_url  = LWTV_SYMBOLICONS_SPRITE_URL . self::SPRITE_FILE;

		if ( ! file_exists( $sprite_path ) ) {
			return $icon_return;
		}

		// Make sure the icon exists in the sprite SVG.
		if ( ! str_contains( file_get_contents( $sprite_path ), $icon_id ) ) {
			return $icon_return;
		}

		// Output SVG with <use>
		return sprintf(
			'<span class="%1$s" data-no-image-dimensions="true"><svg class="%1$s" role="img" style="height:%2$spx;width:%2$spx;" aria-hidden="true"><use href="%3$s#%4$s"></use></svg></span>',
			esc_attr( $svg_class ),
			intval( $max_size ),
			esc_url( $sprite_url ),
			esc_attr( $icon_id )
		);
	}
}
