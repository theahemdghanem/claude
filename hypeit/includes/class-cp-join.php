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
		$sw     = static function ( $name, $on, $label, $help = '' ) {
			?>
			<label class="cpw-switchrow">
				<span class="cpw-switch"><input type="checkbox" name="<?php echo esc_attr( $name ); ?>" value="1" <?php checked( $on ); ?> /><span class="cpw-slider" aria-hidden="true"></span></span>
				<span class="cpw-switchtext"><strong><?php echo esc_html( $label ); ?></strong><?php if ( $help ) : ?><small><?php echo esc_html( $help ); ?></small><?php endif; ?></span>
			</label>
			<?php
		};
		$layouts = array(
			'center' => array( __( 'Centered', 'hypeit' ), __( 'Everything in the middle', 'hypeit' ) ),
			'left'   => array( __( 'Left aligned', 'hypeit' ), __( 'Reads like an article', 'hypeit' ) ),
			'split'  => array( __( 'Split', 'hypeit' ), __( 'Text left, count right — stacks on mobile', 'hypeit' ) ),
		);
		$levels = array(
			'h1' => 'H1',
			'h2' => 'H2',
			'h3' => 'H3',
			'h4' => 'H4',
			'p'  => __( 'Paragraph', 'hypeit' ),
		);
		?>
		<div class="wrap cp-admin cp-wide cps cpj">
			<div class="cp-pagehead">
				<div>
					<h1><?php esc_html_e( 'Join Section', 'hypeit' ); ?></h1>
					<p class="cp-sub"><?php esc_html_e( 'A ready-made section inviting bloggers to join and brands to get in touch. It picks up your theme’s fonts, colours and buttons automatically.', 'hypeit' ); ?></p>
				</div>
				<div class="cpj-live">
					<span><?php esc_html_e( 'Visitors see now', 'hypeit' ); ?></span>
					<b><?php echo esc_html( ( $s['plus'] ? '+' : '' ) . number_format_i18n( $shown ) . ' ' . $s['label'] ); ?></b>
					<small><?php echo esc_html( sprintf( /* translators: %s: number of bloggers. */ __( '%s active bloggers in your list', 'hypeit' ), number_format_i18n( $actual ) ) ); ?></small>
				</div>
			</div>

			<?php if ( isset( $_GET['saved'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Saved.', 'hypeit' ); ?></p></div>
			<?php endif; ?>

			<div class="cps-layout">
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cps-main" id="cpj-form">
					<input type="hidden" name="action" value="cp_join_save" />
					<?php wp_nonce_field( 'cp_join_save', 'cp_join_nonce' ); ?>

					<section class="cpw-card">
						<h3><?php esc_html_e( 'Text', 'hypeit' ); ?></h3>
						<div class="cps-field">
							<label for="cpj-heading"><?php esc_html_e( 'Heading', 'hypeit' ); ?></label>
							<input type="text" id="cpj-heading" name="heading" value="<?php echo esc_attr( $s['heading'] ); ?>" />
						</div>
						<div class="cps-field">
							<label for="cpj-text"><?php esc_html_e( 'Message', 'hypeit' ); ?></label>
							<textarea id="cpj-text" name="text" rows="4"><?php echo esc_textarea( $s['text'] ); ?></textarea>
							<p class="cpw-muted"><?php esc_html_e( 'A short welcome message. Leave a blank line to start a new paragraph.', 'hypeit' ); ?></p>
						</div>
					</section>

					<section class="cpw-card">
						<h3><?php esc_html_e( 'Layout', 'hypeit' ); ?></h3>
						<div class="cps-field">
							<span class="cps-label"><?php esc_html_e( 'Alignment', 'hypeit' ); ?></span>
							<div class="cpj-layouts">
								<?php foreach ( $layouts as $lk => $ll ) : ?>
									<label class="cpj-layout">
										<input type="radio" name="layout" value="<?php echo esc_attr( $lk ); ?>" <?php checked( $s['layout'], $lk ); ?> />
										<span class="cpj-thumb is-<?php echo esc_attr( $lk ); ?>" aria-hidden="true">
											<span class="cpj-t-text"><i class="n"></i><i class="h"></i><i></i><i class="s"></i><span class="cpj-t-btns"><i></i><i></i></span></span>
											<?php if ( 'split' === $lk ) : ?><span class="cpj-t-aside"><i class="n"></i></span><?php endif; ?>
										</span>
										<strong><?php echo esc_html( $ll[0] ); ?></strong>
										<small><?php echo esc_html( $ll[1] ); ?></small>
									</label>
								<?php endforeach; ?>
							</div>
						</div>
						<div class="cps-field">
							<span class="cps-label"><?php esc_html_e( 'Heading level', 'hypeit' ); ?></span>
							<div class="cpj-levels" role="radiogroup" aria-label="<?php esc_attr_e( 'Heading level', 'hypeit' ); ?>">
								<?php foreach ( $levels as $hk => $hl ) : ?>
									<label class="cpj-level"><input type="radio" name="heading_tag" value="<?php echo esc_attr( $hk ); ?>" <?php checked( $s['heading_tag'], $hk ); ?> /><span><?php echo esc_html( $hl ); ?></span></label>
								<?php endforeach; ?>
							</div>
							<p class="cpw-muted"><?php esc_html_e( 'H2 suits most pages (the page title is usually the H1). The heading uses your theme’s own heading style.', 'hypeit' ); ?></p>
						</div>
					</section>

					<section class="cpw-card">
						<h3><?php esc_html_e( 'Buttons', 'hypeit' ); ?></h3>
						<div class="cps-grid2">
							<div class="cps-field">
								<label for="cpj-join-text"><?php esc_html_e( 'Blogger registration button', 'hypeit' ); ?></label>
								<input type="text" id="cpj-join-text" name="join_text" value="<?php echo esc_attr( $s['join_text'] ); ?>" />
								<input type="url" name="join_url" class="cpj-gap" value="<?php echo esc_attr( $s['join_url'] ); ?>" placeholder="<?php echo esc_attr( class_exists( 'CP_Onboarding' ) ? CP_Onboarding::url() : '' ); ?>" aria-label="<?php esc_attr_e( 'Registration link', 'hypeit' ); ?>" />
								<p class="cpw-muted"><?php esc_html_e( 'Link — leave empty to use your blogger onboarding form.', 'hypeit' ); ?></p>
							</div>
							<div class="cps-field">
								<label for="cpj-contact-text"><?php esc_html_e( 'Contact button', 'hypeit' ); ?></label>
								<input type="text" id="cpj-contact-text" name="contact_text" value="<?php echo esc_attr( $s['contact_text'] ); ?>" />
								<?php
								wp_dropdown_pages(
									array(
										'name'              => 'contact_page',
										'class'             => 'cpj-gap',
										'selected'          => (int) $s['contact_page'],
										'show_option_none'  => __( '— Choose your contact page —', 'hypeit' ),
										'option_none_value' => 0,
									)
								);
								?>
								<input type="url" name="contact_url" class="cpj-gap" value="<?php echo esc_attr( $s['contact_url'] ); ?>" placeholder="<?php esc_attr_e( 'or a custom link (overrides the page)', 'hypeit' ); ?>" aria-label="<?php esc_attr_e( 'Contact link', 'hypeit' ); ?>" />
								<p class="cpw-muted"><?php esc_html_e( 'The button is hidden until a page or link is set.', 'hypeit' ); ?></p>
							</div>
						</div>
						<div class="cps-field">
							<span class="cps-label"><?php esc_html_e( 'Main button', 'hypeit' ); ?></span>
							<div class="cps-options">
								<label class="cps-option"><input type="radio" name="primary" value="join" <?php checked( $s['primary'], 'join' ); ?> /><span><strong><?php esc_html_e( 'Blogger registration', 'hypeit' ); ?></strong><small><?php esc_html_e( 'Filled, shown first', 'hypeit' ); ?></small></span></label>
								<label class="cps-option"><input type="radio" name="primary" value="contact" <?php checked( $s['primary'], 'contact' ); ?> /><span><strong><?php esc_html_e( 'Contact', 'hypeit' ); ?></strong><small><?php esc_html_e( 'Filled, shown first', 'hypeit' ); ?></small></span></label>
							</div>
							<p class="cpw-muted"><?php esc_html_e( 'The other button uses the outline style.', 'hypeit' ); ?></p>
						</div>
						<div class="cps-field">
							<label for="cpj-btnclass"><?php esc_html_e( 'Theme button class', 'hypeit' ); ?> <small class="cpw-muted">(<?php esc_html_e( 'optional', 'hypeit' ); ?>)</small></label>
							<input type="text" id="cpj-btnclass" name="btn_class" value="<?php echo esc_attr( $s['btn_class'] ); ?>" placeholder="btn btn-primary" />
							<p class="cpw-muted"><?php esc_html_e( 'Only needed if your theme’s buttons use a custom class. Buttons already use the standard WordPress button classes.', 'hypeit' ); ?></p>
						</div>
					</section>

					<section class="cpw-card">
						<h3><?php esc_html_e( 'Blogger count', 'hypeit' ); ?></h3>
						<?php $sw( 'show_count', (bool) $s['show_count'], __( 'Show the number of bloggers', 'hypeit' ) ); ?>
						<div class="cps-field">
							<span class="cps-label"><?php esc_html_e( 'Counting method', 'hypeit' ); ?></span>
							<div class="cps-options">
								<label class="cps-option"><input type="radio" name="method" value="actual" <?php checked( $s['method'], 'actual' ); ?> /><span><strong><?php esc_html_e( 'Accurate count', 'hypeit' ); ?></strong><small><?php esc_html_e( 'The real number of bloggers, updated automatically', 'hypeit' ); ?></small></span></label>
								<label class="cps-option"><input type="radio" name="method" value="start" <?php checked( $s['method'], 'start' ); ?> /><span><strong><?php esc_html_e( 'Starting number', 'hypeit' ); ?></strong><small><?php esc_html_e( 'Start from a number you choose; every new blogger adds one', 'hypeit' ); ?></small></span></label>
							</div>
						</div>
						<div class="cps-grid2">
							<div class="cps-field">
								<label for="cpj-start"><?php esc_html_e( 'Starting number', 'hypeit' ); ?></label>
								<input type="number" id="cpj-start" name="start" min="0" step="1" value="<?php echo esc_attr( (int) $s['start'] ); ?>" />
								<?php if ( 'start' === $s['method'] ) : ?>
									<p class="cpw-muted"><?php echo esc_html( sprintf( /* translators: 1: start number, 2: new bloggers since, 3: total shown. */ __( 'Started at %1$s · %2$s new since then · showing %3$s.', 'hypeit' ), number_format_i18n( (int) $s['start'] ), number_format_i18n( $since ), number_format_i18n( $shown ) ) ); ?></p>
									<label class="cpw-check"><input type="checkbox" name="restart" value="1" /> <?php esc_html_e( 'Restart counting from this number now', 'hypeit' ); ?></label>
								<?php else : ?>
									<p class="cpw-muted"><?php esc_html_e( 'Used only with “Starting number”.', 'hypeit' ); ?></p>
								<?php endif; ?>
							</div>
							<div class="cps-field">
								<label for="cpj-label"><?php esc_html_e( 'Label', 'hypeit' ); ?></label>
								<input type="text" id="cpj-label" name="label" value="<?php echo esc_attr( $s['label'] ); ?>" />
								<label class="cpw-check cpj-gap"><input type="checkbox" name="plus" value="1" <?php checked( $s['plus'] ); ?> /> <?php esc_html_e( 'Show “+” before the number', 'hypeit' ); ?></label>
							</div>
						</div>
					</section>

					<div class="cps-save"><?php submit_button( __( 'Save', 'hypeit' ), 'primary', 'submit', false ); ?></div>
				</form>

				<aside class="cps-side">
					<section class="cpw-card">
						<h3><?php esc_html_e( 'Preview', 'hypeit' ); ?></h3>
						<div class="cpj-preview is-<?php echo esc_attr( $s['layout'] ); ?>" id="cpj-preview">
							<div class="cpj-p-content">
								<span class="cpj-p-count"><?php echo esc_html( ( $s['plus'] ? '+' : '' ) . number_format_i18n( $shown ) ); ?> <small><?php echo esc_html( $s['label'] ); ?></small></span>
								<span class="cpj-p-h" data-tag="<?php echo esc_attr( strtoupper( $s['heading_tag'] ) ); ?>"><?php echo esc_html( $s['heading'] ); ?></span>
								<span class="cpj-p-t"><?php echo esc_html( wp_trim_words( wp_strip_all_tags( $s['text'] ), 22 ) ); ?></span>
								<span class="cpj-p-btns"><i class="is-main"></i><i></i></span>
							</div>
							<div class="cpj-p-aside"><span class="cpj-p-count"><?php echo esc_html( ( $s['plus'] ? '+' : '' ) . number_format_i18n( $shown ) ); ?> <small><?php echo esc_html( $s['label'] ); ?></small></span></div>
						</div>
						<p class="cpw-muted"><?php esc_html_e( 'A sketch — on your site the section uses your theme’s fonts, colours and buttons.', 'hypeit' ); ?></p>
					</section>

					<section class="cpw-card">
						<h3><?php esc_html_e( 'Shortcode', 'hypeit' ); ?></h3>
						<?php
						$cp_sc = array(
							'[hypeit_join]'              => __( 'Full section — count, text and both buttons', 'hypeit' ),
							'[hypeit_join show="count"]' => __( 'Just the number, inside any text — e.g. “+312 Bloggers”', 'hypeit' ),
						);
						foreach ( $cp_sc as $code => $desc ) :
							?>
							<div class="cpj-sc">
								<code><?php echo esc_html( $code ); ?></code>
								<button type="button" class="button button-small cps-copy" data-link="<?php echo esc_attr( $code ); ?>" data-done="<?php esc_attr_e( 'Copied!', 'hypeit' ); ?>"><?php esc_html_e( 'Copy', 'hypeit' ); ?></button>
								<small><?php echo esc_html( $desc ); ?></small>
							</div>
						<?php endforeach; ?>
						<p class="cpw-muted"><?php esc_html_e( 'Works in any page, post, widget, Elementor shortcode widget or theme template.', 'hypeit' ); ?></p>
					</section>
				</aside>
			</div>
		</div>
		<script>
		( function () {
			var f = document.getElementById( 'cpj-form' ), p = document.getElementById( 'cpj-preview' );
			if ( ! f || ! p ) { return; }
			function val( n ) { var el = f.querySelector( '[name="' + n + '"]:checked' ) || f.querySelector( '[name="' + n + '"]' ); return el ? ( el.type === 'checkbox' ? el.checked : el.value ) : ''; }
			function paint() {
				p.className = 'cpj-preview is-' + val( 'layout' ) + ( val( 'show_count' ) ? '' : ' no-count' );
				var h = p.querySelector( '.cpj-p-h' ); h.textContent = val( 'heading' ); h.setAttribute( 'data-tag', String( val( 'heading_tag' ) ).toUpperCase() );
				p.querySelector( '.cpj-p-t' ).textContent = String( val( 'text' ) ).split( /\s+/ ).slice( 0, 22 ).join( ' ' );
				var main = p.querySelectorAll( '.cpj-p-btns i' );
				main[0].className = val( 'primary' ) === 'join' ? 'is-main' : ''; main[1].className = val( 'primary' ) === 'contact' ? 'is-main' : '';
				Array.prototype.forEach.call( p.querySelectorAll( '.cpj-p-count small' ), function ( s ) { s.textContent = val( 'label' ); } );
			}
			f.addEventListener( 'input', paint ); f.addEventListener( 'change', paint ); paint();
			document.addEventListener( 'click', function ( e ) {
				var b = e.target.closest( '.cps-copy' ); if ( ! b ) { return; }
				var done = function () { var t = b.textContent; b.textContent = b.getAttribute( 'data-done' ); setTimeout( function () { b.textContent = t; }, 1400 ); };
				if ( navigator.clipboard && window.isSecureContext ) { navigator.clipboard.writeText( b.getAttribute( 'data-link' ) ).then( done ); }
			} );
		} )();
		</script>
		<?php
	}
}
