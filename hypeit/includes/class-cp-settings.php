<?php
/**
 * Custom Theme settings: override the inherited theme styling for the
 * client-facing campaign page. Every field is optional — leave blank to
 * inherit the active theme.
 *
 * @package HypeIt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CP_Settings {

	const OPTION = 'cp_theme';

	/**
	 * Hook registration.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
	}

	/**
	 * Field definitions.
	 *
	 * @return array
	 */
	public static function fields() {
		return array(
			'google_font_name' => array( 'label' => __( 'Google Font name', 'hypeit' ), 'type' => 'text' ),
			'google_font_url'  => array( 'label' => __( 'Google Font URL', 'hypeit' ), 'type' => 'url' ),
			'font_family'      => array( 'label' => __( 'Custom font family', 'hypeit' ), 'type' => 'text' ),
			'header_offset'    => array( 'label' => __( 'Top offset to clear a fixed header (e.g. 0px, 160px)', 'hypeit' ), 'type' => 'text' ),
			'side_offset'      => array( 'label' => __( 'Side inset to clear a fixed side rail (e.g. 24px, 80px)', 'hypeit' ), 'type' => 'text' ),
			'color_primary'    => array( 'label' => __( 'Primary color', 'hypeit' ), 'type' => 'color' ),
			'color_secondary'  => array( 'label' => __( 'Secondary color', 'hypeit' ), 'type' => 'color' ),
			'color_accent'     => array( 'label' => __( 'Accent color', 'hypeit' ), 'type' => 'color' ),
			'color_bg'         => array( 'label' => __( 'Background color', 'hypeit' ), 'type' => 'color' ),
			'color_text'       => array( 'label' => __( 'Text color', 'hypeit' ), 'type' => 'color' ),
			'border_radius'    => array( 'label' => __( 'Border radius (e.g. 8px)', 'hypeit' ), 'type' => 'text' ),
			'button_bg'        => array( 'label' => __( 'Button background', 'hypeit' ), 'type' => 'color' ),
			'button_text'      => array( 'label' => __( 'Button text color', 'hypeit' ), 'type' => 'color' ),
			'button_radius'    => array( 'label' => __( 'Button radius (e.g. 999px)', 'hypeit' ), 'type' => 'text' ),
		);
	}

	/**
	 * Get saved settings merged with empty defaults.
	 *
	 * @return array
	 */
	public static function get() {
		$saved    = get_option( self::OPTION, array() );
		$defaults = array();
		foreach ( array_keys( self::fields() ) as $key ) {
			$defaults[ $key ] = '';
		}
		return wp_parse_args( is_array( $saved ) ? $saved : array(), $defaults );
	}

	/**
	 * Add the submenu page.
	 */
	public static function menu() {
		add_submenu_page(
			'edit.php?post_type=' . CP_POST_TYPE,
			__( 'Custom Theme', 'hypeit' ),
			__( 'Custom Theme', 'hypeit' ),
			'manage_options',
			'cp-theme',
			array( __CLASS__, 'render' )
		);
	}

	/**
	 * Register the setting.
	 */
	public static function register() {
		register_setting(
			'cp_theme_group',
			self::OPTION,
			array( 'sanitize_callback' => array( __CLASS__, 'sanitize' ) )
		);
	}

	/**
	 * Sanitize settings.
	 *
	 * @param array $input Raw input.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$clean  = array();
		$fields = self::fields();

		foreach ( $fields as $key => $field ) {
			$value = isset( $input[ $key ] ) ? trim( (string) $input[ $key ] ) : '';

			if ( '' === $value ) {
				$clean[ $key ] = '';
				continue;
			}

			switch ( $field['type'] ) {
				case 'url':
					$clean[ $key ] = esc_url_raw( $value );
					break;
				case 'color':
					// Allow hex, rgb(a) and named colors; store sanitized text.
					$clean[ $key ] = sanitize_text_field( $value );
					break;
				default:
					$clean[ $key ] = sanitize_text_field( $value );
			}
		}

		return $clean;
	}

	/**
	 * Render the settings page.
	 */
	public static function render() {
		$values = self::get();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Campaign — Custom Theme', 'hypeit' ); ?></h1>
			<p class="description"><?php esc_html_e( 'These options override the inherited theme styling on the client-facing campaign page. Leave any field blank to inherit the active theme.', 'hypeit' ); ?></p>
			<form method="post" action="options.php">
				<?php settings_fields( 'cp_theme_group' ); ?>
				<table class="form-table" role="presentation">
					<tbody>
					<?php foreach ( self::fields() as $key => $field ) : ?>
						<tr>
							<th scope="row"><label for="cp_<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $field['label'] ); ?></label></th>
							<td>
								<input
									type="text"
									id="cp_<?php echo esc_attr( $key ); ?>"
									name="<?php echo esc_attr( self::OPTION ); ?>[<?php echo esc_attr( $key ); ?>]"
									value="<?php echo esc_attr( $values[ $key ] ); ?>"
									class="regular-text"
									placeholder="<?php esc_attr_e( 'Inherit theme', 'hypeit' ); ?>"
								/>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
