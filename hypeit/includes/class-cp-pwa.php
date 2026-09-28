<?php
/**
 * PWA delivery: app shell, web app manifest, and service worker.
 * All URLs are built from the current site, so the app is domain-independent.
 *
 * @package HypeIt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CP_PWA {

	const QV = 'cp_app';

	/**
	 * Hook registration.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'add_rewrite_rules' ) );
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_action( 'template_redirect', array( __CLASS__, 'handle' ) );
		// Service worker + manifest are served from the app's own URL with a query
		// string (not a virtual .js/.webmanifest file) — some hosts answer .js
		// requests themselves and never pass them to WordPress. Handled as early
		// as possible so no redirect or other plugin can interfere.
		add_action( 'init', array( __CLASS__, 'early' ), 1 );
	}

	/**
	 * Serve /campaign-app/?cp_sw=1 and /campaign-app/?cp_manifest=1.
	 */
	public static function early() {
		$is_sw  = isset( $_GET['cp_sw'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$is_man = isset( $_GET['cp_manifest'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! $is_sw && ! $is_man ) {
			return;
		}
		$req  = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$want = (string) wp_parse_url( self::app_url(), PHP_URL_PATH );
		if ( untrailingslashit( $req ) !== untrailingslashit( $want ) ) {
			return;
		}
		if ( ! self::enabled() ) {
			status_header( 404 );
			exit;
		}
		while ( ob_get_level() > 0 ) {
			ob_end_clean();
		}
		status_header( 200 );
		if ( $is_sw ) {
			self::output_sw();
		} else {
			self::output_manifest();
		}
		exit;
	}

	/**
	 * Register app routes.
	 */
	public static function add_rewrite_rules() {
		$slug = preg_quote( self::slug(), '#' );
		add_rewrite_rule( '^' . $slug . '/manifest\.webmanifest$', 'index.php?' . self::QV . '=manifest', 'top' );
		add_rewrite_rule( '^' . $slug . '/sw\.js$', 'index.php?' . self::QV . '=sw', 'top' );
		add_rewrite_rule( '^' . $slug . '/?$', 'index.php?' . self::QV . '=shell', 'top' );
	}

	/**
	 * App address slug (custom; default "campaign-app" so existing installs keep working).
	 *
	 * @return string
	 */
	public static function slug() {
		$app  = CP_App_Settings::get();
		$slug = isset( $app['app_slug'] ) ? trim( (string) $app['app_slug'], '/' ) : '';
		return '' !== $slug ? $slug : 'campaign-app';
	}

	/**
	 * Register the query var.
	 *
	 * @param array $vars Query vars.
	 * @return array
	 */
	public static function query_vars( $vars ) {
		$vars[] = self::QV;
		return $vars;
	}

	/**
	 * Whether the PWA is enabled.
	 *
	 * @return bool
	 */
	public static function enabled() {
		$app = CP_App_Settings::get();
		return ! empty( $app['pwa_enabled'] );
	}

	/** @return string App URL. */
	public static function app_url() {
		return home_url( '/' . self::slug() . '/' );
	}

	/** @return string Manifest URL. */
	public static function manifest_url() {
		return add_query_arg( 'cp_manifest', '1', self::app_url() );
	}

	/** @return string Service worker URL. */
	public static function sw_url() {
		return add_query_arg( 'cp_sw', CP_VERSION, self::app_url() );
	}

	/**
	 * Route handler.
	 */
	public static function handle() {
		$what = get_query_var( self::QV );
		if ( '' === $what || null === $what ) {
			return;
		}

		if ( ! self::enabled() ) {
			status_header( 404 );
			self::no_cache();
			return;
		}

		switch ( $what ) {
			case 'manifest':
				self::output_manifest();
				break;
			case 'sw':
				self::output_sw();
				break;
			case 'shell':
			default:
				self::output_shell();
				break;
		}
		exit;
	}

	/**
	 * Output the web app manifest.
	 */
	private static function output_manifest() {
		$app = CP_App_Settings::get();

		self::no_cache();
		header( 'Content-Type: application/manifest+json; charset=utf-8' );

		$name       = $app['app_name'] ? $app['app_name'] : __( 'HypeIt', 'hypeit' );
		$short      = $app['app_short_name'] ? $app['app_short_name'] : $name;
		$theme      = $app['app_theme_color'] ? $app['app_theme_color'] : '#000000';
		$background = $app['app_bg_color'] ? $app['app_bg_color'] : '#000000';

		$manifest = array(
			'name'             => $name,
			'short_name'       => $short,
			'start_url'        => self::app_url(),
			'scope'            => wp_parse_url( self::app_url(), PHP_URL_PATH ),
			'display'          => 'standalone',
			'orientation'      => 'portrait',
			'background_color' => $background,
			'theme_color'      => $theme,
		);

		$icon_id = (int) $app['app_icon_id'];
		if ( $icon_id ) {
			$icon_url = wp_get_attachment_url( $icon_id );
			if ( $icon_url ) {
				$manifest['icons'] = array(
					array(
						'src'     => $icon_url,
						'sizes'   => '192x192',
						'type'    => 'image/png',
						'purpose' => 'any',
					),
					array(
						'src'     => $icon_url,
						'sizes'   => '512x512',
						'type'    => 'image/png',
						'purpose' => 'any maskable',
					),
				);
			}
		}

		echo wp_json_encode( $manifest );
	}

	/**
	 * Never let page caches (LiteSpeed etc.) store app files — a stale
	 * service worker breaks updates and push notifications.
	 */
	private static function no_cache() {
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		do_action( 'litespeed_control_set_nocache', 'campaign app' );
		nocache_headers();
		header( 'X-LiteSpeed-Cache-Control: no-cache' );
		header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
	}

	/**
	 * Output the service worker.
	 */
	private static function output_sw() {
		self::no_cache();
		header( 'Content-Type: application/javascript; charset=utf-8' );
		header( 'Service-Worker-Allowed: ' . (string) wp_parse_url( self::app_url(), PHP_URL_PATH ) );

		$cache   = 'cp-app-' . CP_VERSION;
		$shell   = esc_js( self::app_url() );
		$offline = esc_js( self::app_url() );

		$app_cfg = CP_App_Settings::get();
		$icon    = ! empty( $app_cfg['app_icon_id'] ) ? (string) wp_get_attachment_url( (int) $app_cfg['app_icon_id'] ) : '';

		echo "var CACHE='" . $cache . "';\n";
		echo "var SHELL='" . $shell . "';\n";
		echo 'var ICON=' . wp_json_encode( $icon ) . ";\n";
		?>
self.addEventListener('install', function (e) {
	self.skipWaiting();
	e.waitUntil(caches.open(CACHE).then(function (c) { return c.addAll([SHELL]); }));
});
self.addEventListener('activate', function (e) {
	e.waitUntil(caches.keys().then(function (keys) {
		return Promise.all(keys.map(function (k) { if (k !== CACHE) { return caches.delete(k); } }));
	}).then(function () { return self.clients.claim(); }));
});
self.addEventListener('push', function (e) {
	var d = {};
	try { d = e.data ? e.data.json() : {}; } catch (err) { d = { title: e.data ? e.data.text() : '' }; }
	var opts = { body: d.body || '', tag: d.tag || 'cp', renotify: true, data: { url: d.url || SHELL } };
	if (ICON) { opts.icon = ICON; opts.badge = ICON; }
	if (d.icon) { opts.icon = d.icon; }
	e.waitUntil(self.registration.showNotification(d.title || 'HypeIt', opts));
});
self.addEventListener('notificationclick', function (e) {
	e.notification.close();
	var url = (e.notification.data && e.notification.data.url) || SHELL;
	e.waitUntil(self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (list) {
		for (var i = 0; i < list.length; i++) {
			var c = list[i];
			if (c.url.indexOf(SHELL) === 0 && 'focus' in c) {
				c.postMessage({ type: 'cp-open', url: url });
				return c.focus();
			}
		}
		return self.clients.openWindow(url);
	}));
});
self.addEventListener('fetch', function (e) {
	var req = e.request;
	if (req.method !== 'GET') { return; }
	var url = new URL(req.url);
	// Never cache API calls — always go to network for fresh data.
	if (url.pathname.indexOf('/wp-json/') !== -1) { return; }
	if (req.mode === 'navigate') {
		e.respondWith(fetch(req).catch(function () { return caches.match(SHELL); }));
		return;
	}
	e.respondWith(caches.match(req).then(function (hit) {
		return hit || fetch(req).then(function (res) {
			var copy = res.clone();
			caches.open(CACHE).then(function (c) { c.put(req, copy); });
			return res;
		});
	}));
});
		<?php
	}

	/**
	 * Output the app shell.
	 */
	private static function output_shell() {
		self::no_cache();
		header( 'Content-Type: text/html; charset=utf-8' );
		require CP_DIR . 'templates/app.php';
	}
}
