<?php
error_reporting(E_ALL ^ E_DEPRECATED); //if you're using PHP 7.2+ mcrypt is deprecated and generateSessionV2 will throw a warning for it. 
require_once('configs.php');
require_once('./kaltura-php5/KalturaClient.php');

/**
* Helper function to generate a Kaltura session given privileges and userId
* @param string    $privileges     The privileges to set on this session
* @param string    $userId         The ID of the user to set on this session
**/
function createSession($privileges, $userId) {
    $config = new KalturaConfiguration();
    $config->serviceUrl = KALTURA_SERVICE_URL;
    $client = new KalturaClient($config);
    $ks = $client->generateSessionV2(KALTURA_ADMIN_SECRET, $userId, KalturaSessionType::USER, KALTURA_PARTNER_ID, 86400, $privileges);
    return $ks;
}

/**
* Generate the needed KS for kedit (initial KS, and when kea-get-ks postMessage is called)
* @param string    $entryId     The id of the entry to allow editor access to
* @return string   the needed Kaltura Session
**/
function getEditKS($entryId) {
    if (IS_EDITOR == false) // This user can only create new entries (create clips or quiz), it can not trim this entry:
        $privileges  = 'sview:'.$entryId;
    else // This user can edit this entry (Trim this entry) and create new entries (create clips or quiz):
        $privileges  = 'sview:'.$entryId.',edit:'.$entryId;
    return createSession($privileges, TEST_USER_ID);
}

/**
* Generate the needed KS for kedit to preview an entry anonymously (so that when answering a quiz it won't register as a specific user during preview tests)
* @param string    $entryId     The id of the entry to preview
* @return string   the needed Kaltura Session
**/
function getPreviewKS($entryId) {
    // We add "setrole:PLAYBACK_BASE_ROLE" privilege so that this session will not be allowed to perform any action other than the list of actions needed by the player to play this entry.
    $privileges  = 'disableentitlementforentry:'.$entryId.',setrole:PLAYBACK_BASE_ROLE,sview:' . $entryId;
    return createSession($privileges, '');
}

/**
* Retrieve the display name for a given user ID
* @param string    $userId     The id of the user to retrieve display name for
* @return string   the user's display name to be presented in the editor app
**/
function getDisplayNameForUserId ($userId) {
    // Example using the Kaltura user service (if your managing your users outside of Kaltura, this is where you call your own user management service to get the display name for your userId):
    $config = new KalturaConfiguration();
    $config->serviceUrl = KALTURA_SERVICE_URL;
    $client = new KalturaClient($config);
    $ks = $client->generateSessionV2(KALTURA_ADMIN_SECRET, 'kedit-get-user-name', KalturaSessionType::ADMIN, KALTURA_PARTNER_ID, 86400, '');
    $client->setKs($ks);
    $user = $client->user->get($userId); 
    $userDisplayName = $userId; //in case we don't have a display name available for this user, use the userId
    if (isset($user->fullName) && $user->fullName != '' && $user->fullName != $userId)
        $userDisplayName = $user->fullName . '(' . $userId . ')';
    return $userDisplayName;
}

// Editor Services;
if (isset($_GET["action"])) {
    if ($_GET["action"] == 'kea-get-preview-ks') {
        print '{ "ks": "'.getPreviewKS($_GET["entryId"]).'"}';
    } 
    if ($_GET["action"] == 'kea-get-ks') {
        print '{ "ks": "'.getEditKS($_GET["entryId"]).'"}';
    }
    if ($_GET["action"] == 'kea-get-display-name') {
        print '{ "displayName": "'.getDisplayNameForUserId($_GET["userId"]).'"}';
    }
}
