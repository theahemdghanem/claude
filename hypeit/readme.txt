=== HypeIt ===
Contributors: iLike Agency
Requires at least: 6.0
Requires PHP: 7.4
Stable tag: 2.2.1
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

= 2.2.1 =
* Profiles with every detail filled in (100%) show as complete automatically — the tick appears without marking them by hand.
* New sort: Recently updated (any change made in the admin, the app or the form).
* Follower trend arrows now use ▲ / ▼ and appear from the very next Instagram sync (the count from the previous sync is used as the starting point).

= 2.2.0 =
* Deactivate bloggers instead of deleting them: they disappear from campaigns, client selections, lists, the app and the public count, but their profile and campaign records are kept. Reactivate any time (row action, bulk action, Status tab or the app).
* Mark a profile as complete by hand — for bloggers you know personally, the basic profile and follower count are enough.
* Follower trend: after each Instagram sync the new count is compared with the previous sync — green ↑ when it grew, red ↓ when it dropped (Bloggers page, blogger editor, campaign editor and the app).
* Bloggers page sorting: Newest/Oldest first, Name A–Z/Z–A, Most/Fewest followers, Verified first, Latest completed, Most complete, Last updated by blogger.
* Redesigned Campaigns list (summary strip, status, client progress, attendance, copy link, close/reopen from the list).
* Insights now uses the full screen width and adds a Library health card.
* Refreshed Onboarding and Verification settings pages; categories are now managed on the Onboarding page (the separate Manage Categories screen was removed — old links redirect).

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
