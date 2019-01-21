<?php
// See configs.php to set sample parameters like partnerId, entryId, userId, uiConfId, etc.
require_once('editor-services.php'); //the backend functions for KS generation and userId to displayname
?>
<!DOCTYPE html>
<html>
<head>
    <title>Kaltura Editor Wrapper Reference Implementation Code</title>
    <script src="//ajax.googleapis.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script>
    <style type="text/css">
        iframe {
            border: 1px solid black;
            min-width: 1024px; /* kedit must have a minimum width of 1024px tablet or 1025px on desktop */
            /* TODO: Add CSS rules for iPad vs. Desktop so that it automatically applies the right size */
            width: 1025px; 
            height: 642px;
        }
        html, body {
            width: 100%;
            height: 100%;
            text-align: center;
        }
    </style>
</head>
<body>

    <!-- use /latest/ to always get the latest version of the editor app, or vVERSION (e.g. v2.22.1) to load a specific version (list of available versions can be found in the change log: https://knowledge.kaltura.com/kaltura-video-editing-tools-release-notes-and-changelog) -->
    <iframe src="//cdnapisec.kaltura.com/apps/kea/latest/index.html"></iframe>

    <script>
        (function(window) {
            var keaInitParams = {
                'messageType': 'kea-config',
                'data': {
                    /* URL of the Kaltura Server to use */
		    'service_url': '<?php echo KALTURA_SERVICE_URL; ?>',

                    /* the partner ID to use */
                    'partner_id': <?php echo KALTURA_PARTNER_ID; ?>,

                    /* Kaltura session key to use */
                    'ks': '<?php echo getEditKS(TEST_ENTRY_ID); ?>',

                    /* language - used by priority:
                    * 1. Custom locale (locale_url)
                    *       full url of a json file with translations
                    * 2. Locale code (language_code
                    *       there should be a matching json file under src\assets\i18n)
                    * 3. English default locale (fallback). */
                    'language_code': 'en',
                    'locale_url': null, //link to an external locale file to override language texts

                    /* URL to be used for "Go to User Manual" in KEdit help component */
                    'help_link': 'https://knowledge.kaltura.com/node/1912',

                    /* tabs to show in navigation */
                    'tabs': {
                        'quiz': { name: 'quiz', 
				  // use 'quiz' for older player versions, and 'questions-v2' for latest player version
				  // use preventSeek to prevent users from being able to seek while watching a quiz video
				  // remove preventSeek if you wish to enable users to seek while watching the video
				  permissions: ['questions-v2', 'preventSeek'], 
				  userPermissions: ['quiz'] 
				},
                        'edit': { name: 'edit',
                            	  permissions: ['clip', 'trim'],
                            	  userPermissions: ['clip', 'trim'],
                            	  preSaveMessage: 'Note: Trimming a video will trim the source flavor. If you wish to keep the original video, Clip into a new entry instead of Trimming the video.', //optional msg
                            	  preSaveAsMessage: 'A new entry was created for you.' //optional msg
                        }
                    },

                    /* tab to start current session with, should match one of the keys above  */
                    'tab': 'quiz', //can be 'editor' or 'quiz'

                    /* URL of an additional css file to load */
                    'css_url': null, //if needed to override any of the editor CSS, this can be overrided here by linking to an external CSS file 

                    /* id of the entry to start with */
                    'entry_id': '<?php echo TEST_ENTRY_ID; ?>',

                    /* id of uiconf to be used for internal player,
                    * if left empty the default deployed player will be used */
                    'player_uiconf_id': <?php echo TEST_PLAYER_UICONF_ID; ?>,

                    /* id of uiconf to be used for preview. if not passed, main player is used */
                    'preview_player_uiconf_id': null,

                    /* should a KS be appended to the thumbnails url, only if account has access control applied to thumbnails (not recommended, as this will prevent thumbnail caching) */
                    'load_thumbnail_with_ks': false
                }
            };

            // To read more about postMessage API: https://developer.mozilla.org/en-US/docs/Web/API/Window/postMessage
            var initParamsListener = window.addEventListener('message', function(e) {
                var postMessageData;
                try {
                    postMessageData = e.data;
                }
                catch(ex) {
                    return;
                }

                /* request for init params,
                * should return a message where messageType = kea-config */
                if(postMessageData.messageType === 'kea-bootstrap') {
                    e.source.postMessage(keaInitParams, e.origin);
                }

                /* received when a trim action was requested.
                 * message.data = {entryId}
                 * should return a message where message.messageType = kea-trim-message
                 * and message.data is the (localized) text to show the user.
                */
                if(postMessageData.messageType === 'kea-trimming-started') {
                    e.source.postMessage({
                        messageType: 'kea-trim-message',
                        data: 'You must approve the media replacement in order to be able to watch the trimmed media'
                    }, e.origin)
                }

                /* received when a trim action is complete.
                 * message.data = {entryId}
                 * can be used to clear app cache, for example.
                 */
                if(postMessageData.messageType === 'kea-trimming-done') {
                    console.log('Entry was successfuly trimmed');
                }

                /* received when a clip was created.
                 * postMessageData.data: {
                 *  originalEntryId,
                 *  newEntryId,
                 *  newEntryName
                 * }
                 * should return a message where message.messageType = kea-clip-message,
                 * and message.data is the (localized) text to show the user.
                 * */
                if (postMessageData.messageType === 'kea-clip-created') {
                    var message = 'A new video clip named "' + postMessageData.data.newEntryName + '" (id: ' + postMessageData.data.newEntryId + ') was created from ' + postMessageData.data.originalEntryId;
                    e.source.postMessage({
                        'messageType': 'kea-clip-message',
                        'data': message
                    }, e.origin);
                }

                /* request for user display name of the entry owner.
                * message.data = {userId}
                * should return a message {messageType:kea-display-name, data: display name}
                */
                if (postMessageData.messageType === 'kea-get-display-name') {
                    //use the userId to get display name from your service
                    var getUserDisplaynameUrl = './editor-services.php?userId=' + postMessageData.data.userId + '&action=kea-get-display-name';
                    $.getJSON( getUserDisplaynameUrl, null )
                    .done(function( responseData ) {
                        e.source.postMessage({
                            messageType: 'kea-display-name',
                            data: responseData.displayName
                        }, e.origin);
                    })
                    .fail(function( jqxhr, textStatus, error ) {
                        var err = textStatus + ", " + error;
                        console.log( "Failed to retrieve the user display name: " + err );
                        e.source.postMessage({
                            messageType: 'kea-display-name',
                            data: postMessageData.data.userId
                        }, e.origin);
                    });
                }

                /*
                 * Fired when saving quiz's settings.
                 * message.data = {entryId}
                 */
                if (postMessageData.messageType === 'kea-quiz-updated') {
                    // do something (you can also ignore it), you can invalidate cache, etc..
                }

                /*
                 * Fired when creating a new quiz
                 * message.data = {entryId}
                 */
                if (postMessageData.messageType === 'kea-quiz-created') {
                    // do something (you can also ignore it), you can invalidate cache, etc..
                }

                /* Request for a new Kaltura Session when a new entry was created or the user clicked Preview
                * message.data = entryId
                * may return one of the following responses:
                *   New clip session:
                * {
                *   messageType: kea-get-ks
                *   data: ks
                * }
                *   Preview session:
                * {
                *   messageType: kea-preview-ks
                *   data: ks
                * }
                */
                if (postMessageData.messageType === 'kea-get-ks' || 
                    postMessageData.messageType === 'kea-get-preview-ks')  
                {
                    var getKsUrl = './editor-services.php?' + 'entryId=' + postMessageData.data.entryId + '&action=' + postMessageData.messageType;
                    $.getJSON( getKsUrl, null )
                    .done(function( responseData ) {
                        e.source.postMessage({
                            messageType: 'kea-ks',
                            data: responseData.ks
                        }, e.origin);
                    })
                    .fail(function( jqxhr, textStatus, error ) {
                        var err = textStatus + ", " + error;
                        console.log( "Get KS for " + postMessageData.messageType + " Request Failed: " + err );
                    });
                }

                /* received when user is to be navigated outside of thed application (e.g. finished editing)
                * message.data = entryId
                * The host application should navigate to a page displaying the edited media. 
                */
                else if (postMessageData.messageType === 'kea-go-to-media') {
                    console.log ("Redirecting to the new media: " + postMessageData.data);
                    var videoPath = 'https://example.com/video/'; //replace with your real service path for video playbacl pages
                    var redirectUrl = videoPath + '?entryId=' + postMessageData.data;
                    $(location).attr('href', redirectUrl);
                }
            });
        })(window);
    </script>
</body>
</html>
