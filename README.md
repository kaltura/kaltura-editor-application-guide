# Kaltura Editing Application Integration Guide

The Kaltura Editor Application v2 provides the following capabilities:  

* In-Video Quiz Creation and Editing
* Video Entry Clipping and Trimming

The app is loaded as an iframe like so:  

`<iframe src="//cdnapisec.kaltura.com/apps/kea/v<version>/index.html"></iframe>`

> Replace `<kea_version>` with `latest` to always load the latest production stable version, or set to a specific version number (Refer to the [official changelog](https://knowledge.kaltura.com/node/1926) for the list of production available versions).  
**Important**: Do not load the index.html directly, it should only be loaded in an iFrame.

### The basics

Integrating the Kaltura Editor Application is done by embedding an iFrame inside your web application. Communication with the editor app (calling actions and reacting to events) is done by using the [postMessage API](https://developer.mozilla.org/en-US/docs/Web/API/Window/postMessage).
  
In this repository you will also find complete reference implementation examples (under the reference-samples directory, respective programming language directory).

## To get started you’ll need

* A Kaltura VPaaS Account ([Register here](https://vpaas.kaltura.com/register)).
* Your Kaltura account ID (aka partnerID) and API Admin Secret, which can be found in the KMC under Settings > Integration Settings. 
* An example entry ID, from one of the entries in your KMC Content tab.

### Authentication

The Kaltura Editor expects a Kaltura Session (KS) in 3 postMessage events:

1. In the Initialization phase - used to load the entry that will be edited.
2. After a new Clip or Quiz video entry was created - in order to reset the KS to the newly created entry permissions.
3. During preview - to set the permissions to an entitled anonymous viewer.

#### Kaltura Session privileges considerations:

##### Edit KS:

* Make sure to pass a valid `userId` that will represent the correct `userId` in your system. This `userId` will own the newly created entries (if using Save As or creating a new Quiz). 
* Include the `sview:<entryId>` privilege to bypass any special Access Control limitations.
* To enable editing the loaded entry; Trimming (Trimming will replace the entry source flavor and is an irreversible operation) or editing of an existing In-Video Quiz entry - add the `edit:<entryId>` privilege.
* To only enable the creation of new entries (either Clipping or creating a new Quiz) - do **NOT** add the `edit:<entryId>` privilege.

##### Preview KS:

* Pass an empty string as `userId` when creating the session, in order to preview the edited entry anonymously (this will ensure that when answering a quiz during preview it will not register as a real user during preview tests). 
* Include the `sview:<entryId>` privilege to bypass any special Access Control limitations.
* Include the `disableentitlementforentry:<entryId>` to bypass special entitlement settings for the preview session.
* Include the `setrole:PLAYBACK_BASE_ROLE` privilege so that this session will not be allowed to perform actions other than the playback actions needed to preview the new entry.


## Editor App Initialization

In your code, where the editor iFrame is embedded, set up a listener to postMessages to communicate with the editor API:

```javascript
var initParamsListener = window.addEventListener('message', function(e) {
    var postMessageData;
    try {
        postMessageData = e.data;
    }
    catch(ex) {
        return;
    }
```  

> `postMessageData` will contain the postMessage event name to handle (`messageType`) and any relevant payload `data`.

When the editor will be ready, it will trigger the `kea-bootstrap` postMessage event. Catch that event, and in response, call the `kea-config` postMessage to pass the Initialization Params to the editor app:


```javascript
    if(postMessageData.messageType === 'kea-bootstrap') {
        e.source.postMessage({
            'messageType': 'kea-config', 
            'data': { /* ADD HERE ALL THE EDITOR INITIALIZATION PARAMS */ }
        }, e.origin)
    }
```

### Supported initialization parameters

In the data attribute of the `kea-config` postMessage, you'll need to pass the initialization parameters. Below is a detailed review of all available parameters.

#### Mandatory Base Parameters

* `service_url`: The URL of the Kaltura service to use when making API requests (base API endpoint), typically `https://www.kaltura.com/`.
* `partner_id`: The Kaltura account id (aka partnerId) to use.
* `ks`: The generated Kaltura Session string that the editor will authenticate with. 
* `entry_id`: The id of the video entry to edit (Trim or Edit a Quiz) or clone (create a new Clip from or clone for a new Quiz).
* `player_uiconf_id`: The Kaltura Player instance uiconf ID to be used for the editing view player (you can find it in the KMC Studio tab). Make sure that the player you're using is **NOT** configured to Auto Play (make sure that `autoPlay=false` in the player config or use the Studio to edit the player and uncheck Auto Play in the main settings). 

#### Mandatory Navigation Params 

`tabs`: The editor application tabs to display and their respective permissions to enable. 

##### Supported tabs

* `quiz` - Will enable the Quiz creation/editing tab. Supported permissions:
* `edit` - Will the video Trimming and Clipping tab. Supported permissions are: `'clip'` (enables "Save As" to create a new video clip) and `'trim'` (enables "Save" to modify the loaded video entry source flavor).

The following example shows a tabs configuration of all available tabs and permissions (Quiz, Clipping and Trimming):


```javascript
{ 
    'quiz': { 
        name: 'quiz', 
        permissions: ['quiz'], 
        userPermissions: ['quiz'] 
    }, 
    'edit': { 
        name: 'edit', 
        permissions: ['clip', 'trim'], 
        userPermissions: ['clip', 'trim'] 
    } 
}
```  
 
##### The initial tab

The **`tab`** parameter: The initial tab to start the current application session on: 

* `quiz` for Quiz editing.
* `editor` for the Clip/Trim tab).

#### Customization Parameters

* `help_link`: A full URL to be used for the "Go to User Manual" in the editor's help component (you can use the default guide as reference: `https://knowledge.kaltura.com/node/1912`).
* `css_url`: A full URL to an additional CSS file to override style rules.
* `preview_player_uiconf_id`: The Kaltura Player instance to be used for preview. If not passed, the main player will be used.  
* `load_thumbnail_with_ks`: A boolean (default is `false`) denoting whether to append a KS to the thumbnails url, in the event your account is configured to require a KS for thumbnail URLs (not recommended, as this will prevent thumbnail caching).

#### Language Locale Parameters 

The language is set by priority: 

1. `locale_url` - Custom locale file, full url to a json file with translations. 
2. `language_code` - Supported language code (e.g. `en`). 
3. English (`language_code=en`) is used as default locale (if nothing else is configured). 

## New-Entry and Preview Kaltura Sessions

Since generating sessions should only be done on the server side (to not expose your secret API keys or credentials) - we recommend creating a backend service that will be called when the editor requests a new KS and returns the needed KS for each event.

* `postMessageData.data.entryId` will contain the ID of the newly created entry (pass it to your KS generation service, and return a KS with sview and edit privileges accordingly) .
* Upon getting the new KS from your backend service, use postMessage with `messageType: 'kea-ks'` to set the new KS to the editor.
* If you will not provide a KS in response to these events, the editor app will continue to use the same KS that was provided during initialization.

```javascript
if (postMessageData.messageType === 'kea-get-ks' || 
    postMessageData.messageType === 'kea-get-preview-ks')  
{
    var getKsUrl = 'https://example.com/editor-ks-service/' + '?entryId=' + postMessageData.data.entryId + '&action=' + postMessageData.messageType;
    $.getJSON( getKsUrl, null )
    .done(function( responseData ) {
        e.source.postMessage({
            messageType: 'kea-ks',
            data: responseData.ks
        }, e.origin);
    })
    .fail(function( jqxhr, textStatus, error ) {
        var err = textStatus + ", " + error;
        console.log( "Get KS for Edit Request Failed: " + err );
    });
}
```

### The Renew KS event types

* `kea-get-ks` - After a new Clip or Quiz video entry was created, in order to reset the KS to the newly created entry permissions.
* `kea-get-preview-ks` - When the user asks to preview the entry, to set the permissions to an entitled anonymous viewer. 



## The activity postMessage events

These postMessage events will fire as response to use actions in the editor app. Handle these events in your hosting application to continue the workflow between the editor and your application. 
For example: 

* Close the editor once the user completed editing).
* Present localized messages to the user in response to their actions inside the editor.


### `kea-trimming-started`  

Sent when initiating a Trim action. `postMessageData.data.entryId` will hold the ID of the entry being trimmed.
Expected response: a `kea-trim-message` where `message.data` is the (localized) text to display.  

```javascript
if(postMessageData.messageType === 'kea-trimming-started') {
    e.source.postMessage({
        messageType: 'kea-trim-message',
        data: 'You must approve the media replacement in order to be able to watch the trimmed media'
    }, e.origin)
}
```

### `kea-trimming-done`  

Sent when a Trim action is complete. `postMessageData.data.entryId` will hold the ID of the entry that was trimmed.

```javascript
if(postMessageData.messageType === 'kea-trimming-done') {
    console.log('processing of entry with id ' + message.data.entryId + ' is complete');
}
```

### `kea-clip-created`  

Sent upon clip creation. 
The `data` attribute holds the original Entry ID, the ID of the new clip and the name of the new entry. 
Expected response: a `kea-clip-message` where `message.data` is the (localized) text to display to the user after the new clip was created.  

```javascript
if (postMessageData.messageType === 'kea-clip-created') {
    var message = 'A new video clip named "' + postMessageData.data.newEntryName + '" (id: ' + postMessageData.data.newEntryId + ') was created from ' + postMessageData.data.originalEntryId;
    e.source.postMessage({
        'messageType': 'kea-clip-message',
        'data': message
    }, e.origin);
}
```

### `'kea-quiz-updated`

Sent when an In-Video Quiz entry was update. `message.data` will include the entryId of the Kaltura Entry that was updated. 

```javascript
if (postMessageData.messageType === 'kea-quiz-updated') {
    // do something (you can also ignore it), you can invalidate cache, etc..
}
```

### `kea-quiz-created`

Sent when a new In-Video Quiz entry was created. `message.data` will include the entryId of the Kaltura Entry that was created. 

```javascript
if (postMessageData.messageType === 'kea-quiz-created') {
    // do something (you can also ignore it), you can invalidate cache, etc..
}
```

### `kea-get-display-name`  

Request to get the user's display name for the owner of the loaded entry. The `data` attribute holds the relevant user id.
Expected response: a `kea-display-name` where `message.data` is the user display name to show in the editor app.

```javascript
if (postMessageData.messageType === 'kea-get-display-name') {
    //use the userId to get display name from your service
    var getUserDisplaynameUrl = 'kedit-displayname-service/?userId=' + postMessageData.data.userId;
    $.getJSON( getKsUrl, null )
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
```


### `kea-go-to-media`  

Sent when a user clicks the "Go to media" button after a quiz was created/edited. The `data` attribute holds the entry ID. The host application should navigate to a page displaying the relevant media.    

```javascript
if (postMessageData.messageType === 'kea-go-to-media') {
    console.log ("Redirecting to the new media: " + postMessageData.data);
    var videoPath = "https://example.com/video/"; //replace with your real service path for video playbacl pages
    var redirectUrl = videoPath + "?entryId="postMessageData.data;
    $(location).attr('href', redirectUrl);
}
```

# How you can help (guidelines for contributors) 
Thank you for helping Kaltura grow! If you'd like to contribute please follow these steps:
* Use the repository issues tracker to report bugs or feature requests
* Read [Contributing Code to the Kaltura Platform](https://github.com/kaltura/platform-install-packages/blob/master/doc/Contributing-to-the-Kaltura-Platform.md)
* Sign the [Kaltura Contributor License Agreement](https://agentcontribs.kaltura.org/)

# Where to get help
* Join the [Kaltura Community Forums](https://forum.kaltura.org/) to ask questions or start discussions
* Read the [Code of conduct](https://forum.kaltura.org/faq) and be patient and respectful

# Get in touch
We'd love to hear from you!
You can learn more about Kaltura and start a free trial at: http://corp.kaltura.com    
Contact us via Twitter [@Kaltura](https://twitter.com/Kaltura) or email: community@kaltura.com  

# License and Copyright Information
All code in this project is released under the [AGPLv3 license](http://www.gnu.org/licenses/agpl-3.0.html) unless a different license for a particular library is specified in the applicable library path.   

Copyright © Kaltura Inc. All rights reserved.   
Authors and contributors: See [GitHub contributors list](https://github.com/kaltura/kaltura-editor-application-guide/graphs/contributors).  
