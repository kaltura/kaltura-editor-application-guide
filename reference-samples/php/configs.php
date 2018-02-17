<?php
// Kaltura API base endpoint
define("KALTURA_SERVICE_URL", 'https://www.kaltura.com'); 
//The Kaltura Account ID - KMC > Settings > Integration Settings
define("KALTURA_PARTNER_ID", 0000000); 
//The ADMIN API secret key - KMC > Settings > Integration Settings
define("KALTURA_ADMIN_SECRET",      "your-kalturaadmin-secret-goes-here");
//It is a const for simple example, but you should use real user IDs from your application/system 
define("TEST_USER_ID",              'keditor-test-user'); 
//It is a const for simple example, but you should use real entry ids from your application/system 
define("TEST_ENTRY_ID",             'your-entryid');
//It is a const for simple example, this will determine whether to allow editing of the loaded entry (edit quiz or trim), or just cloning (new quiz or new clip). In your application use this according to your desired workflow and permissions
define("IS_EDITOR",                 true);
// The Kaltura Player ID to use - KMC > Studio 
// Note: The player loaded by the editor should NOT have Auto Play enabled.
define("TEST_PLAYER_UICONF_ID", 0000000);
