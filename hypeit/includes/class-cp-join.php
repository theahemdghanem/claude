<?php
/**
 * "Join" section shortcode: heading + message + blogger count + two buttons
 * (join as a blogger / contact us). Inherits the active theme's styling.
 *
 *   [hypeit_join]                   full section (count + text + both buttons)
 *   [hypeit_join show="count"]      just the number, inline — e.g. "+312 Bloggers"
 *   Optional: heading="…" text="…" layout="center|left|split" count="yes|no"
 *             join_text="…" join_url="…" contact_text="…" contact_url="…"
 *             heading_tag="h2" label="…" plus="yes|no"
 *   ([campaign_blogger_count] still works as an alias of show="count".)
 *
 * Count methods:
 *   actual — live number of active (non-blocked, published) bloggers.
 *   start  — your starting number + every blogger added since you set it.
 *
 * @package HypeIt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CP_Join {

	const OPT       = 'cp_join';
	const CACHE_KEY = 'cp_join_actual';

	/** @var bool Inline CSS printed once per page. */
	private static $css_done = false;

	/**
	 * Hooks.
	 */
	public static function init() {
		add_shortcode( 'hypeit_join', array( __CLASS__, 'shortcode' ) );
		add_shortcode( 'campaign_join', array( __CLASS__, 'shortcode' ) ); // Pre-2.0 name — keeps working.
		add_shortcode( 'campaign_blogger_count', array( __CLASS__, 'shortcode_count' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'rest_routes' ) );

		// Keep the live count fresh.
		add_action( 'save_post_' . CP_Library::CPT, array( __CLASS__, 'bust' ) );
		add_action( 'deleted_post', array( __CLASS__, 'bust' ) );
		add_action( 'trashed_post', array( __CLASS__, 'bust' ) );
		add_action( 'untrashed_post', array( __CLASS__, 'bust' ) );
		add_action( 'updated_post_meta', array( __CLASS__, 'bust_on_block' ), 10, 3 );
		add_action( 'added_post_meta', array( __CLASS__, 'bust_on_block' ), 10, 3 );

		if ( is_admin() ) {
			add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
			add_action( 'admin_post_cp_join_save', array( __CLASS__, 'save' ) );
		}
	}

	/* ------------------------------------------------------------------ */
	/* Settings                                                            */
	/* ------------------------------------------------------------------ */

	/**
	 * Defaults.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'heading'        => __( 'Join Our Blogger Network', 'hypeit' ),
			'text'           => __( 'We work with some of the region’s top bloggers and have successfully delivered campaigns across different industries. Interested in joining our blogger network? Fill in your details below. If you’re looking to work with our bloggers, get in touch with us.', 'hypeit' ),
			'heading_tag'    => 'h2',
			'layout'         => 'center',
			'join_text'      => __( 'Join as a Blogger', 'hypeit' ),
			'join_url'       => '',
			'contact_text'   => __( 'Work With Our Bloggers', 'hypeit' ),
			'contact_page'   => 0,
			'contact_url'    => '',
			'primary'        => 'join',
			'btn_class'      => '',
			'show_count'     => 1,
			'method'         => 'actual',
			'start'          => 100,
			'baseline'       => 0,
			'plus'           => 1,
			'label'          => __( 'Bloggers', 'hypeit' ),
		);
	}

	/**
	 * Current settings.
	 *
	 * @return array
	 */
	public static function get() {
		$o = get_option( self::OPT, array() );
		return array_merge( self::defaults(), is_array( $o ) ? $o : array() );
	}

	/* ------------------------------------------------------------------ */
	/* Counting                                                            */
	/* ------------------------------------------------------------------ */

	/**
	 * Real number of active bloggers (cached; busted on changes).
	 *
	 * @param bool $fresh Skip cache.
	 * @return int
	 */
	public static function actual( $fresh = false ) {
		if ( ! $fresh ) {
			$c = get_transient( self::CACHE_KEY );
			if ( false !== $c ) {
				return (int) $c;
			}
		}
		$q = new WP_Query(
			array(
				'post_type'              => CP_Library::CPT,
				'post_status'            => 'publish',
				'posts_per_page'         => 1,
				'fields'                 => 'ids',
				'no_found_rows'          => false,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'meta_query'             => CP_Library::active_clause(), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			)
		);
		$n = (int) $q->found_posts;
		set_transient( self::CACHE_KEY, $n, 10 * MINUTE_IN_SECONDS );
		return $n;
	}

	/**
	 * The number visitors see.
	 *
	 * @return int
	 */
	public static function display_count() {
		$s      = self::get();
		$actual = self::actual();
		if ( 'start' !== $s['method'] ) {
			return $actual;
		}
		// Starting number + every blogger added since it was set (never below the start).
		return (int) $s['start'] + max( 0, $actual - (int) $s['baseline'] );
	}

	/**
	 * Clear the cached count.
	 */
	public static function bust() {
		delete_transient( self::CACHE_KEY );
	}

	/**
	 * Clear the cache when a blogger is blocked/unblocked.
	 *
	 * @param int    $meta_id Meta ID.
	 * @param int    $post_id Post ID.
	 * @param string $key     Meta key.
	 */
	public static function bust_on_block( $meta_id, $post_id, $key ) {
		if ( '_cp_blocked' === $key || CP_Library::META_INACTIVE === $key ) {
			self::bust();
		}
	}

	/**
	 * Public count endpoint (lets cached pages show the live number).
	 */
	public static function rest_routes() {
		register_rest_route(
			CP_REST::NS,
			'/count',
			array(
				'methods'             => 'GET',
				'permission_callback' => '__return_true',
				'callback'            => function () {
					$res = new WP_REST_Response( array( 'count' => self::display_count() ) );
					$res->header( 'Cache-Control', 'public, max-age=60' );
					return $res;
				},
			)
		);
	}

	/* ------------------------------------------------------------------ */
	/* Links                                                               */
	/* ------------------------------------------------------------------ */

	/**
	 * Blogger registration URL.
	 *
	 * @param array $s Settings.
	 * @return string
	 */
	private static function join_url( $s ) {
		if ( '' !== trim( (string) $s['join_url'] ) ) {
			return $s['join_url'];
		}
		return class_exists( 'CP_Onboarding' ) ? CP_Onboarding::url() : home_url( '/' );
	}

	/**
	 * Contact URL.
	 *
	 * @param array $s Settings.
	 * @return string
	 */
	private static function contact_url( $s ) {
		if ( '' !== trim( (string) $s['contact_url'] ) ) {
			return $s['contact_url'];
		}
		if ( ! empty( $s['contact_page'] ) && get_post_status( (int) $s['contact_page'] ) ) {
			return get_permalink( (int) $s['contact_page'] );
		}
		return '';
	}

	/* ------------------------------------------------------------------ */
	/* Front end                                                           */
	/* ------------------------------------------------------------------ */

	/**
	 * The count markup (+312 Bloggers).
	 *
	 * @param array $s Settings.
	 * @param string $tag Wrapper tag.
	 * @return string
	 */
	private static function count_html( $s, $tag = 'p' ) {
		$n = self::display_count();
		self::assets();
		return '<' . $tag . ' class="cp-join__stat" data-cp-count="' . (int) $n . '" data-cp-endpoint="' . esc_url( rest_url( CP_REST::NS . '/count' ) ) . '">'
			. '<span class="cp-join__num">' . ( $s['plus'] ? '<span class="cp-join__plus" aria-hidden="true">+</span>' : '' ) . '<span class="cp-join__val">' . esc_html( number_format_i18n( $n ) ) . '</span></span>'
			. ( '' !== trim( (string) $s['label'] ) ? ' <span class="cp-join__label">' . esc_html( $s['label'] ) . '</span>' : '' )
			. '</' . $tag . '>';
	}

	/**
	 * [campaign_blogger_count]
	 *
	 * @param array $atts Attributes.
	 * @return string
	 */
	public static function shortcode_count( $atts ) {
		$s    = self::get();
		$atts = shortcode_atts( array( 'label' => $s['label'], 'plus' => $s['plus'] ? 'yes' : 'no' ), $atts, 'campaign_blogger_count' );
		$s['label'] = $atts['label'];
		$s['plus']  = 'no' !== $atts['plus'];
		return '<span class="cp-join cp-join--inline">' . self::count_html( $s, 'span' ) . '</span>';
	}

	/**
	 * [hypeit_join] (also [campaign_join])
	 *
	 * @param array $atts Attributes.
	 * @return string
	 */
	public static function shortcode( $atts ) {
		$atts = (array) $atts;
		if ( isset( $atts['show'] ) && 'count' === strtolower( trim( (string) $atts['show'] ) ) ) {
			unset( $atts['show'] );
			return self::shortcode_count( $atts );
		}
		$s    = self::get();
		$atts = shortcode_atts(
			array(
				'heading'      => $s['heading'],
				'text'         => $s['text'],
				'heading_tag'  => $s['heading_tag'],
				'layout'       => $s['layout'],
				'count'        => $s['show_count'] ? 'yes' : 'no',
				'join_text'    => $s['join_text'],
				'join_url'     => '',
				'contact_text' => $s['contact_text'],
				'contact_url'  => '',
				'class'        => '',
				'show'         => 'all',
			),
			$atts,
			'campaign_join'
		);

		$tag    = in_array( $atts['heading_tag'], array( 'h1', 'h2', 'h3', 'h4', 'p' ), true ) ? $atts['heading_tag'] : 'h2';
		$layout = in_array( $atts['layout'], array( 'center', 'left', 'split' ), true ) ? $atts['layout'] : 'center';

		$join_href    = '' !== $atts['join_url'] ? $atts['join_url'] : self::join_url( $s );
		$contact_href = '' !== $atts['contact_url'] ? $atts['contact_url'] : self::contact_url( $s );

		$extra = trim( preg_replace( '/[^A-Za-z0-9_\- ]/', '', (string) $s['btn_class'] ) );
		$base  = 'cp-join__btn wp-element-button wp-block-button__link button' . ( $extra ? ' ' . $extra : '' );

		$buttons = array(
			'join'    => ( '' !== trim( $atts['join_text'] ) && $join_href ) ? array( $join_href, $atts['join_text'] ) : null,
			'contact' => ( '' !== trim( $atts['contact_text'] ) && $contact_href ) ? array( $contact_href, $atts['contact_text'] ) : null,
		);
		$order = 'contact' === $s['primary'] ? array( 'contact', 'join' ) : array( 'join', 'contact' );

		$btn_html = '';
		foreach ( $order as $i => $key ) {
			if ( ! $buttons[ $key ] ) {
				continue;
			}
			$is_primary = ( $key === $s['primary'] );
			$btn_html  .= '<div class="wp-block-button' . ( $is_primary ? '' : ' is-style-outline' ) . '">'
				. '<a class="' . esc_attr( $base . ' cp-join__btn--' . ( $is_primary ? 'primary' : 'secondary' ) ) . '" href="' . esc_url( $buttons[ $key ][0] ) . '">'
				. esc_html( $buttons[ $key ][1] ) . '</a></div>';
		}

		self::assets();

		$classes = 'cp-join cp-join--' . $layout . ( $atts['class'] ? ' ' . sanitize_html_class( $atts['class'] ) : '' );
		$html    = '<section class="' . esc_attr( $classes ) . '">';
		$html   .= '<div class="cp-join__inner">';
		$html   .= '<div class="cp-join__content">';
		if ( 'split' !== $layout && 'no' !== $atts['count'] ) {
			$html .= self::count_html( $s );
		}
		if ( '' !== trim( $atts['heading'] ) ) {
			$html .= '<' . $tag . ' class="cp-join__heading">' . esc_html( $atts['heading'] ) . '</' . $tag . '>';
		}
		if ( '' !== trim( $atts['text'] ) ) {
			$html .= '<div class="cp-join__text">' . wpautop( wp_kses_post( $atts['text'] ) ) . '</div>';
		}
		if ( $btn_html ) {
			$html .= '<div class="cp-join__actions wp-block-buttons">' . $btn_html . '</div>';
		}
		$html .= '</div>';
		if ( 'split' === $layout && 'no' !== $atts['count'] ) {
			$html .= '<div class="cp-join__aside">' . self::count_html( $s ) . '</div>';
		}
		$html .= '</div></section>';

		return $html;
	}

	/**
	 * Print the (tiny, theme-yielding) CSS once and enqueue the counter script.
	 */
	private static function assets() {
		wp_enqueue_script( 'cp-join', CP_URL . 'assets/js/join.js', array(), CP_VERSION, true );
		if ( self::$css_done ) {
			return;
		}
		self::$css_done = true;
		wp_enqueue_style( 'cp-join', CP_URL . 'assets/css/join.css', array(), CP_VERSION );
		// If styles were already printed in <head>, print ours inline right here.
		if ( did_action( 'wp_print_styles' ) || did_action( 'wp_head' ) ) {
			wp_print_styles( 'cp-join' );
		}
	}

	/* ------------------------------------------------------------------ */
	/* Admin                                                               */
	/* ------------------------------------------------------------------ */

	/**
	 * Submenu.
	 */
	public static function menu() {
		add_submenu_page(
			'edit.php?post_type=' . CP_POST_TYPE,
			__( 'Join Section', 'hypeit' ),
			__( 'Join Section', 'hypeit' ),
			'manage_options',
			'cp-join',
			array( __CLASS__, 'render' )
		);
	}

	/**
	 * Save settings.
	 */
	public static function save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'hypeit' ) );
		}
		check_admin_referer( 'cp_join_save', 'cp_join_nonce' );
		$old = self::get();
		$p   = wp_unslash( $_POST );

		$new = array(
			'heading'      => sanitize_text_field( $p['heading'] ?? '' ),
			'text'         => wp_kses_post( $p['text'] ?? '' ),
			'heading_tag'  => in_array( $p['heading_tag'] ?? '', array( 'h1', 'h2', 'h3', 'h4', 'p' ), true ) ? $p['heading_tag'] : 'h2',
			'layout'       => in_array( $p['layout'] ?? '', array( 'center', 'left', 'split' ), true ) ? $p['layout'] : 'center',
			'join_text'    => sanitize_text_field( $p['join_text'] ?? '' ),
			'join_url'     => esc_url_raw( trim( $p['join_url'] ?? '' ) ),
			'contact_text' => sanitize_text_field( $p['contact_text'] ?? '' ),
			'contact_page' => absint( $p['contact_page'] ?? 0 ),
			'contact_url'  => esc_url_raw( trim( $p['contact_url'] ?? '' ) ),
			'primary'      => 'contact' === ( $p['primary'] ?? '' ) ? 'contact' : 'join',
			'btn_class'    => trim( preg_replace( '/[^A-Za-z0-9_\- ]/', '', $p['btn_class'] ?? '' ) ),
			'show_count'   => empty( $p['show_count'] ) ? 0 : 1,
			'method'       => 'start' === ( $p['method'] ?? '' ) ? 'start' : 'actual',
			'start'        => absint( $p['start'] ?? 0 ),
			'plus'         => empty( $p['plus'] ) ? 0 : 1,
			'label'        => sanitize_text_field( $p['label'] ?? '' ),
			'baseline'     => (int) $old['baseline'],
		);

		// Setting (or changing) the starting number restarts counting from it:
		// remember how many bloggers exist right now.
		if ( 'start' === $new['method'] && ( 'start' !== $old['method'] || $new['start'] !== (int) $old['start'] || ! empty( $p['restart'] ) ) ) {
			$new['baseline'] = self::actual( true );
		}

		update_option( self::OPT, $new, false );
		wp_safe_redirect( add_query_arg( array( 'page' => 'cp-join', 'post_type' => CP_POST_TYPE, 'saved' => 1 ), admin_url( 'edit.php' ) ) );
		exit;
	}

	/**
	 * Settings page.
	 */
	public static function render() {
		$s      = self::get();
		$actual = self::actual( true );
		$shown  = self::display_count();
		$since  = max( 0, $actual - (int) $s['baseline'] );
		?>
		<div class="wrap cp-admin">
			<h1><?php esc_html_e( 'Join Section', 'hypeit' ); ?></h1>
			<p class="cp-sub"><?php esc_html_e( 'A ready-made section inviting bloggers to join and brands to get in touch. It picks up your theme’s fonts, colours and buttons automatically.', 'hypeit' ); ?></p>

			<?php if ( isset( $_GET['saved'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Saved.', 'hypeit' ); ?></p></div>
			<?php endif; ?>

			<div class="cp-card" style="display:flex;flex-wrap:wrap;gap:24px;align-items:center;justify-content:space-between;">
				<div>
					<h2 class="title" style="margin:0 0 8px;"><?php esc_html_e( 'Shortcode', 'hypeit' ); ?></h2>
					<?php
					$cp_sc = array(
						'[hypeit_join]'              => __( 'Full section — count, text and both buttons', 'hypeit' ),
						'[hypeit_join show="count"]' => __( 'Just the number, inside any text — e.g. “+312 Bloggers”', 'hypeit' ),
					);
					foreach ( $cp_sc as $code => $desc ) :
						?>
						<p style="margin:0 0 10px;display:flex;flex-wrap:wrap;align-items:center;gap:8px;">
							<code style="font-size:14px;padding:6px 10px;"><?php echo esc_html( $code ); ?></code>
							<button type="button" class="button" data-sc="<?php echo esc_attr( $code ); ?>" onclick="var b=this;navigator.clipboard&&navigator.clipboard.writeText(b.getAttribute('data-sc'));b.textContent='<?php echo esc_js( __( 'Copied!', 'hypeit' ) ); ?>';setTimeout(function(){b.textContent='<?php echo esc_js( __( 'Copy', 'hypeit' ) ); ?>';},1500);"><?php esc_html_e( 'Copy', 'hypeit' ); ?></button>
							<span class="description"><?php echo esc_html( $desc ); ?></span>
						</p>
					<?php endforeach; ?>
					<p class="description" style="margin-top:4px;"><?php esc_html_e( 'Works in any page, post, widget, Elementor shortcode widget or theme template.', 'hypeit' ); ?></p>
				</div>
				<div style="text-align:right;">
					<div class="description"><?php esc_html_e( 'Visitors see now', 'hypeit' ); ?></div>
					<div style="font-size:32px;font-weight:700;line-height:1.1;letter-spacing:-.02em;"><?php echo esc_html( ( $s['plus'] ? '+' : '' ) . number_format_i18n( $shown ) . ' ' . $s['label'] ); ?></div>
					<div class="description">
						<?php
						printf(
							/* translators: %s: number of bloggers. */
							esc_html__( '%s active bloggers in your list', 'hypeit' ),
							esc_html( number_format_i18n( $actual ) )
						);
						?>
					</div>
				</div>
			</div>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="cp_join_save" />
				<?php wp_nonce_field( 'cp_join_save', 'cp_join_nonce' ); ?>

				<div class="cp-card">
					<h2 class="title"><?php esc_html_e( 'Text', 'hypeit' ); ?></h2>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="cpj-heading"><?php esc_html_e( 'Heading', 'hypeit' ); ?></label></th>
							<td><input type="text" id="cpj-heading" name="heading" class="large-text" value="<?php echo esc_attr( $s['heading'] ); ?>" /></td>
						</tr>
						<tr>
							<th scope="row"><label for="cpj-text"><?php esc_html_e( 'Message', 'hypeit' ); ?></label></th>
							<td>
								<textarea id="cpj-text" name="text" rows="5" class="large-text"><?php echo esc_textarea( $s['text'] ); ?></textarea>
								<p class="description"><?php esc_html_e( 'A short welcome message. Leave a blank line to start a new paragraph.', 'hypeit' ); ?></p>
							</td>
						</tr>
					</table>
				</div>

				<div class="cp-card">
					<h2 class="title"><?php esc_html_e( 'Buttons', 'hypeit' ); ?></h2>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="cpj-join-text"><?php esc_html_e( 'Blogger registration button', 'hypeit' ); ?></label></th>
							<td>
								<input type="text" id="cpj-join-text" name="join_text" class="regular-text" value="<?php echo esc_attr( $s['join_text'] ); ?>" />
								<p style="margin:8px 0 0;"><input type="url" name="join_url" class="regular-text" value="<?php echo esc_attr( $s['join_url'] ); ?>" placeholder="<?php echo esc_attr( class_exists( 'CP_Onboarding' ) ? CP_Onboarding::url() : '' ); ?>" /></p>
								<p class="description"><?php esc_html_e( 'Link — leave empty to use your blogger onboarding form.', 'hypeit' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="cpj-contact-text"><?php esc_html_e( 'Contact button', 'hypeit' ); ?></label></th>
							<td>
								<input type="text" id="cpj-contact-text" name="contact_text" class="regular-text" value="<?php echo esc_attr( $s['contact_text'] ); ?>" />
								<p style="margin:8px 0 0;">
									<?php
									wp_dropdown_pages(
										array(
											'name'              => 'contact_page',
											'selected'          => (int) $s['contact_page'],
											'show_option_none'  => __( '— Choose your contact page —', 'hypeit' ),
											'option_none_value' => 0,
										)
									);
									?>
								</p>
								<p style="margin:8px 0 0;"><input type="url" name="contact_url" class="regular-text" value="<?php echo esc_attr( $s['contact_url'] ); ?>" placeholder="<?php esc_attr_e( 'or a custom link (overrides the page)', 'hypeit' ); ?>" /></p>
								<p class="description"><?php esc_html_e( 'The button is hidden until a page or link is set.', 'hypeit' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Main button', 'hypeit' ); ?></th>
							<td>
								<label style="margin-right:16px;"><input type="radio" name="primary" value="join" <?php checked( $s['primary'], 'join' ); ?> /> <?php esc_html_e( 'Blogger registration', 'hypeit' ); ?></label>
								<label><input type="radio" name="primary" value="contact" <?php checked( $s['primary'], 'contact' ); ?> /> <?php esc_html_e( 'Contact', 'hypeit' ); ?></label>
								<p class="description"><?php esc_html_e( 'Shown first and filled; the other button uses the outline style.', 'hypeit' ); ?></p>
							</td>
						</tr>
					</table>
				</div>

				<div class="cp-card">
					<h2 class="title"><?php esc_html_e( 'Blogger count', 'hypeit' ); ?></h2>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php esc_html_e( 'Show the count', 'hypeit' ); ?></th>
							<td><label><input type="checkbox" name="show_count" value="1" <?php checked( $s['show_count'] ); ?> /> <?php esc_html_e( 'Display the number of bloggers', 'hypeit' ); ?></label></td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Counting method', 'hypeit' ); ?></th>
							<td>
								<label style="display:block;margin-bottom:10px;">
									<input type="radio" name="method" value="actual" <?php checked( $s['method'], 'actual' ); ?> />
									<strong><?php esc_html_e( 'Accurate count', 'hypeit' ); ?></strong> — <?php esc_html_e( 'the real number of bloggers in your list, updated automatically.', 'hypeit' ); ?>
								</label>
								<label style="display:block;">
									<input type="radio" name="method" value="start" <?php checked( $s['method'], 'start' ); ?> />
									<strong><?php esc_html_e( 'Starting number', 'hypeit' ); ?></strong> — <?php esc_html_e( 'start from a number you choose; every new blogger adds one.', 'hypeit' ); ?>
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="cpj-start"><?php esc_html_e( 'Starting number', 'hypeit' ); ?></label></th>
							<td>
								<input type="number" id="cpj-start" name="start" min="0" step="1" class="small-text" style="width:120px;" value="<?php echo esc_attr( (int) $s['start'] ); ?>" />
								<?php if ( 'start' === $s['method'] ) : ?>
									<p class="description">
										<?php
										printf(
											/* translators: 1: start number, 2: new bloggers since, 3: total shown. */
											esc_html__( 'Started at %1$s · %2$s new since then · showing %3$s.', 'hypeit' ),
											esc_html( number_format_i18n( (int) $s['start'] ) ),
											esc_html( number_format_i18n( $since ) ),
											esc_html( number_format_i18n( $shown ) )
										);
										?>
									</p>
									<label style="display:block;margin-top:6px;"><input type="checkbox" name="restart" value="1" /> <?php esc_html_e( 'Restart counting from this number now', 'hypeit' ); ?></label>
								<?php endif; ?>
								<p class="description"><?php esc_html_e( 'Changing the number restarts the count from the new number.', 'hypeit' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="cpj-label"><?php esc_html_e( 'Label', 'hypeit' ); ?></label></th>
							<td>
								<input type="text" id="cpj-label" name="label" class="regular-text" value="<?php echo esc_attr( $s['label'] ); ?>" />
								<label style="margin-left:12px;"><input type="checkbox" name="plus" value="1" <?php checked( $s['plus'] ); ?> /> <?php esc_html_e( 'Show “+” before the number', 'hypeit' ); ?></label>
							</td>
						</tr>
					</table>
				</div>

				<div class="cp-card">
					<h2 class="title"><?php esc_html_e( 'Layout', 'hypeit' ); ?></h2>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php esc_html_e( 'Alignment', 'hypeit' ); ?></th>
							<td>
								<label style="margin-right:16px;"><input type="radio" name="layout" value="center" <?php checked( $s['layout'], 'center' ); ?> /> <?php esc_html_e( 'Centered', 'hypeit' ); ?></label>
								<label style="margin-right:16px;"><input type="radio" name="layout" value="left" <?php checked( $s['layout'], 'left' ); ?> /> <?php esc_html_e( 'Left aligned', 'hypeit' ); ?></label>
								<label><input type="radio" name="layout" value="split" <?php checked( $s['layout'], 'split' ); ?> /> <?php esc_html_e( 'Split — text left, count right (stacks on mobile)', 'hypeit' ); ?></label>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="cpj-htag"><?php esc_html_e( 'Heading level', 'hypeit' ); ?></label></th>
							<td>
								<select id="cpj-htag" name="heading_tag">
									<?php foreach ( array( 'h1', 'h2', 'h3', 'h4', 'p' ) as $h ) : ?>
										<option value="<?php echo esc_attr( $h ); ?>" <?php selected( $s['heading_tag'], $h ); ?>><?php echo esc_html( strtoupper( $h ) ); ?></option>
									<?php endforeach; ?>
								</select>
								<p class="description"><?php esc_html_e( 'H2 suits most pages. The heading uses your theme’s own heading style.', 'hypeit' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="cpj-btnclass"><?php esc_html_e( 'Theme button class', 'hypeit' ); ?> <small>(<?php esc_html_e( 'optional', 'hypeit' ); ?>)</small></label></th>
							<td>
								<input type="text" id="cpj-btnclass" name="btn_class" class="regular-text" value="<?php echo esc_attr( $s['btn_class'] ); ?>" placeholder="btn btn-primary" />
								<p class="description"><?php esc_html_e( 'Only needed if your theme’s buttons use a custom class. Buttons already use the standard WordPress button classes.', 'hypeit' ); ?></p>
							</td>
						</tr>
					</table>
				</div>

				<?php submit_button( __( 'Save', 'hypeit' ) ); ?>
			</form>
		</div>
		<?php
	}
}
