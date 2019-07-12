<?php

/**
 * Adds The LWTV Today widget.
 */

class LWTV_Today_Widget extends WP_Widget {

	/**
	 * Holds widget settings defaults, populated in constructor.
	 */
	protected static $defaults;
	protected static $valid_array;
	protected static $apiurl;

	/**
	 * Register widget with WordPress.
	 */
	public function __construct() {

		self::$apiurl = 'https://lezwatchtv.com/wp-json/lwtv/v1';

		if ( WP_DEBUG ) {
			self::$apiurl = home_url() . '/wp-json/lwtv/v1';
		}

		self::$valid_array = array( 'everything', 'character', 'show', 'death' );
		self::$defaults    = array(
			'title' => 'Today ...',
			'type'  => 'everything',
		);
		$widget_ops        = array(
			'classname'   => 'widget-lwtv-today',
			'description' => 'Displays the character, show, or death of the day, or all of the above.',
		);
		$control_ops       = array(
			'id_base' => 'lwtv-today',
		);

		parent::__construct( 'lwtv-today', 'LWTV Today', $widget_ops, $control_ops );
	}

	/**
	 * Front-end display of widget.
	 */
	public function widget( $args, $instance ) {
		$instance = wp_parse_args( (array) $instance, self::$defaults );
		$type     = ( ! empty( $instance['type'] ) ) ? $instance['type'] : 'everything';

		// Get what's needed from $args array ($args populated with options from widget area register_sidebar function)
		$before_widget = isset( $args['before_widget'] ) ? $args['before_widget'] : '';
		$after_widget  = isset( $args['after_widget'] ) ? $args['after_widget'] : '';
		$before_title  = isset( $args['before_title'] ) ? $args['before_title'] : '';
		$after_title   = isset( $args['after_title'] ) ? $args['after_title'] : '';

		// Get what's needed from $instance array ($instance populated with user inputs from widget form)
		$title = isset( $instance['title'] ) && ! empty( trim( $instance['title'] ) ) ? $instance['title'] : 'Today ...';
		$title = apply_filters( 'widget_title', $title, $instance, $this->id_base );

		/** Output widget HTML BEGIN **/
		echo wp_kses_post( $before_widget );

		switch ( $type ) {
			case 'everything':
				$content = self::display_all();
				$title   = 'Today ...';
				$icon    = lwtv_symbolicons( 'calendar-alt.svg', 'calendar-alt' );
				break;
			case 'death':
				$content = self::display_death();
				$title   = 'Death of the Day';
				$icon    = lwtv_symbolicons( 'book-dead.svg', 'fa-skull' );
				break;
			case 'character':
				$content = self::display_show_char( $type );
				$title   = 'Character of the Day';
				$icon    = lwtv_symbolicons( 'contact-card.svg', 'fa-address-card' );
				break;
			case 'show':
				$content = self::display_show_char( $type );
				$title   = 'Show of the Day';
				$icon    = lwtv_symbolicons( 'tv-hd.svg', 'fa-tv' );
				break;
		}

		echo '<div class="card">';
		// phpcs:ignore WordPress.Security.EscapeOutput
		echo '<div class="card-header"><h4>' . esc_html( $title ) . '<span class="float-right">' . $icon . '</span></h4></div>';
		echo wp_kses_post( $content );
		echo '</div>';

		echo wp_kses_post( $after_widget );
	}

	public function get_data( $type = 'character' ) {
		$request = wp_remote_get( self::$apiurl . '/of-the-day/' . $type );

		// Make sure it's running before we do anything...
		if ( wp_remote_retrieve_response_code( $request ) !== 200 ) {
			return;
		}

		$response = wp_remote_retrieve_body( $request );
		$response = json_decode( $response, true );

		return $response;

	}

	/**
	 * Display everything that happened today
	 * @return string $return
	 */
	public function display_all() {
		// Create the date with regards to timezones
		$tz        = 'America/New_York';
		$timestamp = time();
		$dt        = new DateTime( 'now', new DateTimeZone( $tz ) ); //first argument "must" be a string
		$dt->setTimestamp( $timestamp ); //adjust the object to correct timestamp
		$today = $dt->format( 'F d, Y' );

		$character = self::get_data( 'character' );
		$show      = self::get_data( 'show' );

		$char_otd = '<p>The character of the day is <a href="' . $character['url'] . '">' . $character['name'] . '</a>, from <em>' . $character['shows'] . '</em>.</p>';
		$show_otd = '<p>The show of the day is <a href="' . $show['url'] . '"><em>' . $show['name'] . '</em></a>.</p>';
		$dead_otd = self::display_death( 'text' );

		$return = '<div class="card-body"><h4 class="card-title">On ' . $today . '</h4><div class="card-text">' . $char_otd . $show_otd . $dead_otd . '</div></div>';

		return $return;
	}

