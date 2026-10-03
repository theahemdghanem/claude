=== CELB MGMT ===
Contributors: iLike Agency
Author: iLike Agency
Requires at least: 6.0
Requires PHP: 7.4
Stable tag: 2.7.2
License: GPLv2 or later

Celebrity management directory for iLike Agency. Manage and display celebrity
profiles with a responsive grid, a homepage carousel, individual profile pages,
career history, awards, photo galleries, and social media links.

== Description ==

CELB MGMT registers a "Celebrities" custom post type and provides everything
needed to run a complete celebrity directory:

* Responsive celebrity grid (3 per row desktop / 2 per row mobile), randomized
  on every page load.
* Homepage carousel (up to 6 desktop / 2x2 mobile), randomized on every load.
* Full individual profile pages with device-specific hero images, biography,
  social media row, career history, awards, and a zoomable gallery lightbox.
* No page-builder dependency. Shortcode based. SEO-friendly URLs.

Design system: dark editorial — Cormorant Garamond + Inter, champagne gold
(#c9aa6c) accent. All styles are scoped under `.celb-scope` to avoid theme
conflicts.

== Installation ==

1. Upload the `celb-mgmt` folder to `/wp-content/plugins/`, or install the
   .zip via Plugins > Add New > Upload Plugin.
2. Activate the plugin through the Plugins menu in WordPress.
3. A new "Celebrities" menu appears in the admin sidebar.
4. Permalinks are flushed automatically on activation. If single profile URLs
   ever 404, visit Settings > Permalinks and click Save once.

== Usage ==

= Adding a celebrity =

Go to Celebrities > Add New and fill in:

* Title — the celebrity's name.
* Profile Image — the square image shown on grid/carousel cards (sidebar box).
  Falls back to the Featured Image if left empty.
* Biography — the rich-text Biography box (its own WYSIWYG editor). Existing
  content from older entries is carried over automatically the first time you
  open and save the celebrity.
* Birthdate — optional date with a "Show year publicly" toggle. On the
  celebrity's birthday the profile shows an animated "Happy Birthday" greeting
  next to the date and drops confetti for a few seconds.
* Celebrity Details — Role and Nationality (shown as "Role / Nationality").
* Hero Images — separate Desktop and Mobile images for the profile hero.
* Social Media — only filled-in platforms appear on the front end. Supports
  Instagram, Threads, Facebook, X, TikTok, YouTube, Snapchat, IMDb, Website.
* Career History — repeatable Project / Role / Year rows, or bulk import via
  CSV (columns: Project, Role, Year). Tick "Coming soon" on a row to mark it as
  under production: it pins to the top of the list, shows a gold "Coming Soon"
  badge instead of the year, and the title is highlighted. Other rows sort
  newest year to oldest automatically.
* Awards — repeatable Festival / Title / Project (optional) / Year / Location,
  or bulk import via CSV (columns: Festival, Award Title, Project, Year,
  Location). Leave Project blank for Lifetime Achievement / Honorary awards.
* Photo Gallery — add multiple images; drag thumbnails to reorder.

= Shortcodes =

[CLEB_celebrities]
  Full responsive grid of all celebrities, randomized each load.
  Optional: [CLEB_celebrities limit="9"] to cap the number shown.

[CLEB_celebrities_carousel]
  Compact homepage carousel, randomized each load.
  Optional: [CLEB_celebrities_carousel limit="12"] to set the pool size.

= Carousel / grid assets inside page builders =

Front-end CSS/JS load automatically on the single profile and on any page
whose content contains a shortcode. If you place a shortcode inside a widget
or builder module that bypasses normal post content, force-load the assets:

  add_filter( 'celb_load_frontend_assets', '__return_true' );

== Customising colours / fonts ==

All theming uses CSS variables on `.celb-scope`. Override them in your theme:

  .celb-scope {
    --celb-gold: #c9aa6c;
    --celb-bg: #000;
    --celb-text: #f4f1ea;
  }

== Settings ==

Celebrities > Settings provides:

* Copy-ready shortcodes.
* Accent colour (default champagne gold #c9aa6c).
* Profile page background: Light (white site) or Dark (black site).
* Grid columns on desktop (default 3; mobile always 2).
* Carousel items visible on desktop (default 6; mobile always a 2x2 set).
* "Pull hero flush under site header" toggle to remove a theme's top gap above
  the hero (on by default).
* Call-to-action button shown below the gallery (label + link; e.g. "Let's
  Talk"). On by default.

== Self-submission portal ==

Celebrities can submit their own profiles from the front end:

1. In Celebrities > Settings, enable the Submission portal and add one or more
   access passwords (use "Generate" for a strong code; give each talent their
   own so you can revoke access individually).
2. Put the [CLEB_submit] shortcode on a page (e.g. /submit/).
3. Visitors enter a password, then fill in the form: full name, role,
   nationality, birthday (with show-year option), biography, roles & upcoming
   projects (with "Coming soon"), awards, and social links.
4. On submit they see: "Your profile is currently pending creation and review.
   It will be live very soon." A Draft profile is created and the site admin is
   emailed.
5. Draft profiles are flagged "Self-submitted" in the Celebrities list. Open
   one, review/rewrite the bio, add the Profile Image and Hero banners, tidy the
   social links, then Publish. Nothing goes live until you publish it.

Photos and cover banners are intentionally admin-only (uploaded during review).

== Newsroom ==

A "Newsroom" menu (under Celebrities) lets you publish news articles about a
celebrity. Each article has:

* Headline (title) and article body (main editor).
* Header Image — the popup header banner (its own field).
* Featured Image — the small list thumbnail.
* Celebrity (which profile it appears on) and a manual Location.
* Media Gallery (shown in the popup; downloadable as a single ZIP).
* Related Links — any number of labelled links (YouTube, social, coverage);
  each opens in a new tab.

Published articles appear in a collapsible "Newsroom" section on the celebrity's
profile: a thumbnail + headline list. Clicking one opens a popup over a 50%
dark overlay with the gradient header (title, date, location), the article,
the media gallery (click to open the lightbox), a "Download all media" button,
and the related links.

For a combined page with every celebrity's news, add the [CLEB_newsroom]
shortcode to a page. It lists all articles with a small "Filter by artist"
dropdown on the left.

== Changelog ==

= 2.7.2 =
* Fixed: importing a talent's rate submission into an EXISTING rate card no longer
  replaces that card's services - prices are merged by matching service names;
  blank entries keep the current price.
* New: Settings -> Custom Theme (Google Font URL, body/heading fonts, text and
  background colours). Empty = inherit the theme. Also applied to standalone pages.
* New: Settings -> Page addresses - custom URL slugs for Our Stars, Talent
  onboarding, Personal data and rate-card forms (pretty URLs, auto-refreshed).
* New: optional custom link ending when generating a rate-card onboarding link.
* New: email notice when a talent submits their rates.
* Performance: removed an unused Google Fonts download on roster pages.
* Cleanup: removed retired video helpers and old All-in-One CSS; Author URI fixed.

= 2.7.1 =
* Typography now inherits the active theme's fonts across the on-page components
  (roster, profiles, videos, newsroom) instead of the plugin forcing its own
  fonts. Arabic (RTL) content keeps its dedicated font. The standalone private
  pages (rate card, Our Stars, onboarding portals) keep their own branding.

= 2.7.0 =
* New: Rate Card self-onboarding + templates & duplication.
* Rate Cards list row actions: Duplicate, New (empty rates), Save as Template.
* Celebrities -> Rate Onboarding: generate a private link (based on any card or
  template, optionally linked to a talent). The talent enters their name then a
  price per service on a standalone branded form; submissions are listed and can
  be imported into a new or existing rate card in one click.
* Creating from a template/card keeps services, platforms, categories and
  structure while resetting all prices.

= 2.6.3 =
* Tidied the video Cover control in the editor: name/link stack on the left, a neat
  poster thumbnail on the right with a small × to remove and a Set cover / Change
  button beneath it. No more overlapping controls.

= 2.6.2 =
* "Our Stars" now has a clean URL: /our-stars (the old ?celb_page=stars link still
  works). Rewrite rules refresh automatically on update - no need to re-save Permalinks.

= 2.6.1 =
* Videos: added a manual Cover image picker on each video row. When set it's used
  as the card thumbnail (overriding the auto-fetched one) - reliable for
  Instagram/TikTok where covers can't always be pulled automatically.

= 2.6.0 =
* All-in-One reworked into a STANDALONE, agency-branded "Our Stars" page with its
  own design (own logo, fonts, colours - not the site theme) at /?celb_page=stars.
* Mobile-only: on desktop/tablet it shows an "open on mobile" message; the full
  page renders on phones.
* Enable it + set the page title, intro and bottom CTA under Settings ->
  All-in-One "Our Stars" page. Standalone URL shown there with a Copy button.
* Fixed the hero image not filling its rounded box (now a fixed ~1.2 frame with
  the image covering it fully). Bio paragraphs are now justified.
* Lead celebrities first, everyone else shuffled on each visit.

= 2.5.1 =
* Video poster cards now show a real cover image where available: YouTube (as
  before), plus Vimeo and TikTok via their public oEmbed (cached 12h), and a
  best-effort Instagram cover via its public media endpoint. Where a platform
  blocks its thumbnail, the card falls back to the clean dark tile.

= 2.5.0 =
* New: All-in-One page - shortcode [celb_all_in_one]. One theme-native, full-width
  page that gathers every profile as a rich card (name, role, View Profile, bio,
  rounded ~1.2 hero image, social links). Lead celebrities pinned first, the rest
  shuffled on each visit. Fully responsive (desktop/tablet/mobile). Editable intro
  text and a configurable bottom CTA (label + link) in Settings -> All-in-One page.

= 2.4.1 =
* Videos now show as clean dark poster cards; clicking opens the video in a
  focused lightbox player - no more bulky white Instagram/TikTok widgets in the
  layout. YouTube cards use the real thumbnail.
* Links are auto-normalised, so Instagram reel URLs that include the username
  (…/username/reel/ID/) now work without editing them. TikTok, Vimeo and
  YouTube share links are handled too.

= 2.4.0 =
* New: per-celebrity Videos section. Add videos in the celebrity editor with a
  custom name + link; drag to reorder and set the section title. YouTube and
  Vimeo play inline (responsive 16:9), Instagram and TikTok embed as posts, and
  anything else shows as a tidy "Watch" card. Appears on the profile after the
  gallery.

= 2.3.2 =
* Standalone portals: removed the redundant agency-name line under the logo
  (it now only shows if no logo is set).

= 2.3.1 =
* Fixed the Copy button on the Personal Data Form builder page.
* Personal Data form dropdowns now match the other fields (custom chevron,
  same border/height/colours) instead of the browser's default select.
* Moved Settings to the end of the Celebrities admin menu.

= 2.3.0 =
* Self-onboarding is now a standalone, agency-branded portal with its own design
  (own logo, fonts, colours) that does NOT inherit the site theme. Private link:
  /?celb_page=onboarding (uses the same enable + passwords as [CLEB_submit]).
* New module: Personal Data / Emergency Contacts - a secure, password-protected
  standalone page (own premium design, independent of the theme) at
  /?celb_page=personal. Enable it and set a password under Settings.
* Backend form builder (Celebrities -> Personal Data Form): add/edit/delete and
  drag-reorder sections and questions; per question set required + field type
  (text, long text, phone, email, address, location, number, date, dropdown,
  yes/no). Ships with sensible default sections and fields.
* Submissions appear as clean profile cards (Celebrities -> Personal Data),
  grouped by section, not a raw table - with delete.

= 2.2.3 =
* Self-onboarding portal ([CLEB_submit]) now uses light text on the dark page -
  the intro line, field labels, hints, section titles and checkbox text were
  dark-on-black and invisible. Input fields keep dark text on their white
  background so what you type stays readable. Password gate text fixed too.

= 2.2.2 =
* [CLEB_request] now renders the new Artist Contact Form (multi-artist checkbox
  selection, All artists, WhatsApp, reCAPTCHA) so existing pages using it update
  automatically - no need to change the page. The original booking form is still
  available as [CLEB_booking].

= 2.2.1 =
* Artist contact form: added an optional Company / brand field (stored + emailed).

= 2.2.0 =
* New: dedicated Artist Contact Form — shortcode [artist_contact_form], separate
  from the site's general Contact Us. Collects name, phone, WhatsApp, email,
  request details, and a checkbox artist selection (All artists + one or many),
  auto-built from the live roster (new artists appear, removed ones drop off).
* "Let's Talk" on each artist profile can now point to a chosen contact page and
  pre-selects that artist (Settings → Contact page). Falls back to the CTA URL.
* Submissions are saved under Celebrities → Artist Requests and emailed to a
  configurable recipient (branded template).
* Anti-spam: Google reCAPTCHA v2 (configurable keys in Settings) plus a built-in
  honeypot, time-trap and per-IP rate limit.

= 2.1.2 =
* Fixed the roster category filter not hiding cards: the theme-proofing added in
  2.1.1 forced the card to display:block !important, which blocked the filter's
  hide. The filter now toggles a class that correctly shows/hides cards.

= 2.1.1 =
* Roster/carousel card made theme-proof: the photo box now uses a padding-based
  4:5 frame and all overlay elements (image, name/role, badge, arrow) are forced
  with !important so the active theme's global CSS can no longer break them.
  Fixes the empty band above photos and the broken mobile grid where the name
  rendered beside the image and got clipped. Crop now favours the face.

= 2.1.0 =
* Roster categories: a celebrity can now be in TWO categories (e.g. Actor + Host).
  New category set — Actors, Actress, Host, Director, Writer, Producer, Musician,
  Rising Stars. Removed "Music & Hosts" (existing entries migrate to Musician).
* The roster filter shows a button for every category in use, and a celebrity
  appears under each of their categories.
* Carousel: forced the card image to fully cover its box (no more empty bands
  above/below) and focused the crop toward the face.

= 2.0.1 =
* Roster cards now render the photo as a real image (object-fit cover) instead of
  a CSS background — fixes empty/blank cards on mobile (iOS) and loads reliably.
* Talents with no photo now show a clearly visible monogram tile instead of an
  empty box.
* The [CLEB_celebrities_carousel] homepage carousel now uses the same new card
  design as the grid.

= 2.0.0 =
* Roster (V2): redesigned celebrity cards — editorial overlay style with the name
  / role over the image, a slow image zoom and a reveal arrow on hover, and a
  monochrome (gold-free) Lead pin.
* Tightened the spacing between the roster title, intro and filter row.
* Lead ordering is now the default: any celebrity marked Lead always appears
  first, and everyone else is shuffled on each load.

= 1.55.2 =
* Contract tokens fixed: {{monthly_fee}} now prints ONLY the amount + currency
  (e.g. "30,000 EGP"), and {{payment_day}} ONLY the day — each drops into its own
  place in the sentence instead of the whole phrase landing in one spot.
* Numbers no longer jump position inside right-to-left Arabic lines (amount and
  day are isolated left-to-right).
* New "Contract Language" option in the template builder (English / Arabic). Auto-
  filled values and the commission table render in the template's language —
  Arabic labels, Arabic phrasing, and day shown as a plain number in Arabic.

= 1.55.1 =
* Contracts: added a Payment day (1–31) to the Monthly fee / retainer — the day of
  the month the fee is due. New token {{payment_day}} (e.g. "5th"); {{monthly_fee}}
  and the commission table now read e.g. "5,000 EGP / month, payable on the 5th".

= 1.55.0 =
* Contracts: Commission Percentages box now has an optional Monthly fee / retainer
  (enable toggle + amount) and a Currency selector (EGP/USD/EUR/GBP/SAR/AED/KWD/
  QAR). New template tokens: {{monthly_fee}} (e.g. "5,000 EGP / month", blank if
  disabled), {{monthly_fee_amount}}, {{currency}}. The monthly fee also appears in
  {{commission_table}} when enabled.

= 1.54.0 =
* Shooting days now have a Start time and Duration (hours) per day, so the
  calendar event shows a real time range instead of an all-day block. Postpone
  also takes a new time.
* Calendar alert is configurable: each Project has a "Calendar alert" (none / at
  time / 15m / 30m / 1h / 2h / 1 day / 2 days) that drives the event's reminder;
  schedules use their own reminder. Replaced the fixed 1-day/1-hour alerts.
* Location/map: clarified that the Address must be a plain address (not a pasted
  Maps link) so the calendar renders an embedded map; shooting-day locations that
  match a defined location use that plain address.

= 1.53.0 =
* Projects: added types Program/Podcast/Interview/Filming (+ existing) with an
  "Other" free-text field; Production Status is now Upcoming/Ongoing/Completed/
  Postponed/Canceled; added a Celebrity email field; added Recurring (weekly).
* Schedule: added Status (Upcoming/Ongoing/Completed/Postponed/Canceled), the same
  type list with "Other" free-text, a Celebrity email field, Postponed-to date/
  time, and weekly Recurring.
* Postpone/Cancel now drive the calendar AND email: canceled items are removed
  from every calendar; postponed items move to the new date; the celebrity is
  emailed on status changes and shooting-day postpone/cancel, with the new date.
* Email notifications: branded HTML template with the agency logo; configurable
  sender name/email, reply-to and CC in Settings; optional reminder N days before
  via a daily cron.
* Recurring projects/schedules emit a weekly RRULE so calendar apps auto-generate
  future occurrences.
* Agency master calendar: switched to a query-string subscription URL that needs
  no rewrite rules, fixing the "address is invalid" / "validation failed" errors.

= 1.52.0 =
* Calendar location now uses the geocodable ADDRESS (falling back to the label),
  so events show a map pin instead of a bare name. Project shooting days resolve
  their location to the matching Shooting Location's address.
* Shooting days: new status per day — Scheduled / Completed / Postponed / Canceled.
  Canceled days drop off the calendar; Completed stay (labelled); Postponed asks
  for a new date/call and the event moves to the new date. Every change is
  recorded in Update History with the original date.
* Schedule duration is now entered in HOURS (e.g. 1.5) instead of minutes.
* Agency master calendar: token is generated eagerly and rewrite rules flush on
  update, fixing the "address is invalid" error on the subscription link.

= 1.51.0 =
* Fix (logo): all backend, card, portal and email logo references now resolve
  through one helper (Brand Logo URL setting, then the site custom logo), instead
  of scattered hardcoded URLs (including a stale /2021/04/ path that could 404).
  Set the logo once in Settings and it applies everywhere.
* Fix (calendar location): shooting-day events now fall back to the project's
  first Shooting Location when a day has no location of its own, so the event's
  Location field is populated.
* New: Agency master calendar — a single private subscription feed of every
  celebrity's projects, shooting days and confirmed bookings, with Apple/Outlook
  (webcals) and Google (https) links in Settings.

= 1.50.3 =
* Fix: calendar subscription link no longer triggers an "Insecure Connection" /
  validation error on iOS. The Apple Calendar link now uses the TLS-secured
  webcals:// scheme (fetched over HTTPS) instead of webcal:// (which iOS fetches
  over plain HTTP). The https:// link is still offered for Google/Outlook.

= 1.50.2 =
* Announcement card: the "Know more" link now uses the celebrity's smart link
  (branded short URL) instead of the full profile permalink.

= 1.50.1 =
* Announcement card: the title now always renders on a single line — the font
  shrinks to fit the full "<Name> joins iLike Agency" headline regardless of the
  name length, instead of wrapping.

= 1.50.0 =
* New: Announcement social card for celebrity profiles. Each profile now has an
  "Announcement Card" box that generates a 1500x2000 PNG — photo, logo, a
  megaphone "Announcement" label, the headline "<Name> joins iLike Agency", the
  nationality / role as a subtitle, and a "Know more" link to the profile URL.
  Supports a custom uploaded card image (falls back to the profile/featured
  image). Built on the same renderer as the newsroom card, now resolution-aware.

= 1.49.6 =
* [CLEB_celebrities] roster cards restyled to match the iLike theme's homepage
  roster: photos now render in full colour (grayscale filter removed), name and
  role use the active theme font, role recoloured to #999999, lighter photo
  overlay, 4:5 portraits, and the gold hover accent removed.

= 1.49.5 =
* Fix: the celebrities profile-cards carousel/grid now loads its full styling
  (including the roster display fonts) on any page, including builder pages where
  it is the only plugin element. The roster fonts are detected for the carousel
  too (previously only the grid) and force-printed in the footer alongside the
  main CSS/JS when assets are enqueued late.

= 1.49.4 =
* Fix: shortcodes placed on builder pages where they are the only plugin
  element now reliably load their assets — late-enqueued CSS/JS are force-printed
  in the footer if the theme/builder skips WordPress's automatic late output.
  This fixes the newsroom carousel showing empty/unstyled on pages that don't
  also contain another plugin shortcode.
* Newsroom carousel: card widths (3.5 desktop / 1.5 mobile) use a fixed gap
  instead of an inherited CSS variable, so the count holds inside page builders.

= 1.49.3 =
* Newsroom carousel: fixed desktop showing too many cards — the per-view count
  (3.5 desktop / 1.5 mobile) is now set directly on the cells so it holds inside
  page builders. Section title, card titles/meta and the View More link now
  inherit the active theme font instead of the plugin's bundled fonts.

= 1.49.2 =
* Newsroom carousel: now shows 3.5 cards per view on desktop and 1.5 on mobile
  (peek of the next). Card titles reduced in size and clamped to 3 lines so they
  no longer overflow and clip at the top of the tile. "View More" changed from a
  button to a right-aligned text link with an arrow.

= 1.49.1 =
* Fix: the frontend CSS/JS now load whenever any plugin shortcode actually
  renders, not only when the shortcode is found in the page's post_content.
  This fixes the newsroom carousel (and other shortcodes) rendering unstyled
  when placed inside a page-builder module or widget on the homepage.

= 1.49.0 =
* Newsroom: new [CLEB_newsroom_carousel] shortcode for the homepage — shows the
  6 most recent articles in a carousel (4 per view on desktop, 1 on mobile),
  with a section title above (default "Our Stars' Updates") and a "View More"
  button below linking to the full newsroom page (auto-detected, or set via
  more_url). Optional attributes: title, count, more_url, more_label.

= 1.48.0 =
* Newsroom: paginated the article grid — 12 per page on desktop, 10 on mobile,
  with the rest on further pages. Pagination is client-side so it works with the
  "Filter by artist" control and adjusts the per-page count on resize. Pager
  styled to match the #999999 accent.

= 1.47.0 =
* Social Media Card: added a small "Read more: ilikeagency.co/newsroom" line
  with a link icon beneath the headline.

= 1.46.0 =
* Social Media Card: agency logo reduced 35% (208px to 135px wide).

= 1.45.0 =
* Social Media Card: added a "Press Release" eyebrow (document icon + label)
  above the accent line, at the top of the bottom text block.

= 1.44.0 =
* Social Media Card: redesigned to match the reference layout — the editorial
  frame is removed, the photo stays in full colour (no grayscale), the agency
  logo sits top-left, and the location and date now render as thin outlined
  pills above the headline (they wrap to a second row if long). The headline
  keeps its auto-fit sizing.
* Newsroom: the "Filter by artist" control (label, dropdown border, chevron,
  hover/focus states) recoloured from gold to #999999.

= 1.43.0 =
* Social Media Card: title size reduced ~40%. The auto-fit ceiling dropped from
  86px to 52px (and the floor from 40px to 30px, up to 5 lines), so the headline
  renders noticeably smaller while still auto-fitting and never truncating.

= 1.42.0 =
* Article page: full-container width is now enforced at runtime by JavaScript,
  not just CSS. On load, resize and orientation change it walks every ancestor
  of the article up to <body>, clears their max-width/width/padding/float, then
  pins the article to the exact viewport width flush to the left edge — so it is
  truly edge-to-edge on any theme, regardless of how the theme wraps content.
* Article page: the mobile language switch is hardened. Its click handler is
  bound once via delegation on the document as soon as the script runs (guarded
  against double-binding), and every init is wrapped so a failure in another
  module can no longer leave the switch unbound. Combined with the gradient now
  ignoring taps, the EN/ع toggle responds reliably on touch.
* Social Media Card: the celebrity-name eyebrow is removed (the name already
  appears in the title). The title now auto-fits — it scales down and wraps to
  as many lines as needed so the full headline always shows; it is never
  truncated to three lines as before.

= 1.41.0 =
* Article page (desktop): full-container width hardened. The 100vw breakout was
  being constrained by the theme's content wrappers (e.g. Salient's
  #ajax-content-wrap / .container max-width); the wrapper chain is now forced to
  full width on the news page in both the stylesheet and the inline critical CSS,
  so the hero and article truly fill the viewport.
* Article page (desktop): taller hero banner — min-height raised to
  clamp(660px, 86vh, 1000px). The desktop height bump was missing from the inline
  critical CSS (which overrides the stylesheet), so it is now emitted there too.
* Article page (mobile): language switch now responds to taps. The hero gradient
  overlay was intercepting touches; it is now pointer-events:none, the title/info
  and switch sit above it (z-index), the buttons get touch-action:manipulation,
  and the switch handler is delegated on the document so taps always register.

= 1.40.0 =
* Article page: the English title and body now inherit the active theme's font
  instead of being forced to Cormorant/Inter. The inline critical CSS was
  hardcoding the fonts and overriding the stylesheet; both layers now use the
  theme font. Arabic keeps Cairo/Tajawal (Latin theme fonts don't cover Arabic).
* Article page: full-bleed container hardened — width:100vw with horizontal
  overflow clipped in both the stylesheet and the inline critical CSS, so the
  layout can no longer spill past the viewport on mobile.
* Article page (mobile): fixed the corrupted layout where the date/location,
  by-line and language switch rendered twice (once over the hero, once below).
  The below-hero "subbar" copy is removed; the info + switch now render as a
  single block over the hero at every breakpoint. The responsive rules are also
  emitted in the inline critical CSS so a stale cached stylesheet can't
  reintroduce the double render.
* Article page: language switch sits under the title with the article info on
  the left and the EN/ع toggle pinned to the right (flips in Arabic), on both
  mobile and desktop.
* Admin: the Arabic body editor is now the full editor with text-direction
  (RTL/LTR) buttons and left/center/right/justify alignment, defaulting to RTL.

= 1.39.0 =
* Article page (desktop): taller hero banner showing only the title over the
  gradient; date/location, celebrity by-line and the language switch move to a bar
  under the banner — info on one side, switch on the other (flips for Arabic).
* Article page (mobile): unchanged layout, but the language switch now sits beside
  the info under the title and works reliably.
* Article page now fills the full container width on desktop (removed the
  viewport-bleed that could overflow).
* Fonts: the article title and body now inherit the active theme font (Arabic keeps
  a proper Arabic face).

= 1.38.1 =
* Newsroom bilingual: the EN / ع language switch now also appears on the standalone
  single-article page (not only the popup), and the toggle script runs there too
  (previously it only initialised on pages with the newsroom list).

= 1.38.0 =
* Newsroom: bilingual articles. English stays the main version (post title + main
  editor); a new "Arabic Version" box adds an Arabic title + body. On the article
  popup a small EN / ع switch flips the title and body in place with no reload. The
  image gallery, date and location are shared.
* Social Media Card: now uses the layout — celebrity name (eyebrow) / news title
  (hero) / date • location — and always uses the English version. Updated the agency
  logo to the current file.

= 1.37.0 =
* Newsroom Social Media Card: redesigned to a portrait poster — full photo, thin
  editorial frame, white logo top-left, bottom-to-black gradient, and a bottom text
  block (accent line, headline eyebrow, date/location, and the talent's name in large
  bold). Keeps automatic Arabic (RTL) / English (LTR) alignment.
* New: upload a custom image for the social card (overrides the featured image),
  right in the Social Media Card box.

= 1.36.6 =
* Roster page: stronger vignette on the talent card images, and images now display
  desaturated/dimmed by default and animate to full colour on hover.

= 1.36.5 =
* Accent: plugin default is now neutral grey (#999999) to match the theme, so the
  roster/grid/profile no longer show gold by default. The Lead Talent badge and card
  hover borders now follow the accent setting too. Still editable in Settings →
  Appearance.

= 1.36.4 =
* Roster page: added a subtle vignette to the talent card images (darkened edges
  fading to a clear centre) for a more cinematic, premium look; deepens slightly on
  hover.

= 1.36.3 =
* Settings: the Accent colour picker now shows the actual active colour (it was
  showing an empty value while the site used the champagne default, which looked
  like a mismatch). Corrected the help text. Removed a one-time migration that
  could overwrite a deliberately-chosen accent. The plugin accent controls plugin
  output (roster, grid, carousel, profile); theme elements are set by the theme.

= 1.36.2 =
* Roster page: fixed cards overflowing / spilling off-screen on desktop. Removed the
  fragile JavaScript that resized the roster to the viewport (which miscalculated on
  some themes and pushed the grid wider than the screen). The roster now cleanly
  fills the full width of its container, with four cards per row on desktop.

= 1.36.1 =
* Roster page: desktop now shows exactly four cards per row (three on tablet, two on
  mobile) instead of a variable auto-fit count.
* Accent: a saved old grey (#999999) accent is migrated once to the brand champagne
  (#b9975b) so the roster gold shows correctly; still editable in Settings → Appearance.

= 1.36.0 =
* Roster page (desktop): full-width grid now uses smaller auto-fitting columns, so
  cards stay a sensible size and fill the width evenly instead of a few oversized ones.
* Accent colour is now managed from Settings → Appearance and applies to the roster
  (Lead Talent badge, hover, name links) as well as the general gold. Default is the
  brand champagne #b9975b; removed the old logic that forced gold to grey.

= 1.35.5 =
* Talent Works Archive: redesigned the Ramadan badge to match the site's dark /
  champagne identity — a dark-glass gold pill with a crescent, instead of the
  off-brand green.
* Roster page: reduced the large side padding so the grid spans the full width of
  its container.

= 1.35.4 =
* Roster page: really fixed two-per-row on mobile. The grid class was renamed away
  from the "grid" substring (the active theme collapses any [class*="grid"] to one
  column on mobile), made mobile-first, and the optional column count now applies
  on desktop only — so phones always show two cards per row.

= 1.35.3 =
* Talent Works Archive (mobile): the type and Ramadan badges are now compact so
  they no longer overlap on the narrow two-up cards.
* Talent Works Archive (mobile): the search/filter panel is now collapsed behind a
  "Search & filter" toggle, so the productions show immediately instead of the
  filters filling the first screen. Filters stay always-visible on desktop.

= 1.35.2 =
* Roster page (Our Roster): now shows two cards per row on mobile instead of one
  (the grid was explicitly set to a single column below 560px).

= 1.35.1 =
* Talent Works Archive: fixed the mobile grid showing one card per row. The card
  grid is now mobile-first (two columns as the base), scoped for higher specificity,
  and its class was renamed to avoid theme rules that target [class*="grid"] on
  mobile. Two per row on phones, three on tablets, four on desktop.

= 1.35.0 =
* Talent Works Archive: now inherits the active theme automatically — fonts and
  text colours already inherit, and the card surface + poster gradient are now
  derived from the actual page background at runtime, so the archive matches any
  theme's colour scheme (light or dark) instead of a fixed shade. Accent still has
  default / inherit-theme / custom modes.
* Mobile grid reinforced at two cards per row (down to the smallest screens).

= 1.34.1 =
* Manage app (PWA): the home-screen icon tags now also fire on page-builder pages
  (Elementor/Bricks/etc.) by remembering the app's page, and added sized Apple
  touch icon variants — fixes the custom icon not appearing on install.

= 1.34.0 =
* Manage app (PWA): add-to-home-screen now uses a custom icon and app name — set
  them under Settings → Appearance (falls back to the brand logo). Adds a proper
  web-app manifest + Apple touch icon so it installs as a standalone app.
* Manage app: the accent colour from Settings is now actually applied to the app
  (buttons, highlights) — previously it was saved but ignored.
* Manage app: now inherits the active theme's font instead of forcing Inter; the
  accent colour remains the custom override.

= 1.33.3 =
* Talent Works Archive: tightened the space above the card info (title/cast) so it
  sits closer to the poster fade — cleaner, less empty dark space.

= 1.33.2 =
* Talent Works Archive: cards now use a #121212 panel with a bottom-up gradient
  over each poster that melts into the info area for a seamless, cinematic look.

= 1.33.1 =
* Talent Works Archive: the card info area (title + cast) no longer blends into the
  page — each card now has a subtle unifying panel (faint surface, thin border,
  rounded) that groups the poster with its text while staying light and elegant.

= 1.33.0 =
* Talent Works Archive: redesigned the cards to be poster-forward and more compact
  — removed the boxy card chrome and the redundant year/type line (year is the
  section heading, type is the poster badge), so posters lead and cards take less
  space.
* Year sections are now collapsible. The current year is expanded by default;
  older years are collapsed and open on click (all years open automatically while
  a filter or search is active).
* Every year now renders on one page — removed the "Load more" button (collapsed
  years defer their images, so it stays fast).

= 1.32.0 =
* Talent Works Archive: Release Date is now a Release Month picker (month only).
  Productions sort by year, then by month, newest first.
* New production type: Mini Series.
* New "Ramadan project" toggle: shows a high-contrast "Ramadan" badge (crescent +
  deep-green gradient, white text, shadow) over the poster, readable on any image.
* Accent colour is no longer fixed: three modes — plugin default (soft grey
  #999999 on white), inherit the theme's accent, or a custom colour.

= 1.31.0 =
* Talent Works Archive: productions are now grouped by release year (current year
  first, going back), each year with its own heading and card grid. Removed the
  Sort control (ordering is always newest-year-first). Load More reveals older
  years without ever splitting a year across a boundary.
* Fixed the custom accent colour not applying on the front end (the scoped accent
  style is now printed with the archive, so it works with any theme/page builder).
  The accent now also shows on the year headings and count pills.

= 1.30.1 =
* Talent Works Archive: fixed the artist filter dropdown rendering transparently
  and overlapping the fields below it (now a solid, theme-adaptive panel).
* Refined the filter fields (subtle fill, cleaner borders, focus ring).
* Desktop grid now shows 4 cards per row (4 / 3 / 2 across desktop, laptop, mobile).

= 1.30.0 =
* Talent Works Archive: artist names now link to the celebrity's main profile page
  instead of their Smart Link. Private/locked profiles remain plain text.

= 1.29.0 =
* New module: Talent Works Archive. A cinematic, streaming-style archive of every
  production (TV series, film, theatre, TV show, commercial, music video, other)
  managed by the agency — for both current and former talent.
  - New "Talent Works" admin menu. Each production has a poster (featured image),
    type, release year/date, and an unlimited, drag-sortable list of artists, each
    a roster artist (auto-links to their profile) or a former/custom name, with a
    character per artist.
  - Shortcode [ilike_works_archive]: responsive grid (3 / 2 / 2), lazy-loaded
    posters, AJAX filtering (searchable artist, year, type, keyword), sorting
    (newest / oldest / A–Z / Z–A) and Load More — no page reloads.
  - Theme-aware by design: inherits the active theme's fonts, colours and spacing;
    namespaced CSS; assets load only on pages using the shortcode. Optional
    Appearance settings to override just the plugin's accent colour.
  - Extensible data model ready for director, channel, trailer, gallery, etc.

= 1.28.0 =
* Newsroom: the “Filter by artist” control now sits on the left and is restyled to
  match the dark site design — champagne label, dark pill dropdown with a custom
  chevron, and a readable options list (replaces the plain white OS dropdown).

= 1.27.0 =
* Rate Card: customers can now add extra items. Adding a service reveals a clear
  “Extra items · X each” stepper, the per-extra price is shown, and extras are
  included in the live total and the copied summary.
* Clearer pricing font for better readability.
* Currency switch moved next to “View My Profile” (no longer over the logo).
* Fixed “Request an Offer” on mobile (robust copy that works on iOS/Android) and
  the popup now always confirms.
* Clearer flow: a 2-step guide (1 — Request an Offer copies your selection; 2 —
  Contact My Management to paste & send), plus a hint under the button and a
  friendly prompt if nothing is selected yet.

= 1.26.0 =
* Rate Card: USD is now a visitor-facing toggle instead of a forced conversion.
  Cards display in EGP by default; when the USD switch is enabled in settings, an
  EGP/USD toggle appears on the card and the visitor chooses. All prices, live
  totals and the copied summary update instantly. Conversion uses the configured
  exchange rate + markup; percentage options are unaffected.

= 1.25.0 =
* Rate Card: optional USD display (Settings → Rate Card). Set the EGP↔USD rate and
  an optional % markup; every card then shows in USD, live totals included.
  Percentage options (e.g. rush) are unaffected.
* Rate Card: added a “View My Profile” button under the intro linking to the
  talent’s Smart Link; intro text is now justified.
* Rate Card sharing: page title now reads “{Name} Rate Card | {Agency}”, with the
  talent’s photo as the social preview image and their bio’s first paragraph as
  the description.
* Calendar Sync: every event now includes two automatic reminders — 1 day before
  and 1 hour before.

= 1.24.1 =
* Rate card polish: the intro paragraph now spans the full content width; fixed
  the Usage/Exclusivity options where the label and description ran together;
  and replaced the bulky floating summary with a slim, tap-to-expand bottom bar
  that no longer covers the page on desktop or mobile.

= 1.24.0 =
* New: Calendar Sync — every celebrity gets a private, subscribable calendar
  feed (Calendar Sync box on the profile). One link works on Apple Calendar,
  Google Calendar, Outlook and any app that supports ICS subscriptions.
  * Auto-syncs: projects (shoot days), schedule appointments and accepted
    (in-progress) requests appear automatically and update on their own.
  * Timezone-correct: uses a real Africa/Cairo timezone with full daylight-saving
    support (built from the timezone database, not fixed offsets), so events show
    in each device’s local time and DST is handled automatically.
  * Copy links for Apple/Outlook (webcal) and Google (https), plus a “Reset link”
    control to revoke and reissue a talent’s feed. Also available in the manage app.
* New: the All Celebrities admin list now shows each profile’s photo next to the name.

= 1.23.1 =
* Fixed: the agency logo now shows on the Smart Link and the Rate Card pages.
  Added a single “Brand logo” field (Settings → Appearance) used by both,
  defaulting to the current iLike logo. Replaces the old broken logo path.
* Settings page reorganised into tabs (Appearance, Roster, Contracts, Rate Card,
  Shortcodes) for easier management. All settings still save together.

= 1.23.0 =
* Rebuilt: Rate Cards are now their own type under Celebrities → Rate Cards.
  Create one card per talent and assign it — all pricing, sections, terms and
  passwords now live on the card, not in global settings.
* Unlimited sections: build the card as titled sections (Services, Event
  Coverage, PR Packages…) exactly like a printed rate sheet, each with its own
  reorderable services. Drag to reorder sections and items.
* Clean URLs: each card is served at /rate/{talentname} (e.g. /rate/daliamostafa),
  derived from the talent’s name — no more /rate/?cid=.
* Branded password gate: the access page now shows the agency logo, the talent’s
  photo (or monogram) and name, on the full Casting-Book styling.
* Smart Link: an optional “View Rate Card” button now appears on a talent’s
  smart link when they have an enabled card (still password-protected).
* Manage app (PWA): each profile now has “Copy link + password” for its rate
  card, for managers.
* Settings trimmed to the shared commons only (contact link + button labels).

= 1.22.0 =
* New: Rate Card — a private, password-gated quotation builder for each talent.
  * Per-celebrity meta box: enable toggle, access password (with the private
    /rate/?cid= link), audience (handle + follower/subscriber count per platform),
    and an unlimited, reorderable Services list (name, description, included
    quantity, base price, additional-per-item price, platform availability).
  * Interactive builder: clients add services and adjust quantities; the total
    recalculates live (e.g. 4 Stories = 3 included at base + 1 extra). Usage
    rights, exclusivity, rush booking, travel fee apply as % or fixed adjustments.
  * Summary panel with running total, a Copy-Summary action that copies a
    formatted estimate and shows a "Contact My Management" popup deep-linking to
    your contact method.
  * Global Settings → Rate Card: currency, intro, editable section titles,
    show/hide any section, enabled platforms, usage/exclusivity option catalogs,
    rush fee, travel policy, notes, terms, and CTA/contact labels + link.
  * Standalone noindex page at /rate/ with per-talent password gate (12-hour
    unlock cookie). Nothing is shown until the correct password is entered.

= 1.21.4 =
* Fix: Roster still blank on some phones — the desktop-only breakout now decides
  desktop vs mobile with matchMedia (true CSS pixels) instead of a width read
  that some mobile browsers report in device pixels, plus extra sanity guards.
  On phones the breakout never runs, so the roster renders in normal flow.

= 1.21.3 =
* Fix: Roster was blank on mobile — the full-width breakout is now applied only
  on wider screens (>=768px) and wrapped defensively, so phones render normally.
* Fix: Content no longer touches the page edges — the roster now keeps a
  comfortable side gutter that scales up on large screens.
* New: The roster header text (small label, title, intro paragraph) is now
  editable under Celebrities → Settings → "Roster header", so it can be changed
  without touching the shortcode. Shortcode attributes still override.

= 1.21.2 =
* Fix: The roster now breaks out to the full viewport width instead of being
  trapped in the theme's narrow, left-aligned content column (which left a large
  empty area on the right). Because the theme column is left-aligned, the
  breakout is measured per-page so it works regardless of the container.
* The grid now auto-fills the row, so wide screens show more cards at a sensible
  size rather than three stretched ones. New shortcode options: full="no" to
  disable the breakout, and cols="3" (any number) to pin a fixed column count.

= 1.21.1 =
* Roster refinements to match the prototype: "Our Stars" title enlarged to full
  Bodoni display scale; monogram initials now render in a metallic silver
  gradient (champagne is reserved for the Lead Talent badge); a small target-dot
  marker sits beside the intro; cards are slightly taller.
* Order is random again on every refresh (orderby="rand" default; lead|title
  still available).

= 1.21.0 =
* New: [CLEB_celebrities] roster redesigned to the Casting-Book direction —
  true black, champagne accent (#b9975b), Bodoni Moda titles/names, Space Mono
  labels. Header ("Our Stars" + intro), All / Actors / Rising Stars / Music &
  Hosts filter pills, and a 3-up grid (2 on tablet, 1 on phone).
* New: Talent without a headshot show a Bodoni monogram (initials) instead of a
  blank tile; name sits in Bodoni with the role in Space Mono beneath.
* New: Celebrity editor gains a "Roster category" (Actors / Rising Stars /
  Music & Hosts, or Auto-guess from the role) and a "Lead talent" toggle that
  shows a champagne "Lead Talent" badge.
* Shortcode options: header, eyebrow, title, intro, filters, orderby
  (lead | title | rand). The homepage carousel is unchanged.

= 1.20.1 =
* Fix: Opening a contract from the app showed "Contract not found". The admin
  download link was HTML-escaping the "&" in the URL, which broke the contract
  ID when the app opened it. Links are now built with raw separators.
* Fix: In the app contract list, the name and the line beneath it were touching;
  they now stack with proper spacing (also tidies the Requests list).
* Add: A clear "please sign in" message if a download link is opened without an
  active session (some in-app browsers drop the login cookie).

= 1.20.0 =
* New: Contracts in the web app (Drop 3). A Contracts tab now appears in both
  dashboards. Admins/managers see every contract with talent + status filters
  and can download signed PDFs (or copy the signing link for pending ones).
  Each talent sees only their own contracts in their Work Center app — they can
  open a pending contract to review and sign it, and download it once signed.

= 1.19.0 =
* Fix: Signed PDF is now built as proper A4 pages. Previously the whole contract
  was captured as one tall image and sliced, which ballooned to many pages and
  cut lines across page breaks. It now flows the contract into real A4 pages with
  clean breaks and compact print typography, so it lands at roughly the same
  length as the source document (about 3 pages).
* New: Contract logo. Settings -> Online Contracts -> Contract logo. The logo
  prints at the top-left of every PDF page and at the top of the signing page.
  Upload a dark/colour logo that reads on white (not the white header logo).

= 1.18.9 =
* Fix: Agency signature picker in Settings now opens the media library (it was
  bailing out before the media scripts had loaded).
* New: Commission Percentages on every contract — set a percentage for each of:
  Artistic Works (Agency-Sourced), Artistic Works (Independently Sourced),
  Commercial & Advertising (Agency-Sourced), Commercial & Advertising
  (Independently Sourced). Use {{commission_table}} in the template to print all
  four, or {{rate_KEY}} for one (e.g. {{rate_artistic_agency}}). If the template
  never references them, the table is appended automatically.
* New: Signing page (Drop 2). The contract signing link now resolves at
  /sign/?cid=&token= with no page to create — the plugin owns that URL. The
  actor fills the highlighted fields, draws a signature, ticks consent, and
  submits. A PDF is generated in their browser (Arabic-safe) with both their
  signature and the agency signature, saved to a web-protected folder, and
  emailed to them automatically. Signed date + IP are recorded. [CLEB_sign] also
  works if you prefer to place it on your own page.

= 1.18.8 =
* New: Online Contracts — foundation (Drop 1 of 3).
  - Contract Templates (Celebrities -> Contract Templates): write the contract
    in the editor using {{placeholders}}, and define the actor's fill-in fields
    (label, key, type, required) in a Form Fields manager. Starter fields for
    Full Name, National ID / Passport and Email are pre-filled.
  - Contracts (Celebrities -> Contracts): pick a template, assign a talent, and
    Publish to get a unique signing link to send the actor.
  - Admin dashboard: Talent / Template / Status / Signed / PDF columns, plus
    Pending|Signed and per-talent filters, and gated PDF download.
  - Settings -> Online Contracts: upload the agency signature once, and set the
    email From / Subject / Body and the signing-page URL.
  - Signed PDFs are stored in a web-protected uploads folder and only served
    through a permission-checked download.
* Next (Drop 2): the actor signing page ([CLEB_sign]) with the signature pad,
  browser-side PDF with both signatures, and the automatic signed-PDF email.

= 1.18.7 =
* New: Newsroom [CLEB_newsroom] redesigned as photo cards - each article is a
  rounded image tile with a dark gradient and the headline + "Location / Date"
  burned into the bottom, matching the approved design. Two cards per row on
  desktop, one per row on mobile.
* New: Per-article RTL/LTR. Each card headline auto-aligns right for Arabic
  titles and left for English, based on the article's own language.
* Filter by artist moved to a right-aligned bar above the grid; the dropdown
  still filters the cards live. The celebrity profile page news list is
  unchanged (it keeps its own compact layout).

= 1.18.6 =
* Fix: Hero top-gap closer rewritten to be theme-agnostic. Instead of trying to
  identify the site header (which kept failing on different setups), it now walks
  up from the hero and strips top margin/padding off every ancestor element, plus
  the bottom margin of anything sitting directly above it. No theme detection
  required, so the gap is removed regardless of header markup. The precise
  header-pin is kept only as a finishing pass.

= 1.18.5 =
* Fix: The hero top-gap fix now targets the actual Elementor header
  (.elementor-location-header) - the site runs on Elementor, not the theme the
  earlier selectors assumed, so the script couldn't find the header and did
  nothing. It now measures the real header and pins the hero flush against it,
  with several retries for Elementor's late layout.

= 1.18.4 =
* Fix: White space above the article hero removed for real. Replaced the
  :has() approach (unsupported in some in-app browsers) with body-class wrapper
  resets plus a cache-proof inline script that measures the site header and
  pins the hero flush against it (works whether the header is fixed or static).
* Change: Hero now spans the full viewport width on desktop (theme side padding
  on the content wrapper is cleared on article pages).
* Change: Gallery is now a clean borderless grid - 3 across on desktop, 2 on
  mobile (no more one-per-row), images still open in the lightbox.
* Change: Articles in the Celebrities/newsroom lists now link straight to the
  full article page instead of opening a pop-up.

= 1.18.3 =
* Fix: Article hero image was overflowing its box and the body text rendered on
  top of it (looked like a duplicated image). The hero now clips its image
  (overflow hidden) and the image is pinned to fill the hero exactly.

= 1.18.2 =
* Fix: Removed the space above the article hero for real - the theme's content
  wrapper (WPBakery/Salient #ajax-content-wrap / .container-wrap / #content) had
  top padding that negative margins couldn't escape; it's now zeroed directly
  and inlined so cache can't restore it.
* Change: Title is back over the hero image (with the bottom gradient), at a
  smaller size. The celebrity name now sits beneath the date as a clickable
  by-line linking to the profile.
* Change: Gallery redesigned as clean 2-up 16:9 film-still tiles (1 column on
  mobile); images still open in the lightbox.

= 1.18.1 =
* Fix: Article hero now renders as a true full-width image on desktop AND mobile
  (was vanishing on mobile and trapped in a column on desktop). Top white space
  and the black gap below the hero are removed - the hero sizes to its image and
  the headline now sits in a readable centered column below it.
* Change: Article title size reduced; body/title use a comfortable column with
  left/right margins for readability.
* Change: Celebrity name moved off the hero into a by-line and is now a clickable
  link to the celebrity's profile.
* Fix: Article gallery images now open in the built-in lightbox (with prev/next
  and zoom) instead of a new browser tab.
* Hardening: Critical article CSS is emitted inline so a stale CDN/page cache
  can't break the layout.

= 1.18.0 =
* Fix: Article page hero now spans the full viewport width on desktop and
  mobile (it was trapped inside the theme's centered content column), and the
  empty gap below the title is gone - the hero is sized to its image and the
  title/date sit pinned at the bottom. Top spacing tightened.
* New: Separate Desktop and Mobile hero images per article (Hero Images box).
  Use a wide crop for desktop and a tall/portrait crop for mobile; mobile falls
  back to the desktop image, and both fall back to the Featured Image.

= 1.17.0 =
* New: Dedicated News Article page. Opening an article from a Smart Link now
  lands on a proper layout - full hero header image, title, publication date and
  location beneath it, the article body, and any gallery media or related links
  configured in the back-end, shown automatically.
* New: Automatic text direction. Article titles and body content render
  right-to-left for Arabic and left-to-right for English - on the new article
  page and in the profile newsroom pop-up (desktop and mobile).
* Change: Gold has been retired across the whole interface. The design is now
  black background, white text, and neutral grey (#999999) for borders,
  highlights and accents - grid, carousel, profile, Manage app, Smart Link and
  the news pages. Any previously saved gold accent is mapped to grey.
* Change: Social Media Card now uses Raleway for English and Cairo for Arabic,
  with the title and meta text reduced by ~40 percent.

= 1.16.0 =
* New: Social Media Card Generator in the Newsroom. Each article's edit screen
  now has a "Social Media Card" box that builds a 3:4 PNG from the featured
  image with a bottom-to-top gradient, the agency logo (top-left, white), the
  Location / Date line and the title. The title and meta align left for English
  and right for Arabic automatically, and the date is shown in the matching
  language. One-click Download PNG.
* New: Smart Link "Latest News" section. Below the roles/experience list, the
  Smart Link now shows the celebrity's most recent published article - a small
  square thumbnail, the title (aligned RTL/LTR by the article's language) and
  the publication date. Tapping it opens the full article.

= 1.15.0 =
* New: Smart Link pages show a "View Full Profile" button linking to the
  celebrity's main profile page. It appears only when the profile is public
  (i.e. "Keep profile private" is unchecked), so private profiles stay hidden.

= 1.14.1 =
* Fix: Career History CSV import put the year into the Type field after the Type
  column was added. CSV columns are now mapped by name, so Project, Role, Year
  land correctly. An optional 4th "Type" column is now supported, and the sample
  CSV shows it.

= 1.14.0 =
* Change: Celebrity logins are now Work Center only. A logged-in celebrity sees
  their upcoming/past Schedule and Projects and can create new ones - nothing
  else. The "My Profile" tab and all profile editing have been removed from the
  celebrity view, and profile edits/photo uploads are now restricted to managers
  on the server as well.

= 1.13.0 =
* New: At-a-glance Schedule + Projects everywhere.
  - WordPress dashboard widget now shows totals for Schedule, Projects and new
    Requests, plus this month's upcoming Schedule entries and Projects (each
    linking to the item), alongside quick links.
  - The Manage app dashboard now shows Schedule and Projects totals and the same
    "This month" Schedule and Projects lists; tap any item to jump to that
    celebrity's Work Center.

= 1.12.0 =
* Change: Celebrity App Login now GENERATES a login link, username and password
  on the spot (no email). Tick "Generate login & password", Update, then copy the
  three fields and send them to the celebrity. "Reset password" regenerates it;
  "Remove access" revokes it.
* New: Requests - a per-celebrity booking/contact inbox.
  - Public form via [CLEB_request] (use celb="ID" to fix it to one celebrity, or
    omit for a chooser). Honeypot-protected.
  - Admin inbox under Celebrities -> Requests: filter by celebrity, filter by
    status, sort by date, with contact details and a status workflow
    (New / In progress / Closed).
  - New "Requests" tab in the Manage app: filter by celebrity, sort newest/oldest,
    open a request to see full details, tap to email/call/WhatsApp, change status.
  - WordPress dashboard widget "iLike - Incoming Requests": new/total counts and
    the latest requests at a glance.

= 1.11.0 =
* Fix/New: Full Work Center backend inside wp-admin. Projects and Schedule now
  have complete, editable screens (previously they opened as empty title-only
  posts with nowhere to enter data).
  - Project editor: celebrity, type (Movie/TV Series/TV Show/Commercial/Campaign
    /Other...), production company, status, start/end dates, notes; repeatable
    shooting locations (with Maps links); per-day shooting log with call times,
    location, notes and a Completed toggle that auto-counts total/completed/
    upcoming days; attachments via the WordPress media library (PDF, scripts,
    call sheets, images, videos, docs); and a saved update history.
  - Schedule editor: celebrity, type (incl. Personal/Unavailable), date, time,
    duration, location (Maps), description, prep notes, reminder, attachments,
    and an "Add to calendar" button.
  - Proper menu labels (Add Project / Add Schedule Entry) and list columns
    showing celebrity, status/type, dates and shooting-day counts.
  - The mobile/web app and wp-admin share the same data.

= 1.10.0 =
* New: Celebrity self-service logins. Each celebrity can have their own account
  to log into the app and manage ONLY their own Work Center, schedule, projects,
  and profile - they never see other celebrities or plugin settings.
  - New "Celebrity" user role (app-only; kept out of wp-admin and redirected to
    the Manage app on login).
  - "Celebrity App Login" box on each profile (Celebrities editor): create a new
    login by email or link an existing user, with a one-time password-setup link
    to send them. Unlink to revoke access at any time.
  - All Work Center, schedule, project, and profile REST endpoints now enforce
    per-celebrity ownership: a celebrity account can only read or change its own
    data; managers (your team) keep full access to everyone.
  - On login, celebrities land directly in their Work Center; managers get the
    full dashboard.

= 1.9.0 =
* New: Work Center - a per-celebrity hub inside the Manage app (mobile + web)
  for managing professional life as a single source of truth. Open it from any
  profile via "Open Work Center".
  - Dashboard: today's agenda, upcoming/past projects, upcoming/past schedule,
    and quick-create buttons.
  - Projects (Movies, TV Series, TV Shows, Commercials, Campaigns, Other) with
    production company, status, start/end dates, shooting locations (each a
    tappable Maps link), notes, and per-day shooting logs that auto-calculate
    total / completed / upcoming shooting days for verifiable work history.
  - Attachments on projects and schedule entries (call sheets, scripts, PDFs,
    images, videos, documents) via the device.
  - Update history: every save is logged chronologically, with optional notes,
    so changes are preserved over time.
  - Schedule (Interviews, TV appearances, Meetings, Photoshoots, Events, Brand
    campaigns, Personal, plus Unavailable blocks to prevent booking conflicts)
    with date, time, duration, location, description, prep notes, attachments,
    and a reminder via device-native "Add to calendar" (.ics with alarm).
  - Agency logo footer linking to the agency site on every Work Center screen.

= 1.8.1 =
* Change: The Manage app now opens full-screen over the site (no theme header,
  footer, or white margins) for a proper app feel, and resists theme CSS so its
  fields and buttons render correctly.

= 1.8.0 =
* New: Mobile Manage app via [CLEB_manage]. A login-protected, phone-friendly
  dashboard to preview, edit, and create profiles, upload a profile photo,
  manage Smart Links, and (for admins) edit plugin settings - without opening
  the WordPress admin. Secured by the site's own login plus per-user capability
  checks and a REST nonce; no separate password system.

= 1.7.5 =
* New: More project type suggestions (TV Show, Podcast, Advertisement, Campaign,
  Presenting, Hosting, Radio, Voice Over, Brand Ambassador, Endorsement, etc.).
* New: Desktop filter on the Career section - browse a celebrity's roles by
  project type via pill buttons (shown only when a profile has 2+ types).

= 1.7.4 =
* Fix: A published celebrity's Smart Link now always loads, even if the profile
  is marked private (the private toggle still hides the on-site profile page and
  grid link, but the deliberately-shared Smart Link stays live).
* Fix: Added a direct database fallback when resolving a Smart Link slug, so a
  cached/stale link map can no longer cause a 404.

= 1.7.3 =
* New: Career entries now have a project Type (TV Series, Movie, Play... free
  text with suggestions) and a Special appearance checkbox. Both show on the
  profile page and on the Smart Link's Recent & Upcoming Roles, and are
  available in the self-submission portal.

= 1.7.2 =
* Fix: Smart link now routes via parse_request, so /name/ resolves even when a
  host caches rewrite rules and never reaches the hosted-image fallback.
* New: "Recent & Upcoming Roles" section on the smart link, listing Coming Soon
  projects and roles dated to the current year.

= 1.7.1 =
* Fix: Smart links now work immediately after a plugin update - rewrite rules
  auto-flush on version change, so /name/ no longer falls through to WordPress's
  404 URL-guess (which could land on a hosted image). Added a safety filter so a
  smart link slug can never be redirected to an attachment.

= 1.7.0 =
* New: Smart Link. Every published celebrity gets a clean shareable landing page
  at /name/ (e.g. /daliamostafa/) - a premium black Linktree-style page with the
  profile photo, name, first bio paragraph, their social icons, a Contact My
  Management section (iLike Agency socials), a Let's Talk button, and the iLike
  logo. The page reads live from the profile, so it always stays in sync. The
  link (and a Copy button) appears on the celebrity edit screen and in the list.

= 1.6.5 =
* Change: Card scrim now adapts to card width. Wide cards (desktop grid) get a
  soft, light fade; narrow cards (mobile grid, desktop carousel) keep the strong
  scrim where two-line names need it.

= 1.6.4 =
* Fix: Card name/role now sit on a dark scrim that hugs the text and grows with
  it, so two-line names or roles no longer float over the artist's face. Type
  tightened slightly. Emitted inline too, so it applies under a stale CSS cache.

= 1.6.3 =
* Fix: The carousel "View all" button and the mobile swipe layout are now also
  emitted as inline CSS, so they render correctly even when a CDN or page cache
  is still serving an older copy of the stylesheet.

= 1.6.2 =
* Fix: Card name/role now scale to the card width (container units), so the
  carousel's narrower cards no longer show oversized text overlapping faces and
  match the grid's proportions.
* Fix: Stronger bottom gradient on cards for reliable text contrast over light
  photos.
* Change: On mobile the carousel now shows large swipeable cards (with a peek of
  the next) instead of a small 2x2 block.
* New: Optional "View all" button under the carousel (Settings -> label + link,
  default the Our Celebrities page) or via [CLEB_celebrities_carousel viewall="URL"
  viewall_label="..."].

= 1.6.1 =
* New: "Keep profile private" toggle per celebrity. Private profiles still show
  in the grid and carousel (photo, name, role / nationality) but the card is not
  clickable and the profile page is hidden from visitors (still viewable by
  logged-in editors). A Private/Public indicator was added to the list.

= 1.6.0 =
* New: Global newsroom page via [CLEB_newsroom] — all articles across every
  celebrity, with a small "Filter by artist" dropdown on the left.
* New: Dedicated Header Image field for articles (popup banner), separate from
  the Featured Image (now used only for the list thumbnail).
* Fix: Article popup header now renders from its own image field.

= 1.5.0 =
* New: Newsroom. A "celeb_news" content type (under the Celebrities menu) for
  publishing articles about a celebrity — headline, body, header image,
  location, media gallery, and unlimited labelled related links.
* New: Collapsible "Newsroom" section on profiles with a thumbnail + headline
  list; each opens a popup (gradient header with title/date/location, article
  body, gallery lightbox, "Download all media" ZIP, and related links).
* New: Server-side "Download all media" ZIP endpoint for each article.

= 1.4.1 =
* Fix: Portal buttons and inputs now override aggressive theme styles (gold
  button with visible label instead of a themed black pill; consistent boxed
  inputs).

= 1.4.0 =
* New: Front-end self-submission portal via the [CLEB_submit] shortcode,
  protected by backend-managed access passwords (add/generate/edit/revoke).
* New: Submissions create Draft profiles (never auto-published) populated into
  the existing fields, so admins review, edit, add photos/banners, and publish.
* New: Submission confirmation message and an admin email notification.
* New: "Self-submitted" column in the Celebrities list and a review notice on
  the edit screen.

= 1.3.2 =
* New: "Coming soon" / under-production flag for Career History roles. Marked
  roles pin to the top of the list, show a gold "Coming Soon" badge instead of
  the year, and get a highlighted title.

= 1.3.1 =
* New: Career History and Awards are now collapsible. They start collapsed with
  a +/- toggle in the heading corner and expand on click, keeping the profile
  compact (content stays in the page for SEO and instant expansion).

= 1.3.0 =
* New: "Let's Talk" call-to-action button below the gallery (label + link
  configurable in Settings; left-aligned).
* New: IMDb and Threads added to the social media platforms.
* New: Bulk import for Career History and Awards via CSV (with a downloadable
  sample and automatic header-row detection).

= 1.2.1 =
* Fix: Biography text now fills the full content width on desktop (justified
  text reaches the section's right edge instead of stopping early).
* Fix: Removed the duplicate top divider line in Career History and Awards
  (the section heading underline already separates the list).

= 1.2.0 =
* New: Birthdate field with a per-celebrity "Show year publicly" toggle.
* New: On the celebrity's birthday, the profile shows an animated "Happy
  Birthday" greeting beside the date and a few seconds of confetti (respects
  reduced-motion preferences).
* Change: Biography is now its own clearly labelled rich-text editor box
  (auto-migrates any existing body content; falls back to it on display).
* Change: Biography text is justified by default.

= 1.1.0 =
* New: Settings page (shortcode reference, accent colour, light/dark profile
  background, grid columns, carousel item count, hero spacing toggle).
* New: Dedicated Profile Image box (sidebar) for grid/carousel cards, with
  Featured Image fallback.
* Fix: Single profile now spans full width (edge-to-edge hero on desktop and
  mobile) instead of being boxed inside the theme content column.
* Fix: Profile body now uses a light background to match white sites.
* Fix: Removed the white gap above the hero caused by theme container padding.

= 1.0.0 =
* Initial release.
