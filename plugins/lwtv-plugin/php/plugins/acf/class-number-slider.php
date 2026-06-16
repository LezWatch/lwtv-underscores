<?php
/**
 * ACF Field Type: Number Slider
 *
 * Registers a draggable number slider field for Advanced Custom Fields.
 * Uses the jQuery Simple Slider library by James Smith (http://loopj.com).
 *
 * Original plugin by Q Studio (https://qstudio.us).
 * Adapted for LWTV plugin — ACF 5+ only, text domain changed to 'lwtv'.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound,PEAR.NamingConventions.ValidClassName -- ACF field type classes must match the snake-case field name.
class acf_field_number_slider extends acf_field {

	/**
	 * Set up field type metadata.
	 */
	public function __construct() {
		$this->name     = 'number_slider';
		$this->label    = __( 'Number Slider', 'lwtv' );
		$this->category = 'basic';
		$this->version  = '1.0.0';
		$this->defaults = array(
			'slider_min_value' => 0,
			'slider_max_value' => 100,
			'increment_value'  => 1,
			'slider_units'     => '',
			'slider_append'    => '',
			'default_value'    => 0,
		);

		parent::__construct();
	}

	/**
	 * Render field type settings in the ACF field group editor.
	 *
	 * @param array $field ACF field definition.
	 */
	public function render_field_settings( $field ) {
		acf_render_field_setting(
			$field,
			array(
				'label' => __( 'Default Value', 'lwtv' ),
				'type'  => 'number',
				'name'  => 'default_value',
			)
		);
		acf_render_field_setting(
			$field,
			array(
				'label' => __( 'Minimum Value', 'lwtv' ),
				'type'  => 'number',
				'name'  => 'slider_min_value',
			)
		);
		acf_render_field_setting(
			$field,
			array(
				'label' => __( 'Maximum Value', 'lwtv' ),
				'type'  => 'number',
				'name'  => 'slider_max_value',
			)
		);
		acf_render_field_setting(
			$field,
			array(
				'label' => __( 'Increment Value', 'lwtv' ),
				'type'  => 'number',
				'name'  => 'increment_value',
			)
		);
		acf_render_field_setting(
			$field,
			array(
				'label'        => __( 'Append', 'lwtv' ),
				'instructions' => __( 'Text displayed after the value (e.g. "stars").', 'lwtv' ),
				'type'         => 'text',
				'name'         => 'slider_append',
			)
		);
	}

	/**
	 * Render the slider input on the edit screen.
	 *
	 * @param array $field ACF field definition.
	 */
	public function render_field( $field ) {
		$min  = intval( $field['slider_min_value'] );
		$max  = intval( $field['slider_max_value'] );
		$step = intval( $field['increment_value'] );

		$default = intval( $field['default_value'] ) < $min ? $min : intval( $field['default_value'] );
		$value   = ( isset( $field['value'] ) && intval( $field['value'] ) >= $min )
			? intval( $field['value'] )
			: $default;
		?>
		<div class="lwtv-number-slider">
			<input
				type="range"
				name="<?php echo esc_attr( $field['name'] ); ?>"
				min="<?php echo esc_attr( $min ); ?>"
				max="<?php echo esc_attr( $max ); ?>"
				step="<?php echo esc_attr( $step ); ?>"
				value="<?php echo esc_attr( $value ); ?>"
			/>
			<output class="lwtv-slider-value"><?php echo esc_html( $value ); ?></output>
		</div>
		<?php
	}

	/**
	 * Enqueue slider JS and CSS on ACF admin screens.
	 */
	public function input_admin_enqueue_scripts() {
		$url     = plugins_url( '', __FILE__ );
		$version = $this->version;

		wp_enqueue_script( 'acf-number-slider-input', $url . '/js/input.js', array( 'jquery' ), $version, true );
		wp_enqueue_style( 'acf-number-slider', $url . '/css/simple-slider.css', array(), $version );
	}

	/**
	 * Cast stored value to integer on load.
	 *
	 * @param mixed $value   Current field value.
	 * @param int   $post_id Post ID.
	 * @param array $field   ACF field definition.
	 * @return int
	 */
	public function load_value( $value, $post_id, $field ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		return (int) $value;
	}

	/**
	 * Cast value to integer before saving.
	 *
	 * @param mixed $value   Value to save.
	 * @param int   $post_id Post ID.
	 * @param array $field   ACF field definition.
	 * @return int
	 */
	public function update_value( $value, $post_id, $field ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		return (int) $value;
	}

	/**
	 * Format value for template output.
	 *
	 * Returns an integer when no append text is set so numeric comparisons
	 * in get_field() callers continue to work.
	 *
	 * @param mixed $value   Field value from the database.
	 * @param int   $post_id Post ID.
	 * @param array $field   ACF field definition.
	 * @return int|string
	 */
	public function format_value( $value, $post_id, $field ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$int    = (int) $value;
		$append = ! empty( $field['slider_append'] ) ? (string) $field['slider_append'] : '';
		return $append ? $int . ' ' . $append : $int;
	}
}
// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound,PEAR.NamingConventions.ValidClassName