	public function display_death( $output = 'widget' ) {
		$response = self::get_data( 'death' );
		$count    = ( array_key_exists( 'none', $response ) ) ? 0 : count( $response );
		$how_many = 'no characters died.';
		$the_dead = '';

		if ( $count > 0 ) {
			// translators: %s is the number of dead characters.
			$how_many = sprintf( _n( '%s character died:', '%s characters died:', $count ), $count );
			$the_dead = '<ul class="byq-otd">';

			foreach ( $response as $dead_character ) {
				$the_dead .= '<li><a href="' . $dead_character['url'] . '">' . $dead_character['name'] . '</a> - ' . $dead_character['died'] . '</li>';
			}
			$the_dead .= '</ul>';
		}

		$return = '<p>Today, ' . $how_many . '</p>' . $the_dead;

		if ( 'widget' === $output ) {
			$return = '<div class="card-body">' . $return . '</div>';
		}

		return $return;
	}

	public function display_show_char( $type = 'character' ) {
		$otd_array = get_option( 'lwtv_otd' );
		$the_post  = $otd_array[ $type ]['post'];

		switch ( $type ) {
			case 'character':
				$thumb = 'character-img';
				$text  = lwtv_yikes_chardata( $the_post, 'oneshow' ) . lwtv_yikes_chardata( $the_post, 'oneactor' );
				break;
			case 'show':
				$text = ''; // There's a lot, keep reading...
				// Stations
				$stations = get_the_terms( $the_post, 'lez_stations' );
				if ( $stations ) {
					$text          .= '<div class="card-meta-item"><strong>Airs On:</strong> ';
					$station_string = '';
					foreach ( $stations as $station ) {
						$station_string .= $station->name . ', ';
					}
					$text .= trim( $station_string, ', ' ) . '</div>';
				}
				// Airdates
				$field   = get_post_meta( $the_post, 'lezshows_airdates', true );
				$start   = isset( $field['start'] ) ? $field['start'] : '';
				$finish  = isset( $field['finish'] ) ? $field['finish'] : '';
				$airdate = $start . ' - ' . $finish;
				if ( $start === $finish || '' === $finish ) {
					$airdate = $start;
				}
				$text .= '<div class="card-meta-item"><strong>Airdates:</strong> ' . $airdate . '</div>';

				// Excerpt
				$text .= '<div class="card-excerpt">' . get_the_excerpt( $the_post ) . '</div>';
				break;
		}

		$thumb_attribution = get_post_meta( get_post_thumbnail_id( $the_post ), 'lwtv_attribution', true );
		$thumb_title       = ( empty( $thumb_attribution ) ) ? get_the_title( $the_post ) : get_the_title( $the_post ) . ' &copy; ' . $thumb_attribution;
		$thumbnail         = get_the_post_thumbnail( $the_post, $thumb, array(
			'class' => 'card-img-top',
			'alt'   => $thumb_title,
			'title' => $thumb_title,
		) );

		// Featured Image
		$image  = '<div class="' . $type . '-image-wrapper"><a href="' . get_the_permalink( $the_post ) . '">' . $image . '</a></div>';
		$body   = '<div class="card-body"><h4 class="card-title">' . get_the_title( $the_post ) . '</h4><div class="card-text">' . $text . '</div></div>';
		$button = '<div class="card-footer"><a href="' . get_the_permalink( $the_post ) . '" class="btn btn-outline-primary">' . esc_attr( ucfirst( $type ) ) . ' Profile</a>
		</div>';

		$output = $image . $body . $button;

		return $output;
	}

	/**
	 * Update a particular instance.
	 *
	 * @param array $new_instance New settings for this instance as input by the user via form()
	 * @param array $old_instance Old settings for this instance
	 * @return array Settings to save or bool false to cancel saving
	 */
	public function update( $new_instance, $old_instance ) {
		$new_instance['title'] = wp_strip_all_tags( $new_instance['title'] );

		if ( ! in_array( $new_instance['type'], self::$valid_array, true ) ) {
			$new_instance['type'] = 'everything';
		}

		return $new_instance;
	}

	/**
	 * Back-end widget form.
	 */
	public function form( $instance ) {
		$instance = wp_parse_args( (array) $instance, self::$defaults );
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php echo 'Title'; ?>: </label>
			<input type="text" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" value="<?php echo esc_attr( $instance['title'] ); ?>" class="widefat" />
		</p>

		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'type' ) ); ?>"><?php echo 'Type'; ?>: </label>
			<select id="<?php echo esc_attr( $this->get_field_id( 'type' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'type' ) ); ?>" class="widefat">
				<?php
				foreach ( self::$valid_array as $type ) {
					?>
					<option <?php selected( $instance['type'], $type ); ?> value="<?php echo esc_attr( $type ); ?>"><?php echo esc_html( ucfirst( $type ) ); ?></option>
					<?php
				}
				?>
			</select>
		</p>
		<?php
	}

}

// Register widget and remove the one from the plugin for reasons
function register_lwtv_today_widget() {
	register_widget( 'LWTV_Today_Widget' );
}
add_action( 'widgets_init', 'register_lwtv_today_widget' );
