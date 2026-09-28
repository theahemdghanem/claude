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
		// Fixed URL on purpose: the browser picks up new versions by comparing the
		// script's content. A versioned URL let an old cached app page re-register
		// the old worker, and the app bounced between versions on launch.
		return add_query_arg( 'cp_sw', '1', self::app_url() );
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
			'id'               => wp_parse_url( self::app_url(), PHP_URL_PATH ),
			'description'      => __( 'Manage campaigns and bloggers from your phone.', 'hypeit' ),
			'display_override' => array( 'standalone', 'minimal-ui' ),
			'categories'       => array( 'business', 'productivity' ),
			// Long-press the app icon to jump straight in.
			'shortcuts'        => array(
				array( 'name' => __( 'New campaign', 'hypeit' ), 'url' => self::app_url() . '#/campaigns/new' ),
				array( 'name' => __( 'Campaigns', 'hypeit' ), 'url' => self::app_url() . '#/campaigns' ),
				array( 'name' => __( 'Bloggers', 'hypeit' ), 'url' => self::app_url() . '#/bloggers' ),
				array( 'name' => __( 'Search', 'hypeit' ), 'url' => self::app_url() . '#/search' ),
			),
		);

		$gen = class_exists( 'CP_Icons' ) ? CP_Icons::icons() : array();
		$icon_id = (int) $app['app_icon_id'];
		if ( isset( $gen['i192'], $gen['i512'], $gen['m512'] ) ) {
			$manifest['icons'] = array(
				array( 'src' => $gen['i192'], 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any' ),
				array( 'src' => $gen['i512'], 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any' ),
				array( 'src' => $gen['m512'], 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable' ),
			);
		} elseif ( $icon_id ) {
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

		$assets = array(
			add_query_arg( 'ver', CP_VERSION, CP_URL . 'assets/css/app.css' ),
			add_query_arg( 'ver', CP_VERSION, CP_URL . 'assets/js/app.js' ),
		);

		echo "var CACHE='" . $cache . "';\n";
		echo "var IMG='cp-app-img';\n";
		echo "var SHELL='" . $shell . "';\n";
		echo 'var ASSETS=' . wp_json_encode( array_map( 'esc_url_raw', $assets ) ) . ";\n";
		echo 'var ICON=' . wp_json_encode( $icon ) . ";\n";
		?>
self.addEventListener('install', function (e) {
	self.skipWaiting();
	// Shell + app code up front, so the app opens instantly and works offline.
	e.waitUntil(caches.open(CACHE).then(function (c) {
		return c.addAll([SHELL]).then(function () { return Promise.all(ASSETS.map(function (a) { return c.add(a).catch(function () {}); })); });
	}));
});
self.addEventListener('activate', function (e) {
	e.waitUntil(caches.keys().then(function (keys) {
		return Promise.all(keys.map(function (k) { if (k !== CACHE && k !== IMG) { return caches.delete(k); } }));
	}).then(function () { return self.clients.claim(); }));
});
self.addEventListener('message', function (e) {
	if (e.data && e.data.type === 'cp-skip-waiting') { self.skipWaiting(); }
});
self.addEventListener('push', function (e) {
	var d = {};
	try { d = e.data ? e.data.json() : {}; } catch (err) { d = { title: e.data ? e.data.text() : '' }; }
	var opts = { body: d.body || '', tag: d.tag || 'cp', renotify: true, data: { url: d.url || SHELL } };
	if (ICON) { opts.icon = ICON; opts.badge = ICON; }
	if (d.icon) { opts.icon = d.icon; }
	var jobs = [self.registration.showNotification(d.title || 'HypeIt', opts)];
	// Tell an open app to refresh its notification count.
	jobs.push(self.clients.matchAll({ type: 'window' }).then(function (list) { list.forEach(function (c) { c.postMessage({ type: 'cp-refresh' }); }); }));
	if (self.navigator && self.navigator.setAppBadge) { jobs.push(self.navigator.setAppBadge().catch(function () {})); }
	e.waitUntil(Promise.all(jobs));
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
// Keep the photo/logo cache from growing forever.
function trim(name, max) {
	return caches.open(name).then(function (c) {
		return c.keys().then(function (k) { if (k.length > max) { return Promise.all(k.slice(0, k.length - max).map(function (r) { return c.delete(r); })); } });
	});
}
self.addEventListener('fetch', function (e) {
	var req = e.request;
	if (req.method !== 'GET') { return; }
	var url = new URL(req.url);
	// Never cache API calls — the app keeps its own offline copy of data.
	if (url.pathname.indexOf('/wp-json/') !== -1 || url.search.indexOf('rest_route=') !== -1) { return; }
	if (req.mode === 'navigate') {
		// Always the fresh app page; the saved copy only when offline.
		e.respondWith(fetch(req).then(function (res) {
			if (res.ok && url.href.indexOf(SHELL) === 0) { var copy = res.clone(); caches.open(CACHE).then(function (c) { c.put(SHELL, copy); }); }
			return res;
		}).catch(function () {
			return caches.match(SHELL).then(function (hit) { return hit || Response.error(); });
		}));
		return;
	}
	// Images (blogger photos, logos): show the saved copy, refresh it in the background.
	if (req.destination === 'image' && url.origin === self.location.origin) {
		e.respondWith(caches.open(IMG).then(function (c) {
			return c.match(req).then(function (hit) {
				var net = fetch(req).then(function (res) {
					if (res.ok) { c.put(req, res.clone()); trim(IMG, 400); }
					return res;
				}).catch(function () { return hit; });
				return hit || net;
			});
		}));
		return;
	}
	if (url.origin !== self.location.origin) { return; }
	e.respondWith(caches.match(req).then(function (hit) {
		return hit || fetch(req).then(function (res) {
			if (res.ok) { var copy = res.clone(); caches.open(CACHE).then(function (c) { c.put(req, copy); }); }
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
