<?php
/**
 * HypeIt app (PWA) shell. Markup + config only — UI lives in assets/js/app.js
 * and assets/css/app.css. Dark by default with a light / system toggle.
 *
 * @package HypeIt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$app        = CP_App_Settings::get();
$app_name   = $app['app_name'] ? $app['app_name'] : __( 'HypeIt', 'hypeit' );
$theme      = $app['app_theme_color'] ? $app['app_theme_color'] : '#000000';
$icon_id    = (int) $app['app_icon_id'];
$icon_url   = $icon_id ? wp_get_attachment_url( $icon_id ) : '';
$splash_id  = (int) $app['app_splash_id'];
$splash_url = $splash_id ? wp_get_attachment_url( $splash_id ) : '';

$cpa_config = array(
	'api'      => esc_url_raw( rest_url( CP_REST::NS ) ),
	'sw'       => esc_url_raw( CP_PWA::sw_url() ),
	'appName'  => $app_name,
	'version'  => CP_VERSION,
	'adminUrl' => esc_url_raw( admin_url( 'edit.php?post_type=' . CP_POST_TYPE ) ),
	'i18n'     => array(
		// Navigation.
		'campaigns'      => __( 'Campaigns', 'hypeit' ),
		'bloggers'       => __( 'Bloggers', 'hypeit' ),
		'lists'          => __( 'Lists', 'hypeit' ),
		'insights'       => __( 'Insights', 'hypeit' ),
		'more'           => __( 'More', 'hypeit' ),
		'back'           => __( 'Back', 'hypeit' ),
		// Login.
		'signInTitle'    => __( 'Sign in', 'hypeit' ),
		'signInSub'      => __( 'Manage campaigns and bloggers from your phone.', 'hypeit' ),
		'username'       => __( 'Username or email', 'hypeit' ),
		'password'       => __( 'Password', 'hypeit' ),
		'keepMe'         => __( 'Keep me signed in', 'hypeit' ),
		'signIn'         => __( 'Sign in', 'hypeit' ),
		'signOut'        => __( 'Sign out', 'hypeit' ),
		'expired'        => __( 'Your session expired. Please sign in again.', 'hypeit' ),
		// Generic.
		'offline'        => __( 'Couldn’t load. Check your connection.', 'hypeit' ),
		'retry'          => __( 'Try again', 'hypeit' ),
		'save'           => __( 'Save', 'hypeit' ),
		'saving'         => __( 'Saving…', 'hypeit' ),
		'cancel'         => __( 'Cancel', 'hypeit' ),
		'confirm'        => __( 'Confirm', 'hypeit' ),
		'delete'         => __( 'Delete', 'hypeit' ),
		'edit'           => __( 'Edit', 'hypeit' ),
		'search'         => __( 'Search', 'hypeit' ),
		'filters'        => __( 'Filters', 'hypeit' ),
		'clear'          => __( 'Clear', 'hypeit' ),
		'apply'          => __( 'Apply', 'hypeit' ),
		'done'           => __( 'Done', 'hypeit' ),
		'any'            => __( 'Any', 'hypeit' ),
		'all'            => __( 'All', 'hypeit' ),
		'none'           => __( 'Nothing here yet.', 'hypeit' ),
		'noResults'      => __( 'No results. Try a different search or filter.', 'hypeit' ),
		'copied'         => __( 'Copied', 'hypeit' ),
		'copy'           => __( 'Copy', 'hypeit' ),
		'share'          => __( 'Share', 'hypeit' ),
		'open'           => __( 'Open', 'hypeit' ),
		'refresh'        => __( 'Refresh', 'hypeit' ),
		'pullRefresh'    => __( 'Release to refresh', 'hypeit' ),
		'required'       => __( 'Required', 'hypeit' ),
		'sortBy'         => __( 'Sort', 'hypeit' ),
		// Status.
		'confirmed'      => __( 'Confirmed', 'hypeit' ),
		'declined'       => __( 'Declined', 'hypeit' ),
		'pending'        => __( 'No response', 'hypeit' ),
		'people'         => __( 'people', 'hypeit' ),
		'peopleCap'      => __( 'People', 'hypeit' ),
		'guests'         => __( 'guests', 'hypeit' ),
		'live'           => __( 'Live', 'hypeit' ),
		'draft'          => __( 'Draft', 'hypeit' ),
		'drafts'         => __( 'Drafts', 'hypeit' ),
		'followers'      => __( 'Followers', 'hypeit' ),
		'followersWord'  => __( 'followers', 'hypeit' ),
		'reach'          => __( 'Reach (28d)', 'hypeit' ),
		'verified'       => __( 'Verified', 'hypeit' ),
		'blocked'        => __( 'Blocked', 'hypeit' ),
		'active'         => __( 'Active', 'hypeit' ),
		// Campaign list.
		'newCampaign'    => __( 'New campaign', 'hypeit' ),
		'searchCampaigns'=> __( 'Search campaigns', 'hypeit' ),
		'sNewest'        => __( 'Newest', 'hypeit' ),
		'sOldest'        => __( 'Oldest', 'hypeit' ),
		'sName'          => __( 'Name A–Z', 'hypeit' ),
		'sMostConfirmed' => __( 'Most confirmed', 'hypeit' ),
		'sMostPending'   => __( 'Most waiting', 'hypeit' ),
		'sMostPeople'    => __( 'Most people', 'hypeit' ),
		'bloggersCount'  => __( '%d bloggers', 'hypeit' ),
		'emptyCampaigns' => __( 'No campaigns yet. Create your first one.', 'hypeit' ),
		// Campaign detail.
		'attendance'     => __( 'Attendance', 'hypeit' ),
		'copyLink'       => __( 'Copy link', 'hypeit' ),
		'openPage'       => __( 'Open page', 'hypeit' ),
		'exportCsv'      => __( 'Export CSV', 'hypeit' ),
		'exportNone'     => __( 'No confirmed bloggers to export yet.', 'hypeit' ),
		'draftBanner'    => __( 'This campaign is a draft — the client link isn’t live yet.', 'hypeit' ),
		'publishNow'     => __( 'Publish now', 'hypeit' ),
		'noPwBanner'     => __( 'No password set. The client can’t open the list until you set one.', 'hypeit' ),
		'setPassword'    => __( 'Set password', 'hypeit' ),
		'everyone'       => __( 'Everyone', 'hypeit' ),
		'evActive'       => __( 'All bloggers are in, and new bloggers join automatically.', 'hypeit' ),
		'evPaused'       => __( 'Paused — the client started selecting. New bloggers aren’t added automatically.', 'hypeit' ),
		'evOff'          => __( 'Only the bloggers below are in this campaign.', 'hypeit' ),
		'addBloggers'    => __( 'Add bloggers', 'hypeit' ),
		'addAll'         => __( 'Add everyone now', 'hypeit' ),
		'addAllQ'        => __( 'Add every active blogger who isn’t in this campaign yet?', 'hypeit' ),
		'participants'   => __( 'Participants', 'hypeit' ),
		'searchHere'     => __( 'Search this campaign', 'hypeit' ),
		'sOrder'         => __( 'Campaign order', 'hypeit' ),
		'sStatus'        => __( 'Status: confirmed first', 'hypeit' ),
		'sStatusRev'     => __( 'Status: no response first', 'hypeit' ),
		'sFollowersDesc' => __( 'Followers: high → low', 'hypeit' ),
		'sFollowersAsc'  => __( 'Followers: low → high', 'hypeit' ),
		'sPeople'        => __( 'People: most first', 'hypeit' ),
		'sCity'          => __( 'City', 'hypeit' ),
		'viewProfile'    => __( 'View profile', 'hypeit' ),
		'openInstagram'  => __( 'Open Instagram', 'hypeit' ),
		'removeFromCamp' => __( 'Remove from campaign', 'hypeit' ),
		'removeQ'        => __( 'Remove this blogger from the campaign?', 'hypeit' ),
		'removeRespQ'    => __( 'This blogger already has a client response. Remove anyway? The response will be lost.', 'hypeit' ),
		'closed'         => __( 'Closed', 'hypeit' ),
		'closeCamp'      => __( 'Close campaign', 'hypeit' ),
		'reopen'         => __( 'Reopen', 'hypeit' ),
		'closeQ'         => __( 'Close this campaign? The client won’t be able to open it anymore, and bloggers without a response won’t count in insights. You can reopen it any time.', 'hypeit' ),
		'closedBanner'   => __( 'This campaign is closed — the client link no longer works.', 'hypeit' ),
		'duplicate'      => __( 'Duplicate', 'hypeit' ),
		'moveDraft'      => __( 'Move to drafts', 'hypeit' ),
		'resetResp'      => __( 'Reset all responses', 'hypeit' ),
		'resetQ'         => __( 'Reset every response back to “no response”? This can’t be undone.', 'hypeit' ),
		'deleteCampQ'    => __( 'Move this campaign to trash? The client link stops working.', 'hypeit' ),
		'emptyPart'      => __( 'No bloggers yet. Add some or turn on Everyone.', 'hypeit' ),
		// Campaign form.
		'editCampaign'   => __( 'Edit campaign', 'hypeit' ),
		'basics'         => __( 'Basics', 'hypeit' ),
		'campName'       => __( 'Campaign name', 'hypeit' ),
		'brief'          => __( 'Brief for the client', 'hypeit' ),
		'briefPh'        => __( 'Event details, date, location, what you need…', 'hypeit' ),
		'logo'           => __( 'Client logo', 'hypeit' ),
		'chooseImage'    => __( 'Choose image', 'hypeit' ),
		'removeImage'    => __( 'Remove', 'hypeit' ),
		'startWith'      => __( 'Bloggers', 'hypeit' ),
		'startEveryone'  => __( 'Add everyone (new bloggers join automatically until the client starts selecting)', 'hypeit' ),
		'startPick'      => __( 'I’ll pick bloggers after creating', 'hypeit' ),
		'access'         => __( 'Access', 'hypeit' ),
		'generate'       => __( 'Generate', 'hypeit' ),
		'pwKeep'         => __( 'Leave blank to keep the current password', 'hypeit' ),
		'pwNew'          => __( 'Password the client will use', 'hypeit' ),
		'customLink'     => __( 'Custom link', 'hypeit' ),
		'randomLink'     => __( 'Replace with a random link', 'hypeit' ),
		'clientPage'     => __( 'Client page', 'hypeit' ),
		'maxGuests'      => __( 'Max additional guests per blogger', 'hypeit' ),
		'showFollowers'  => __( 'Show followers', 'hypeit' ),
		'showGender'     => __( 'Show gender', 'hypeit' ),
		'showCats'       => __( 'Show categories', 'hypeit' ),
		'showLocation'   => __( 'Show location', 'hypeit' ),
		'showPopularity' => __( 'Show popularity label', 'hypeit' ),
		'notifications'  => __( 'Notification emails', 'hypeit' ),
		'notifyPh'       => __( 'Uses the default if blank · comma-separated', 'hypeit' ),
		'visibility'     => __( 'Status', 'hypeit' ),
		'publishLive'    => __( 'Live', 'hypeit' ),
		'saveDraft'      => __( 'Draft', 'hypeit' ),
		'create'         => __( 'Create campaign', 'hypeit' ),
		'shareTitle'     => __( 'Share with the client', 'hypeit' ),
		'shareNote'      => __( 'Copy this now — the password can’t be shown again.', 'hypeit' ),
		'shareMsg'       => __( "Here’s your campaign link:\n%1\$s\nPassword: %2\$s", 'hypeit' ),
		'copyBoth'       => __( 'Copy link + password', 'hypeit' ),
		'nameRequired'   => __( 'Please give the campaign a name.', 'hypeit' ),
		'uploadFailed'   => __( 'The logo couldn’t be uploaded.', 'hypeit' ),
		// Picker.
		'selectShown'    => __( 'Select all shown', 'hypeit' ),
		'addSelected'    => __( 'Add %d selected', 'hypeit' ),
		'allIn'          => __( 'Everyone matching is already in this campaign.', 'hypeit' ),
		'availableN'     => __( '%d available', 'hypeit' ),
		// Bloggers.
		'addBlogger'     => __( 'Add blogger', 'hypeit' ),
		'editBlogger'    => __( 'Edit blogger', 'hypeit' ),
		'searchBloggers' => __( 'Search name, @handle or city', 'hypeit' ),
		'sNewestAdded'   => __( 'Newest added', 'hypeit' ),
		'sVerified'      => __( 'Verified first', 'hypeit' ),
		'sPopular'       => __( 'Most popular', 'hypeit' ),
		'list'           => __( 'List', 'hypeit' ),
		'gender'         => __( 'Gender', 'hypeit' ),
		'city'           => __( 'City', 'hypeit' ),
		'email'          => __( 'Email', 'hypeit' ),
		'emailBad'       => __( 'Please enter a valid email address.', 'hypeit' ),
		'openFor'        => __( 'Open for', 'hypeit' ),
		'verifiedOnly'   => __( 'Verified only', 'hypeit' ),
		'state'          => __( 'Show', 'hypeit' ),
		'onlyActive'     => __( 'Active only', 'hypeit' ),
		'onlyBlocked'    => __( 'Blocked only', 'hypeit' ),
		'categories'     => __( 'Categories', 'hypeit' ),
		'location'       => __( 'Location', 'hypeit' ),
		'profile'        => __( 'Profile', 'hypeit' ),
		'private'        => __( 'Private — admin only', 'hypeit' ),
		'birthday'       => __( 'Birthday', 'hypeit' ),
		'phone'          => __( 'Phone', 'hypeit' ),
		'whatsapp'       => __( 'WhatsApp', 'hypeit' ),
		'call'           => __( 'Call', 'hypeit' ),
		'address'        => __( 'Address', 'hypeit' ),
		'source'         => __( 'Source', 'hypeit' ),
		'firstName'      => __( 'First name', 'hypeit' ),
		'lastName'       => __( 'Last name', 'hypeit' ),
		'igField'        => __( 'Instagram username or link', 'hypeit' ),
		'newList'        => __( 'Or create a new list', 'hypeit' ),
		'igRequired'     => __( 'Instagram username is required.', 'hypeit' ),
		'block'          => __( 'Block', 'hypeit' ),
		'unblock'        => __( 'Unblock', 'hypeit' ),
		'deleteBlock'    => __( 'Delete & block', 'hypeit' ),
		'blockQ'         => __( 'Block this blogger? They’ll be hidden from campaigns and can’t re-apply.', 'hypeit' ),
		'deleteQ'        => __( 'Delete this blogger permanently?', 'hypeit' ),
		'deleteBlockQ'   => __( 'Delete this blogger and block them from re-applying?', 'hypeit' ),
		'dangerZone'     => __( 'Danger zone', 'hypeit' ),
		'acceptance'     => __( 'Acceptance', 'hypeit' ),
		'included'       => __( 'Campaigns included', 'hypeit' ),
		'accepted'       => __( 'Accepted', 'hypeit' ),
		'rejected'       => __( 'Rejected', 'hypeit' ),
		'rejection'      => __( 'Rejection rate', 'hypeit' ),
		// Insights.
		'selections'     => __( 'Selections', 'hypeit' ),
		'acceptRate'     => __( 'Accept rate', 'hypeit' ),
		'rejectRate'     => __( 'Reject rate', 'hypeit' ),
		'p30'            => __( '30 days', 'hypeit' ),
		'p90'            => __( '90 days', 'hypeit' ),
		'p365'           => __( '12 months', 'hypeit' ),
		'pAll'           => __( 'All time', 'hypeit' ),
		'proposed'       => __( 'Proposed', 'hypeit' ),
		'pts'            => __( 'pts', 'hypeit' ),
		'responseRate'   => __( 'Response rate', 'hypeit' ),
		'attending'      => __( 'People attending', 'hypeit' ),
		'libVerified'    => __( 'Library · %s% verified', 'hypeit' ),
		'trend12'        => __( 'Selections — last 12 months', 'hypeit' ),
		'waiting'        => __( 'Waiting', 'hypeit' ),
		'clientsPick'    => __( 'What clients pick', 'hypeit' ),
		'byCategory'     => __( 'Category', 'hypeit' ),
		'byTier'         => __( 'Size', 'hypeit' ),
		'byGender'       => __( 'Gender', 'hypeit' ),
		'byCity'         => __( 'City', 'hypeit' ),
		'ofProposed'     => __( 'of %s', 'hypeit' ),
		'barMeaning'     => __( 'Bar = share of responses where the client said yes.', 'hypeit' ),
		'bestAcceptance' => __( 'Best acceptance', 'hypeit' ),
		'min3'           => __( '3+ responses', 'hypeit' ),
		'mostProposed'   => __( 'Most proposed', 'hypeit' ),
		'mostReliable'   => __( 'Most reliable', 'hypeit' ),
		'atriumCheckins' => __( 'ATRIUM check-ins', 'hypeit' ),
		'needsAttention' => __( 'Needs attention', 'hypeit' ),
		'neverSelected'  => __( 'Never selected (30+ days)', 'hypeit' ),
		'oftenDeclined'  => __( 'Declined 3+ times, never accepted', 'hypeit' ),
		'notVerified'    => __( 'Not verified', 'hypeit' ),
		'personalAccs'   => __( 'Personal Instagram accounts', 'hypeit' ),
		'respPct'        => __( '%s% responded', 'hypeit' ),
		'mostSelected'   => __( 'Most selected', 'hypeit' ),
		'trending'       => __( 'Trending', 'hypeit' ),
		'mostAccepted'   => __( 'Most accepted', 'hypeit' ),
		'mostRejected'   => __( 'Most rejected', 'hypeit' ),
		'noData'         => __( 'No data yet.', 'hypeit' ),
		// Instagram sync (no login).
		'igOk'           => __( 'Instagram synced %s ago', 'hypeit' ),
		'igEng'          => __( '%s% engagement', 'hypeit' ),
		'igPosts'        => __( '%s posts', 'hypeit' ),
		'igPersonal'     => __( 'Personal Instagram account — numbers can’t be read. Ask them to switch to a Creator account.', 'hypeit' ),
		'igPending'      => __( 'Instagram not synced yet — it will update within the hour.', 'hypeit' ),
		'missingInfo'    => __( 'Missing info', 'hypeit' ),
		'missAny'        => __( 'Missing any', 'hypeit' ),
		'missAll'        => __( 'Missing all', 'hypeit' ),
		'missingX'       => __( 'Missing %s', 'hypeit' ),
		'sLeastComplete' => __( 'Least complete', 'hypeit' ),
		'sMostComplete'  => __( 'Most complete', 'hypeit' ),
		'sOldestAdded'   => __( 'Oldest first', 'hypeit' ),
		'sNameDesc'      => __( 'Name Z–A', 'hypeit' ),
		'sLatestDone'    => __( 'Latest completed', 'hypeit' ),
		'sBloggerUpd'    => __( 'Last updated by blogger', 'hypeit' ),
		'sRecentUpd'     => __( 'Recently updated', 'hypeit' ),
		// Deactivate + profile completeness + follower trend.
		'deactivate'     => __( 'Deactivate', 'hypeit' ),
		'reactivate'     => __( 'Reactivate', 'hypeit' ),
		'deactivated'    => __( 'Deactivated', 'hypeit' ),
		'onlyInactive'   => __( 'Deactivated', 'hypeit' ),
		'deactivateQ'    => __( 'Deactivate this blogger? They’ll be hidden from campaigns, selections and lists. Nothing is deleted — you can reactivate them any time.', 'hypeit' ),
		'deactivatedBanner' => __( 'Deactivated — hidden from campaigns, selections and lists. Past campaign records are kept.', 'hypeit' ),
		'deactivatedDone'   => __( 'Blogger deactivated', 'hypeit' ),
		'reactivatedDone'   => __( 'Blogger reactivated', 'hypeit' ),
		'completeness'   => __( 'Profile completeness', 'hypeit' ),
		'markComplete'   => __( 'Mark profile as complete', 'hypeit' ),
		'markCompleteHint' => __( 'For bloggers you know personally — the basic profile and follower count are enough.', 'hypeit' ),
		'markedComplete' => __( 'Marked complete', 'hypeit' ),
		'unmarkedComplete' => __( 'Complete mark removed', 'hypeit' ),
		'autoComplete'     => __( 'Profile complete', 'hypeit' ),
		'autoCompleteHint' => __( 'Every detail is filled in, so it’s marked complete automatically.', 'hypeit' ),
		'needsFollowers' => __( 'Add the Instagram username and follower count — it counts as complete once both are there.', 'hypeit' ),
		'fUp'            => __( '+%s since the last sync', 'hypeit' ),
		'fDown'          => __( '−%s since the last sync', 'hypeit' ),
		'growing'        => __( 'Growing', 'hypeit' ),
		'shrinking'      => __( 'Shrinking', 'hypeit' ),
		'completeProfiles' => __( 'Complete profiles', 'hypeit' ),
		'libHealth'      => __( 'Library health', 'hypeit' ),
		'openExisting'   => __( 'Open existing profile', 'hypeit' ),
		'verification'   => __( 'Verification', 'hypeit' ),
		'markVerified'   => __( 'Mark as verified', 'hypeit' ),
		'unverify'       => __( 'Remove verification', 'hypeit' ),
		'unverifyQ'      => __( 'Remove the ✓ Verified badge from this blogger?', 'hypeit' ),
		'verifiedDone'   => __( 'Marked as verified ✓', 'hypeit' ),
		'vmManual'       => __( 'Approved manually', 'hypeit' ),
		'vmBio'          => __( 'Bio code detected automatically', 'hypeit' ),
		'vmMeta'         => __( 'Instagram login', 'hypeit' ),
		'vCodeHint'      => __( 'Bio code: %s — or verify manually', 'hypeit' ),
		'vManualHint'    => __( 'Verify manually once you’ve confirmed the account', 'hypeit' ),
		'personalOnly'   => __( 'Personal Instagram accounts only', 'hypeit' ),
		'askSwitch'      => __( 'Ask to switch (WhatsApp)', 'hypeit' ),
		'igSync'         => __( 'Sync', 'hypeit' ),
		'igSynced'       => __( 'Instagram numbers updated.', 'hypeit' ),
		'igRate'         => __( 'Instagram asked us to slow down — try again in an hour.', 'hypeit' ),
		'igToken'        => __( 'The Instagram connection needs renewing in WordPress → HypeIt → Verification.', 'hypeit' ),
		// ATRIUM event bridge.
		'event'          => __( 'Event', 'hypeit' ),
		'evNone'         => __( 'Invite the bloggers the client confirmed to an ATRIUM event — with personal invitations and check-in.', 'hypeit' ),
		'evCreate'       => __( 'Create event', 'hypeit' ),
		'evLink'         => __( 'Link existing', 'hypeit' ),
		'evShared'       => __( 'Same date & time for everyone', 'hypeit' ),
		'evPerGuestOpt'  => __( 'Each blogger gets their own visit time', 'hypeit' ),
		'evPerGuest'     => __( 'Per-blogger visit times', 'hypeit' ),
		'evDraft'        => __( 'Set the date and venue in ATRIUM and publish the event before sending invitations.', 'hypeit' ),
		'evSendN'        => __( 'Send %d to ATRIUM', 'hypeit' ),
		'evSync'         => __( 'Sync with ATRIUM', 'hypeit' ),
		'evEdit'         => __( 'Edit in ATRIUM', 'hypeit' ),
		'evPage'         => __( 'Event page', 'hypeit' ),
		'evUnlink'       => __( 'Unlink event', 'hypeit' ),
		'evUnlinkQ'      => __( 'Unlink this event? Guests already in ATRIUM are kept.', 'hypeit' ),
		'evPick'         => __( 'Choose an event', 'hypeit' ),
		'evNoEvents'     => __( 'No ATRIUM events yet — create one instead.', 'hypeit' ),
		'evSelected'     => __( 'Selected', 'hypeit' ),
		'evInvited'      => __( 'Invited', 'hypeit' ),
		'evOpened'       => __( 'Opened', 'hypeit' ),
		'evConfirmed'    => __( 'Confirmed', 'hypeit' ),
		'evAttended'     => __( 'Attended', 'hypeit' ),
		'waInvite'       => __( 'WhatsApp invite', 'hypeit' ),
		'copyInvite'     => __( 'Copy invitation link', 'hypeit' ),
		'reliability'    => __( 'Reliability', 'hypeit' ),
		'relDetail'      => __( 'attended %1$d of %2$d confirmed events', 'hypeit' ),
		// Photos.
		'photo'          => __( 'Profile photo', 'hypeit' ),
		'choosePhoto'    => __( 'Choose photo', 'hypeit' ),
		'removePhoto'    => __( 'Remove', 'hypeit' ),
		'photoFailed'    => __( 'The photo couldn’t be uploaded.', 'hypeit' ),
		// Push.
		'pushTitle'      => __( 'Notifications', 'hypeit' ),
		'pushNew'        => __( 'New blogger submissions', 'hypeit' ),
		'pushNewSub'     => __( 'Get a notification on this device when a blogger submits the form.', 'hypeit' ),
		'pushTest'       => __( 'Send a test notification', 'hypeit' ),
		'pushTestSent'   => __( 'Test sent — it should arrive in a few seconds.', 'hypeit' ),
		'pushOn'         => __( 'Notifications are on for this device.', 'hypeit' ),
		'pushOff'        => __( 'Notifications are off for this device.', 'hypeit' ),
		'pushDenied'     => __( 'Notifications are blocked. Turn them on in your phone’s Settings → Notifications → HypeIt.', 'hypeit' ),
		'pushInstall'    => __( 'To get notifications on iPhone, open this app from your Home Screen: in Safari tap Share → Add to Home Screen, then open it from there.', 'hypeit' ),
		'pushUnsupported'=> __( 'This browser doesn’t support push notifications.', 'hypeit' ),
		'pushSwFail'     => __( 'The app’s background service didn’t start. Fully close the app (swipe it away), reopen it from the Home Screen, and try again.', 'hypeit' ),
		'pushServer'     => __( 'The server can’t send push notifications (missing OpenSSL features). Please contact your host.', 'hypeit' ),
		// More.
		'signedInAs'     => __( 'Signed in as', 'hypeit' ),
		'appearance'     => __( 'Appearance', 'hypeit' ),
		'dark'           => __( 'Dark', 'hypeit' ),
		'light'          => __( 'Light', 'hypeit' ),
		'system'         => __( 'System', 'hypeit' ),
		'reloadData'     => __( 'Reload all data', 'hypeit' ),
		'reloaded'       => __( 'Data reloaded.', 'hypeit' ),
		'openAdmin'      => __( 'Open WordPress admin', 'hypeit' ),
		'version'        => __( 'Version', 'hypeit' ),
	),
);
?><!DOCTYPE html>
<html lang="<?php echo esc_attr( get_bloginfo( 'language' ) ); ?>" data-theme="dark">
<head>
	<meta charset="<?php echo esc_attr( get_bloginfo( 'charset' ) ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
	<meta name="theme-color" content="<?php echo esc_attr( $theme ); ?>" />
	<meta name="mobile-web-app-capable" content="yes" />
	<meta name="apple-mobile-web-app-capable" content="yes" />
	<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent" />
	<meta name="apple-mobile-web-app-title" content="<?php echo esc_attr( $app_name ); ?>" />
	<meta name="format-detection" content="telephone=no" />
	<meta name="robots" content="noindex, nofollow" />
	<title><?php echo esc_html( $app_name ); ?></title>
	<link rel="manifest" href="<?php echo esc_url( CP_PWA::manifest_url() ); ?>" />
	<?php if ( $icon_url ) : ?>
		<link rel="apple-touch-icon" href="<?php echo esc_url( $icon_url ); ?>" />
		<link rel="icon" href="<?php echo esc_url( $icon_url ); ?>" />
	<?php endif; ?>
	<?php if ( $splash_url ) : ?>
		<link rel="apple-touch-startup-image" href="<?php echo esc_url( $splash_url ); ?>" />
	<?php endif; ?>
	<script>
	( function () {
		try {
			var p = localStorage.getItem( 'cp_app_theme' ) || 'dark';
			if ( p === 'system' ) { p = ( window.matchMedia && matchMedia( '(prefers-color-scheme: light)' ).matches ) ? 'light' : 'dark'; }
			document.documentElement.setAttribute( 'data-theme', p );
		} catch ( e ) {}
	} )();
	</script>
	<link rel="stylesheet" href="<?php echo esc_url( add_query_arg( 'ver', CP_VERSION, CP_URL . 'assets/css/app.css' ) ); ?>" />
</head>
<body>
	<div id="boot" class="boot"><div class="spinner"></div></div>

	<section id="login" class="login" hidden>
		<form class="login-card" id="login-form" autocomplete="on">
			<?php if ( $icon_url ) : ?>
				<img class="login-icon" src="<?php echo esc_url( $icon_url ); ?>" alt="" />
			<?php endif; ?>
			<h1><?php echo esc_html( $app_name ); ?></h1>
			<p class="muted"><?php esc_html_e( 'Manage campaigns and bloggers from your phone.', 'hypeit' ); ?></p>
			<label class="field"><span><?php esc_html_e( 'Username or email', 'hypeit' ); ?></span><input class="input" id="login-u" type="text" autocomplete="username" autocapitalize="none" spellcheck="false" required /></label>
			<label class="field"><span><?php esc_html_e( 'Password', 'hypeit' ); ?></span><input class="input" id="login-p" type="password" autocomplete="current-password" required /></label>
			<label class="check"><input type="checkbox" id="login-remember" checked /> <span><?php esc_html_e( 'Keep me signed in', 'hypeit' ); ?></span></label>
			<button class="btn btn-block" type="submit" id="login-btn"><?php esc_html_e( 'Sign in', 'hypeit' ); ?></button>
			<p class="error" id="login-error" role="alert"></p>
		</form>
	</section>

	<div id="app" class="app" hidden>
		<header class="bar" id="bar">
			<button class="bar-btn" id="bar-back" type="button" aria-label="<?php esc_attr_e( 'Back', 'hypeit' ); ?>" hidden>
				<svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path d="M15 5l-7 7 7 7" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
			</button>
			<h1 class="bar-title" id="bar-title"></h1>
			<div class="bar-actions" id="bar-actions"></div>
		</header>
		<div class="ptr" id="ptr" aria-hidden="true"><div class="ptr-spin"></div></div>
		<main class="view" id="view" tabindex="-1"></main>
		<nav class="tabbar" id="tabbar" aria-label="<?php esc_attr_e( 'Main', 'hypeit' ); ?>"></nav>
	</div>

	<div id="sheet-root"></div>
	<div id="toast-root" class="toasts" aria-live="polite"></div>

	<script>window.CPA = <?php echo wp_json_encode( $cpa_config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE ); ?>;</script>
	<script src="<?php echo esc_url( add_query_arg( 'ver', CP_VERSION, CP_URL . 'assets/js/app.js' ) ); ?>" defer></script>
</body>
</html>
