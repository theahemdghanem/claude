=== Eye Comfort Dark Mode ===
Contributors: ilikeagency
Tags: dark mode, admin, dashboard, accessibility, eye strain
Requires at least: 6.0
Tested up to: 7.2
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A real dark mode for the WordPress dashboard — every screen, including the block editor and other plugins' pages, with no white flash.

== Description ==

Made for people whose eyes hurt from white screens and who spend all day in WordPress.

Instead of a stylesheet that only knows core screens, the plugin reads every stylesheet a page loads and writes a dark version of each colour. That means:

* **Every admin screen** goes dark — core screens and other plugins' pages alike — without per-plugin fixes.
* **The block editor** — toolbars, sidebars, menus, the inserter, the media library and the writing area itself.
* **The classic editor** (TinyMCE) writing area.
* **The login screen**, once you have signed in on that browser.
* **No white flash** between pages: colours are applied before the page is painted.
* Accent colours (buttons, badges, notices) keep their hue; links are lightened so they stay readable.

Everything is per user. Each person picks:

* **Mode:** always dark, follow the computer's light/dark setting, or off.
* **Palette:** Dim grey (gentlest), Dark, or Pure black (OLED, dark rooms).
* **Text brightness:** Soft, Normal or Bright — soft lowers glare.
* **Dim images** slightly, **darken the writing area** of editors, and optionally **darken the public site** while signed in (only you see it).

Switch from the moon in the toolbar, or press **Alt+Shift+D** anywhere in the dashboard (it works inside the editor too). All settings are also under **Users → Profile → Eye Comfort Dark Mode**.

== Installation ==

1. Upload the `eye-comfort-dark-mode` folder to `/wp-content/plugins/` (or upload the zip under Plugins → Add New → Upload Plugin).
2. Activate it. The dashboard turns dark straight away.
3. Hover the moon in the toolbar to change the palette or text brightness.

== Frequently Asked Questions ==

= Will other users on my site get dark mode too? =

Yes, it starts on for everyone, and each person can turn it off with one click (the moon, or Alt+Shift+D). Developers can change the starting point with the `ecdm_default_prefs` filter.

= A plugin screen still has something light on it. =

Colours set by images or by stylesheets loaded from another domain can't be changed. Everything else should adapt; please report anything that doesn't.

= Does it change my site for visitors? =

No. The public site is only darkened for you, and only if you turn that option on.

== Changelog ==

= 1.0.0 =
* First release.
