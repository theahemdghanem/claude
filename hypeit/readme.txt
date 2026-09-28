=== HypeIt ===
Contributors: iLike Agency
Requires at least: 6.0
Requires PHP: 7.4
Stable tag: 2.1.0
License: GPL-2.0-or-later

Blogger management for agencies: a blogger library with onboarding, verification and automatic Instagram sync, plus private campaign pages where clients pick the bloggers they want.

== Description ==

* Blogger library with profiles, photos, categories, smart lists and private contact details.
* Onboarding form with automatic follower check and bio-code verification (no login).
* Campaigns with private client selection pages, open/close control and CSV export.
* Insights dashboard: acceptance by category, size, gender and city; leaderboards; trends.
* ATRIUM bridge for invitations, RSVP, check-in and reliability.
* Companion app (PWA) with push notifications.
* Email notifications: right away, daily digest or weekly digest.

== Changelog ==

= 2.1.0 =
* Redesigned Bloggers page: summary strip, one toolbar, Missing info filter (any/all), sort menu, filter chips, new Contact and Profile columns.
* App: Missing info filter and completeness sorting.

= 2.0.6 =
* One Instagram account = one profile: duplicates are blocked in the admin editor, the app and when restoring from trash; existing duplicates are flagged for review.

= 2.0.5 =
* Fixed: saving Verification settings no longer breaks the Instagram connection (#100 accounts error).

= 2.0.4 =
* Reliable follower refresh: status panel, backup trigger when the admin/app is used, and an optional server-timer link for hosts with page caching.

= 2.0.3 =
* App: verify or remove verification for any blogger manually.

= 2.0.2 =
* Campaigns stay in step with the library: deleted or blocked bloggers leave open campaigns, username changes no longer create duplicates.
* One-time tidy-up of existing open campaigns (client selections are always kept).

= 2.0.1 =
* Fixed: App icon and splash image pickers on the HypeIt App page.
* New [hypeit_join] shortcode (the old [campaign_join] keeps working).

= 2.0.0 =
* Renamed from "Campaign" to HypeIt — all bloggers, campaigns and settings carry over.
* Custom app address (default stays /campaign-app/ so installed apps keep working).
* Removing the old "Campaign" plugin can never delete data.
