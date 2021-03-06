/* ***** BEGIN LICENSE BLOCK *****
 *   Version: MPL 1.1/GPL 2.0/LGPL 2.1
 *
 * The contents of this file are subject to the Mozilla Public License Version
 * 1.1 (the "License"); you may not use this file except in compliance with
 * the License. You may obtain a copy of the License at
 * http://www.mozilla.org/MPL/
 *
 * Software distributed under the License is distributed on an "AS IS" basis,
 * WITHOUT WARRANTY OF ANY KIND, either express or implied. See the License
 * for the specific language governing rights and limitations under the
 * License.
 *
 * The Original Code is bandwagon.
 *
 * The Initial Developer of the Original Code is
 * Mozilla Corporation.
 * Portions created by the Initial Developer are Copyright (C) 2008
 * the Initial Developer. All Rights Reserved.
 *
 * Contributor(s): David McNamara
 *
 * Alternatively, the contents of this file may be used under the terms of
 * either the GNU General Public License Version 2 or later (the "GPL"), or
 * the GNU Lesser General Public License Version 2.1 or later (the "LGPL"),
 * in which case the provisions of the GPL or the LGPL are applicable instead
 * of those above. If you wish to allow use of your version of this file only
 * under the terms of either the GPL or the LGPL, and not to allow others to
 * use your version of this file under the terms of the MPL, indicate your
 * decision by deleting the provisions above and replace them with the notice
 * and other provisions required by the GPL or the LGPL. If you do not delete
 * the provisions above, a recipient may use your version of this file under
 * the terms of any one of the MPL, the GPL or the LGPL.
 *
 * ***** END LICENSE BLOCK ***** */

const nsISupports = Components.interfaces.nsISupports;
const CLASS_ID = Components.ID("5c896f09-126c-466d-b28a-4e8b87a29916");
const CLASS_NAME = "";
const CONTRACT_ID = "@addons.mozilla.org/bandwagonservice;1";

const Cc = Components.classes;
const Ci = Components.interfaces;

const WindowMediator = Cc["@mozilla.org/appshell/window-mediator;1"];
const Timer = Cc["@mozilla.org/timer;1"];
const ExtensionsManager = Cc["@mozilla.org/extensions/manager;1"];
const Storage = Cc["@mozilla.org/storage/service;1"];
const DirectoryService = Cc["@mozilla.org/file/directory_service;1"];
const ObserverService = Cc["@mozilla.org/observer-service;1"];
const CookieManager = Cc["@mozilla.org/cookiemanager;1"];

const nsIWindowMediator = Ci.nsIWindowMediator;
const nsITimer = Ci.nsITimer;
const nsIExtensionManager = Ci.nsIExtensionManager;
const mozIStorageService = Ci.mozIStorageService;
const nsIProperties = Ci.nsIProperties;
const nsIFile = Ci.nsIFile;
const nsIObserverService = Ci.nsIObserverService;
const nsICookieManager = Ci.nsICookieManager;

var Bandwagon;
var bandwagonService;

var gEmGUID;
var gUninstallObserverInited = false;

/* Restore settings added or changed by the extension:
 *  - extension preferences
 *  - logins stored in the Login Manager?
 */
function cleanupSettings()
{
  // Cleanup preferences
  var prefs = Components.classes["@mozilla.org/preferences-service;1"]
                        .getService(Components.interfaces.nsIPrefBranch);
  try {
    prefs.deleteBranch("extensions.bandwagon");
  }
  catch(e) {}
}

function BandwagonService()
{
    this.wrappedJSObject = this;
    gEmGUID = "sharing@addons.mozilla.org";
}

BandwagonService.prototype = {

    collections: {},

    _initialized: false,
    _service: null,
    _collectionUpdateObservers: [],
    _collectionListChangeObservers: [],
    _authenticationStatusChangeObservers: [],
    _storageConnection: null,
    _collectionFactory: null, 
    _collectionUpdateTimer: null,
    _bwObserver: null,
    _serviceDocument: null,

    init: function()
    {
        if (this._initialized)
            return;

        // get access to Bandwagon.* singletons

        var browserWindow = WindowMediator.getService(nsIWindowMediator).getMostRecentWindow("navigator:browser");

        if (!browserWindow || !browserWindow.Bandwagon)
        {
            debug("Bandwagon: could not get access to Bandwagon singletons from last window");
            return;
        }

        Bandwagon = browserWindow.Bandwagon;
        bandwagonService = this;

        Bandwagon.Logger.info("Initializing Bandwagon");

        this._initAMOHost();

        // init rpc service

        this._service = new Bandwagon.RPC.Service();
        this._service.registerLogger(Bandwagon.Logger);
        this._service.registerObserver(this._getCollectionObserver);
        this._service.registerObserver(this._getServiceDocumentObserver);

        this.registerCollectionUpdateObserver(this._collectionUpdateObserver);
        // init sqlite storage (also creating tables in sqlite if needed). create factory objects.

        this._initStorage();

        // first run stuff

        if (Bandwagon.Preferences.getPreference("firstrun") == true)
        {
            Bandwagon.Preferences.setPreference("firstrun", false);
            this.firstrun();
        }

        // storage initialized, tables created - open the collections and service document
        
        this._initCollections();

        // start the update timer

        this._initUpdateTimer();

        // observe when the app shuts down so we can uninit

        ObserverService.getService(nsIObserverService).addObserver(this._bwObserver, "quit-application", false);

        // kick off the auto-publish functionality

        this.autopublishExtensions();

        this._initialized = true;

        Bandwagon.Logger.info("Bandwagon has been initialized");
    },

    /** 
     * Update "constants" to reflect amo_host in preferences
     */
    _initAMOHost: function()
    {
        var amoHost = Bandwagon.Preferences.getPreference("amo_host");

        Bandwagon.RPC.Constants.BANDWAGON_RPC_SERVICE_DOCUMENT = Bandwagon.RPC.Constants.BANDWAGON_RPC_SERVICE_DOCUMENT.replace("%%AMO_HOST%%", amoHost);
        Bandwagon.LOGINPANE_DO_NEW_ACCOUNT = Bandwagon.LOGINPANE_DO_NEW_ACCOUNT.replace("%%AMO_HOST%%", amoHost);
        Bandwagon.COLLECTIONSPANE_DO_SUBSCRIBE_URL = Bandwagon.COLLECTIONSPANE_DO_SUBSCRIBE_URL.replace("%%AMO_HOST%%", amoHost);
        Bandwagon.COLLECTIONSPANE_DO_NEW_COLLECTION_URL = Bandwagon.COLLECTIONSPANE_DO_NEW_COLLECTION_URL.replace("%%AMO_HOST%%", amoHost);
        Bandwagon.FIRSTRUN_LANDING_PAGE = Bandwagon.FIRSTRUN_LANDING_PAGE.replace("%%AMO_HOST%%", amoHost);
        Bandwagon.AMO_AUTH_COOKIE_HOST = Bandwagon.AMO_AUTH_COOKIE_HOST.replace("%%AMO_HOST%%", amoHost);
    },

    _initCollections: function()
    {
        var storageCollections = this._collectionFactory.openCollections();

        for (var id in storageCollections)
        {
            this.collections[id] = storageCollections[id];
            this.collections[id].setAllNotified();

            if (this.collections[id].isLocalAutoPublisher())
            {
                this.collections[id].autoPublishExtensions = Bandwagon.Preferences.getPreference("local.autopublisher.publish.extensions");
                this.collections[id].autoPublishThemes = Bandwagon.Preferences.getPreference("local.autopublisher.publish.themes");
                this.collections[id].autoPublishDicts = Bandwagon.Preferences.getPreference("local.autopublisher.publish.dictionaries");
                this.collections[id].autoPublishLangPacks = Bandwagon.Preferences.getPreference("local.autopublisher.publish.language.packs");
                this.collections[id].autoPublishDisabled = !Bandwagon.Preferences.getPreference("local.autopublisher.only.publish.enabled");
            }

            Bandwagon.Logger.debug("opened collection from storage: " + id);
        }

        this._serviceDocument = this._collectionFactory.openServiceDocument();
        this._service._serviceDocument = this._serviceDocument;

        if (!this._serviceDocument)
        {
            // no service document in storage, we never had it or we've lost it - go fetch it
            this.updateCollectionsList();
        }
    },

    _initUpdateTimer: function()
    {
        this._bwObserver = 
        {
            observe: function(aSubject, aTopic, aData)
            {
                if (aTopic == "timer-callback")
                {
                    bandwagonService.checkAllForUpdates();
                }
                else if (aTopic == "quit-application")
                {
                    bandwagonService.uninit();
                }
            }
        };

        this._collectionUpdateTimer = Timer.createInstance(nsITimer);
        this._collectionUpdateTimer.init(
            this._bwObserver,
            (Bandwagon.Preferences.getPreference("debug")?120*1000:Bandwagon.COLLECTION_UPDATE_TIMER_DELAY*1000),
            nsITimer.TYPE_REPEATING_SLACK
            );
    },

    uninit: function()
    {
        this._collectionUpdateTimer = null;
        this.commitAll();
        this._service = null;
        this._collectionFactory = null;
        Bandwagon = null;
        bandwagonService = null;
    },

    getLocalAutoPublisher: function()
    {
        for (var id in bandwagonService.collections)
        {
            if (bandwagonService.collections[id].isLocalAutoPublisher())
            {
                return bandwagonService.collections[id];
            }
        }

        return null;
    },

    autopublishExtensions: function(callback)
    {
        Bandwagon.Logger.debug("in autopublishExtensions()");

        var localAutoPublisher = bandwagonService.getLocalAutoPublisher();

        if (localAutoPublisher == null)
        {
            Bandwagon.Preferences.setPreferenceList("autopublished.extensions", []);
            return;
        }

        var internalCallback = function(event)
        {
            if (!event.isError())
            {
                bandwagonService._notifyCollectionUpdateObservers(localAutoPublisher);
            }

            if (callback)
            {
                callback(event);
            }
        }

        var installedExtensions = Bandwagon.Util.getInstalledExtensions();
        var autopublishedExtensions = Bandwagon.Preferences.getPreferenceList("autopublished.extensions");
        var willAutopublishExtensions = [];

        for (var i=0; i<installedExtensions.length; i++)
        {
            //Bandwagon.Logger.debug("checking addon '" + installedExtensions[i].id + "' against user auto pub prefs (type=" +  installedExtensions[i].type + ")");

            // check if user wants to publish this extension (enabled, type)
            
            if ((
                Bandwagon.Util.getExtensionProperty(installedExtensions[i].id, "isDisabled") == "true"
                ||
                Bandwagon.Util.getExtensionProperty(installedExtensions[i].id, "appDisabled") == "true"
                ||
                Bandwagon.Util.getExtensionProperty(installedExtensions[i].id, "userDisabled") == "true"
                )
                && !localAutoPublisher.autoPublishDisabled)
            {
                //Bandwagon.Logger.debug("addon '" + installedExtensions[i].id + "' is disabled, so won't publish");
                continue;
            }

            if (installedExtensions[i].type & installedExtensions[i].TYPE_EXTENSION 
                && !localAutoPublisher.autoPublishExtensions)
            {
                //Bandwagon.Logger.debug("addon '" + installedExtensions[i].id + "' is an extension, so won't publish");
                continue;
            }

            if (installedExtensions[i].type & installedExtensions[i].TYPE_THEME 
                && !localAutoPublisher.autoPublishThemes)
            {
                //Bandwagon.Logger.debug("addon '" + installedExtensions[i].id + "' is a theme, so won't publish");
                continue;
            }

            if (installedExtensions[i].type & installedExtensions[i].TYPE_LOCALE 
                && !localAutoPublisher.autoPublishLangPacks)
            {
                //Bandwagon.Logger.debug("addon '" + installedExtensions[i].id + "' is a locale, so won't publish");
                continue;
            }

            /** TODO
            if (installedExtensions[i].type & installedExtensions[i].TYPE_DICT 
                && !localAutoPublisher.autoPublishDicts)
            {
                Bandwagon.Logger.debug("addon '" + installedExtensions[i].id + "' is a dict, so won't publish");
                continue;
            }
            */

            // check if we have already published this extension
            
            var hasPublished = false;

            for (var j=0; j<autopublishedExtensions.length; j++)
            {
                if (installedExtensions[i].id == autopublishedExtensions[j])
                {
                    hasPublished = true;
                    break;
                }
            }

            if (hasPublished == false)
            {
                //Bandwagon.Logger.debug("addon '" + installedExtensions[i].id + "' added to auto-publish queue");
                willAutopublishExtensions.push(installedExtensions[i]);
            }
            else
            {
                //Bandwagon.Logger.debug("addon '" + installedExtensions[i].id + "' has already been published");
            }
        }

        if (willAutopublishExtensions.length > 0)
        {
            for (var i=0; i<willAutopublishExtensions.length; i++)
            {
                //Bandwagon.Logger.debug("Will autopublish extension '" + willAutopublishExtensions[i].id + "' to collection '" + localAutoPublisher.resourceURL + "'");

                var extension =
                {
                    guid: willAutopublishExtensions[i].id,
                    name: willAutopublishExtensions[i].name
                }

                bandwagonService.publishToCollection(extension, localAutoPublisher, "", internalCallback);

                // add to autopublish
                autopublishedExtensions.push(willAutopublishExtensions[i].id);
            }

            Bandwagon.Preferences.setPreferenceList("autopublished.extensions", autopublishedExtensions);
        }
    },

    _getCollectionObserver: function(event)
    {
        Bandwagon.Logger.info("in _getCollectionObserver()");

        if (event.getType() == Bandwagon.RPC.Constants.BANDWAGON_RPC_EVENT_TYPE_BANDWAGON_RPC_GET_COLLECTION_COMPLETE)
        {
            var collection = event.collection;

            if (event.isError())
            {
                Bandwagon.Logger.error("RPC error: '" + event.getError().getMessage() + "'");
    
                if (event.getError().getCode() == Bandwagon.RPC.Constants.BANDWAGON_RPC_SERVICE_ERROR_UNAUTHORIZED)
                {
                    bandwagonService.deauthenticate();
                }

                // otherwise ignore for now
            }
            else
            {
                if (collection != null && collection.resourceURL != null)
                {
                    Bandwagon.Logger.info("Finished getting updates for collection '" + collection.resourceURL + "'");
                    bandwagonService.collections[collection.resourceURL] = collection;
                }
            }

            // we want to notify the observers even if there's been an error

            bandwagonService._notifyCollectionUpdateObservers(collection);
        }
    },

    _getServiceDocumentObserver: function(event)
    {
        Bandwagon.Logger.info("in _getServiceDocumentObserver()");

        if (event.getType() == Bandwagon.RPC.Constants.BANDWAGON_RPC_EVENT_TYPE_BANDWAGON_RPC_GET_SERVICE_DOCUMENT_COMPLETE)
        {
            if (event.isError())
            {
                Bandwagon.Logger.error("Could not update collections list: " + event.getError().toString());

                if (event.getError().getCode() == Bandwagon.RPC.Constants.BANDWAGON_RPC_SERVICE_ERROR_UNAUTHORIZED)
                {
                    bandwagonService.deauthenticate();
                }
            }
            else
            {
                bandwagonService._serviceDocument = event.serviceDocument;
                bandwagonService._service._serviceDocument = bandwagonService._serviceDocument;

                var collections = bandwagonService._serviceDocument.collections;

                Bandwagon.Logger.debug("Updating collections list: saw " + collections.length + " collections");

                for (var id in bandwagonService.collections)
                {
                    var isStaleCollection = true;

                    for (var jd in collections)
                    {
                        if (bandwagonService.collections[id].equals(collections[jd]))
                        {
                            isStaleCollection = false;
                            break;
                        }
                    }

                    if (isStaleCollection)
                    {
                        Bandwagon.Logger.debug("Updating collections list: removing stale collection: " + bandwagonService.collections[id].toString());

                        bandwagonService.unlinkCollection(bandwagonService.collections[id]);
                    }
                }

                for (var id in collections)
                {
                    var collection = collections[id];

                    if (bandwagonService.collections[collection.resourceURL])
                    {
                        // we have already added this collection
                    }
                    else
                    {
                        // this is a new collection
                        Bandwagon.Logger.debug("Updating collections list: adding new collection: " + collection.toString());

                        bandwagonService.collections[collection.resourceURL] = collection;
                    }
                }

                bandwagonService.forceCheckAllForUpdates();
                
                bandwagonService._notifyListChangeObservers();

                if (Bandwagon.COMMIT_NOW)
                    bandwagonService.commitAll();
            }
        }
    },

    _notifyCollectionUpdateObservers: function(collection)
    {
        Bandwagon.Logger.debug("Notifying collection update observers");

        for (var i=0; i<bandwagonService._collectionUpdateObservers.length; i++)
        {
            if (bandwagonService._collectionUpdateObservers[i])
            {
                bandwagonService._collectionUpdateObservers[i](collection);
            }
        }
    },

    registerCollectionUpdateObserver: function(observer)
    {
        Bandwagon.Logger.debug("Registering collection update observer");
        this._collectionUpdateObservers.push(observer);
    },

    unregisterCollectionUpdateObserver: function(observer)
    {
        Bandwagon.Logger.debug("Unregistering collection update observer");

        for (var i=0; i<this._collectionUpdateObservers.length; i++)
        {
            if (this._collectionUpdateObservers[i] == observer)
            {
                delete this._collectionUpdateObservers[i];
            }
        }
    },

    _notifyAuthenticationStatusChangeObservers: function()
    {
        Bandwagon.Logger.debug("Notifying authentication status change observers");

        for (var i=0; i<bandwagonService._authenticationStatusChangeObservers.length; i++)
        {
            if (bandwagonService._authenticationStatusChangeObservers[i])
            {
                bandwagonService._authenticationStatusChangeObservers[i]();
            }
        }
    },

    registerAuthenticationStatusChangeObserver: function(observer)
    {
        Bandwagon.Logger.debug("Registering authentication status change observer");
        this._authenticationStatusChangeObservers.push(observer);
    },

    unregisterAuthenticationStatusChangeObserver: function(observer)
    {
        Bandwagon.Logger.debug("Unregistering authentication status change observer");

        for (var i=0; i<this._authenticationStatusChangeObservers.length; i++)
        {
            if (this._authenticationStatusChangeObservers[i] == observer)
            {
                delete this._authenticationStatusChangeObservers[i];
            }
        }
    },

    _notifyListChangeObservers: function()
    {
        Bandwagon.Logger.debug("Notifying collection list change observers");

        for (var i=0; i<bandwagonService._collectionListChangeObservers.length; i++)
        {
            if (bandwagonService._collectionListChangeObservers[i])
            {
                bandwagonService._collectionListChangeObservers[i]();
            }
        }
    },

    registerCollectionListChangeObserver: function(observer)
    {
        Bandwagon.Logger.debug("Registering collection list change observer");
        this._collectionListChangeObservers.push(observer);
    },

    unregisterCollectionListChangeObserver: function(observer)
    {
        Bandwagon.Logger.debug("Unregistering collection list change observer");

        for (var i=0; i<this._collectionListChangeObservers.length; i++)
        {
            if (this._collectionListChangeObservers[i] == observer)
            {
                delete this._collectionListChangeObservers[i];
            }
        }
    },

    authenticate: function(login, password, callback)
    {
        Bandwagon.Logger.debug("in authenticate()");

        var service = this;

        Bandwagon.Preferences.setPreference(Bandwagon.PREF_AUTH_TOKEN, "");
        Bandwagon.Preferences.setPreference("login", "");
        this.deleteAMOCookie();

        var internalCallback = function(event)
        {
            if (!event.isError())
            {
                Bandwagon.Preferences.setPreference("login", login);

                service._notifyAuthenticationStatusChangeObservers();
            }

            if (callback)
                callback(event);
        }

        this._service.authenticate(login, password, internalCallback);
    },

    deauthenticate: function(callback)
    {
        Bandwagon.Preferences.setPreference(Bandwagon.PREF_AUTH_TOKEN, "");
        Bandwagon.Preferences.setPreference("login", "");

        this._notifyAuthenticationStatusChangeObservers();

        if (callback)
            callback();
    },

    updateCollectionsList: function(callback)
    {
        Bandwagon.Logger.debug("Updating collections list...");

        this.updateServiceDocument(callback);
    },

    updateServiceDocument: function(callback)
    {
        if (!this.isAuthenticated())
            return;

        this._service.getServiceDocument(callback);
    },

    checkForUpdates: function(collection)
    {
        if (!this.isAuthenticated())
            return;

        this._service.getCollection(collection);

        var now = new Date();

        collection.dateLastCheck = now;
    },

    checkAllForUpdates: function()
    {
        Bandwagon.Logger.debug("in checkAllForUpdates()");

        var now = new Date();

        for (var id in this.collections)
        {
            var collection = this.collections[id];

            if (collection.updateInterval == -1)
            {
                // use global setting
                
                var dateLastCheck = new Date(Bandwagon.Preferences.getPreference("updateall.datelastcheck")*1000);
                var dateNextCheck = new Date(dateLastCheck.getTime() + Bandwagon.Util.intervalUnitsToMilliseconds(
                    Bandwagon.Preferences.getPreference("global.update.interval"),
                    Bandwagon.Preferences.getPreference("global.update.units")
                    ));

                if (dateNextCheck.getTime() > now.getTime())
                {
                    return;
                }
                else
                {
                    this.checkForUpdates(collection);
                }
            }
            else
            {
                // use per-collection setting
                
                var dateLastCheck = null;
                var dateNextCheck = null;

                if (collection.dateLastCheck != null)
                {
                    dateLastCheck = collection.dateLastCheck;
                    dateNextCheck = new Date(dateLastCheck.getTime() + collection.updateInterval*1000);
                }
                else
                {
                    dateLastCheck = null;
                    dateNextCheck = now;
                }

                if (dateLastCheck == null || dateNextCheck.getTime() <= now.getTime())
                {
                    this.checkForUpdates(collection);
                }
            }
        }

        Bandwagon.Preferences.setPreference("updateall.datelastcheck", now.getTime()/1000);
    },

    forceCheckForUpdates: function(collection)
    {
        if (!this.isAuthenticated())
            return;

        this._service.getCollection(collection);
        collection.dateLastCheck = new Date();
    },

    forceCheckAllForUpdates: function()
    {
        Bandwagon.Logger.debug("in forceCheckAllForUpdates()");

        for (var id in this.collections)
        {
            var collection = this.collections[id];
            this.forceCheckForUpdates(collection);
        }
    },

    forceCheckAllForUpdatesAndUpdateCollectionsList: function(callback)
    {
        // All updates to the collections list are forced, i.e. they are always
        // caused by *some* user interaction, never in the background.
        // Updating the collections list also forces the collections to be updated.
        
        this.updateCollectionsList(callback);
    },

    firstrun: function()
    {
        Bandwagon.Logger.info("This is bandwagon's firstrun. Welcome!");

        // the last check date is now

        var now = new Date();
        Bandwagon.Preferences.setPreference("updateall.datelastcheck", now.getTime()/1000);

        // open the firstrun landing page

        Bandwagon.Controller.BrowserOverlay.openFirstRunLandingPage();
    },

    _addDefaultCollection: function(url, name)
    {
        var collection = this._collectionFactory.newCollection();
        collection.resourceURL = url;
        collection.name = name;
        collection.showNotifications = 0;

        this.collections[collection.resourceURL] = collection;

        if (Bandwagon.COMMIT_NOW)
            this.commit(collection);

        this.forceCheckForUpdates(collection);
        this.subscribe(collection);
    },

    uninstall: function()
    {
        // TODO
    },

    commit: function(collection)
    {
        if (!bandwagonService._collectionFactory)
            return;

        Bandwagon.Logger.debug("In commit() with collection: " + collection.resourceURL);

        bandwagonService._collectionFactory.commitCollection(collection);
    },

    commitAll: function()
    {
        Bandwagon.Logger.debug("In commitAll()");

        for (var id in bandwagonService.collections)
        {
            var collection = bandwagonService.collections[id];

            this.commit(collection);
        }

        if (bandwagonService._serviceDocument)
            bandwagonService._collectionFactory.commitServiceDocument(bandwagonService._serviceDocument);
    },

    removeAddonFromCollection: function(guid, collection, callback)
    {
        Bandwagon.Logger.debug("In removeAddonFromCollection()");

        if (!this.isAuthenticated())
            return;

        this._service.removeAddonFromCollection(guid, collection, callback);
    },

    newCollection: function(collection, callback)
    {
        Bandwagon.Logger.debug("In newCollection()");

        if (!this.isAuthenticated())
            return;

        var internalCallback = function(event)
        {
            if (!event.isError())
            {
                var collection = event.collection;

                bandwagonService.collections[collection.resourceURL] = collection;
                //bandwagonService._notifyCollectionUpdateObservers(collection);
                bandwagonService._notifyListChangeObservers();
            }

            if (callback)
            {
                callback(event);
            }
        }

        this._service.newCollection(collection, internalCallback);
    },

    unlinkCollection: function(collection)
    {
        this._collectionFactory.deleteCollection(collection);

        for (var id in bandwagonService.collections)
        {
            if (collection.equals(bandwagonService.collections[id]))
            {
                delete bandwagonService.collections[id];

                bandwagonService._notifyListChangeObservers();

                break;
            }
        }
    },

    deleteCollection: function(collection, callback)
    {
        if (!this.isAuthenticated())
            return;

        this._service.deleteCollection(collection, callback);
    },

    subscribeToCollection: function(collection, callback)
    {
        if (!this.isAuthenticated())
            return;

        var internalCallback = function(event)
        {
            if (!event.isError())
            {
                collection.subscribed = true;
                bandwagonService._notifyListChangeObservers();
            }

            if (callback)
            {
                callback(event);
            }
        }

        this._service.subscribeToCollection(collection, internalCallback);
    },

    unsubscribeFromCollection: function(collection, callback)
    {
        if (!this.isAuthenticated())
            return;

        var internalCallback = function(event)
        {
            if (!event.isError())
            {
                bandwagonService.unlinkCollection(collection);
            }

            if (callback)
            {
                callback(event);
            }
        }

        this._service.unsubscribeFromCollection(collection, internalCallback);
    },

    updateCollectionDetails: function(collection, callback)
    {
        if (!this.isAuthenticated())
            return;

        this._service.updateCollectionDetails(collection, callback);
    },

    getAddonsPerPage: function(collection)
    {
        // returns this collection's custom items per page, or else the global value

        var addonsPerPage;
        
        if (collection.addonsPerPage != -1)
        {
            addonsPerPage = collection.addonsPerPage;
        }
        else
        {
            addonsPerPage = Bandwagon.Preferences.getPreference("global.addonsperpage");
        }

        if (addonsPerPage < 1)
        {
            addonsPerPage = 1;
        }

        return addonsPerPage;
    },

    getPreviouslySharedEmailAddresses: function()
    {
        return Bandwagon.Preferences.getPreferenceList("publish.shared.emails");
    },

    clearPreviouslySharedEmailAddresses: function()
    {
        Bandwagon.Preferences.setPreferenceList("publish.shared.emails", []);
    },

    addPreviouslySharedEmailAddress: function(emailAddress)
    {
        emailAddress = emailAddress.replace(/^\s+/, "");
        emailAddress = emailAddress.replace(/\s+$/, "");

        var previouslySharedEmailAddresses = this.getPreviouslySharedEmailAddresses();

        for (var i=0; i<previouslySharedEmailAddresses.length; i++)
        {
            if (previouslySharedEmailAddresses[i] == emailAddress)
            {
                return;
            }
        }

        previouslySharedEmailAddresses.push(emailAddress);

        Bandwagon.Preferences.setPreferenceList("publish.shared.emails", previouslySharedEmailAddresses);
    },

    addPreviouslySharedEmailAddresses: function(commaSeparatedEmailAddresses)
    {
        var bits = commaSeparatedEmailAddresses.split(",");

        for (var i=0; i<bits.length; i++)
        {
            this.addPreviouslySharedEmailAddress(bits[i]);
        }
    },

    publishToCollection: function(extension, collection, personalNote, callback)
    {
        if (!this.isAuthenticated())
            return;

        this._service.publishToCollection(extension, collection, personalNote, callback);
    },

    shareToEmail: function(extension, emailAddress, personalNote, callback)
    {
        if (!this.isAuthenticated())
            return;

        this._service.shareToEmail(extension, emailAddress, personalNote, callback);
    },

    /**
     * Performs a 'soft' check for authenication. I.e. do we have a token from a previous auth. This method doesn't
     * check if that token is still valid on the server.
     */
    isAuthenticated: function()
    {
        return (Bandwagon.Preferences.getPreference(Bandwagon.PREF_AUTH_TOKEN) != "");
    },

    deleteAMOCookie: function()
    {
        var cm = CookieManager.getService(nsICookieManager);

        var iterator = cm.enumerator;

        while (iterator.hasMoreElements())
        {
            var cookie = iterator.getNext();

            if (cookie instanceof Ci.nsICookie)
            {
                if (cookie.host == Bandwagon.AMO_AUTH_COOKIE_HOST && cookie.name == Bandwagon.AMO_AUTH_COOKIE_NAME)
                {
                    // KILL!
                    cm.remove(cookie.host, cookie.name, cookie.path, false);
                }
            }
        }
    },

    _collectionUpdateObserver: function(collection)
    {
        // called when a collection is updated

        // if there are new items, notify the user if notifications are enabled for this user and it's not a preview of a collection

        Bandwagon.Logger.debug("in _collectionUpdateObserver() with collection '" + collection + "', unnotified collection items = " + collection.getUnnotifiedAddons().length)

        var showNotificationsForThisCollection;

        if (collection.showNotifications == -1)
        {
            showNotificationsForThisCollection = Bandwagon.Preferences.getPreference("global.notify.enabled");
        }
        else
        {
            showNotificationsForThisCollection = collection.showNotifications;
        }

        if (showNotificationsForThisCollection && collection.getUnnotifiedAddons().length > 0)
        {
            var browserWindow = WindowMediator.getService(nsIWindowMediator).getMostRecentWindow("navigator:browser");

            if (browserWindow)
            {
                browserWindow.Bandwagon.Controller.BrowserOverlay.showNewAddonsAlert(collection);
            }
            else
            {
                Bandwagon.Logger.error("Can't find a browser window to notify the user");
            }

            collection.setAllNotified();
        }

        // commit the collection

        if (Bandwagon.COMMIT_NOW)
            bandwagonService.commit(collection);
    },

    _initStorage: function()
    {
        var storageService = Storage.getService(mozIStorageService);

        var file = DirectoryService.getService(nsIProperties).get("ProfD", nsIFile);
        file.append(Bandwagon.EMID);

        if (!file.exists() || !file.isDirectory())
        {
            file.create(nsIFile.DIRECTORY_TYPE, 0777);
        }

        file.append(Bandwagon.SQLITE_FILENAME);

        try
        {
            this._storageConnection = storageService.openUnsharedDatabase(file);
        }
        catch (e)
        {
            Bandwagon.Logger.error("Error opening Storage connection: " + e);
            return;
        }

        this._collectionFactory = new Bandwagon.Factory.CollectionFactory(this._storageConnection);

        this._initStorageTables();
    },

    _initStorageTables: function()
    {
        if (!this._storageConnection)
            return;

        // create tables (if they're not already created)

        this._storageConnection.beginTransaction();

        try
        {
            this._storageConnection.executeSimpleSQL(
                "CREATE TABLE IF NOT EXISTS serviceDocument "
                + "(emailResourceURL TEXT NOT NULL, "
                + "collectionListResourceURL TEXT NOT NULL)"
                );
            this._storageConnection.executeSimpleSQL(
                "CREATE TABLE IF NOT EXISTS collections "
                + "(id INTEGER PRIMARY KEY AUTOINCREMENT, "
                + "url TEXT NOT NULL UNIQUE, "
                + "name TEXT NOT NULL, "
                + "description TEXT, "
                + "dateAdded INTEGER NOT NULL, "
                + "dateLastCheck INTEGER, "
                + "updateInterval INTEGER NOT NULL, "
                + "showNotifications INTEGER NOT NULL, "
                + "autoPublish INTEGER NOT NULL, "
                + "active INTEGER NOT NULL DEFAULT 1, "
                + "addonsPerPage INTEGER NOT NULL, "
                + "creator TEXT, "
                + "listed INTEGER NOT NULL DEFAULT 1, "
                + "writable INTEGER NOT NULL DEFAULT 0, "
                + "subscribed INTEGER NOT NULL DEFAULT 1, "
                + "lastModified INTEGER, "
                + "addonsResourceURL TEXT, "
                + "type TEXT)"
                );
            this._storageConnection.executeSimpleSQL(
                "CREATE TABLE IF NOT EXISTS collectionsLinks "
                + "(id INTEGER PRIMARY KEY AUTOINCREMENT, "
                + "collection INTEGER NOT NULL, "
                + "name TEXT NOT NULL, "
                + "href TEXT NOT NULL)"
                );
            this._storageConnection.executeSimpleSQL(
                "CREATE TABLE IF NOT EXISTS collectionsAddons "
                + "(id INTEGER PRIMARY KEY AUTOINCREMENT, "
                + "collection INTEGER NOT NULL, "
                + "addon INTEGER NOT NULL, "
                + "read INTEGER NOT NULL DEFAULT 0)"
                );
            this._storageConnection.executeSimpleSQL(
                "CREATE TABLE IF NOT EXISTS addons "
                + "(id INTEGER PRIMARY KEY AUTOINCREMENT, "
                + "guid TEXT NOT NULL UNIQUE, "
                + "name TEXT NOT NULL, "
                + "type INTEGER NOT NULL, "
                + "version TEXT NOT NULL, "
                + "status INTEGER NOT NULL, "
                + "summary TEXT, "
                + "description TEXT, "
                + "icon TEXT, "
                + "eula TEXT, "
                + "thumbnail TEXT, "
                + "learnmore TEXT NOT NULL, "
                + "author TEXT, "
                + "category TEXT, "
                + "dateAdded INTEGER NOT NULL)"
                );
            this._storageConnection.executeSimpleSQL(
                "CREATE TABLE IF NOT EXISTS addonCompatibleApplications "
                + "(addon INTEGER NOT NULL, "
                + "name TEXT NOT NULL, "
                + "applicationId INTEGER NOT NULL, "
                + "minVersion TEXT NOT NULL, "
                + "maxVersion TEXT NOT NULL, "
                + "guid TEXT NOT NULL)"
                );
            this._storageConnection.executeSimpleSQL(
                "CREATE TABLE IF NOT EXISTS addonCompatibleOS "
                + "(addon INTEGER NOT NULL, "
                + "name TEXT NOT NULL)"
                );
            this._storageConnection.executeSimpleSQL(
                "CREATE TABLE IF NOT EXISTS addonInstalls "
                + "(addon INTEGER NOT NULL, "
                + "url TEXT NOT NULL, "
                + "hash TEXT NOT NULL, "
                + "os TEXT NOT NULL)"
                );
            this._storageConnection.executeSimpleSQL(
                "CREATE TABLE IF NOT EXISTS addonComments "
                + "(addon INTEGER NOT NULL, "
                + "comment TEXT NOT NULL, "
                + "author TEXT NOT NULL)"
                );
            this._storageConnection.executeSimpleSQL(
                "CREATE TABLE IF NOT EXISTS addonAuthors "
                + "(addon INTEGER NOT NULL, "
                + "author TEXT NOT NULL)"
                );
        }
        catch (e)
        {
            Bandwagon.Logger.error("Error creating sqlite table: " + e);
            this._storageConnection.rollbackTransaction();
            return;
        }

        this._storageConnection.commitTransaction();
    },

    startUninstallObserver : function ()
    {
      if (gUninstallObserverInited) return;

      var extService = Components.classes["@mozilla.org/extensions/manager;1"]
                                 .getService(Components.interfaces.nsIExtensionManager);
      if (extService && ("uninstallItem" in extService)) {
        var observerService = Components.classes["@mozilla.org/observer-service;1"]
                                        .getService(Components.interfaces.nsIObserverService);
        observerService.addObserver(this.addonsAction, "em-action-requested", false);
        gUninstallObserverInited = true;
      } else {
        try {
          extService.datasource.AddObserver(this.addonsObserver);
          gUninstallObserverInited = true;
        } catch (e) { }
      }
    },

    addonsObserver :
    {
      onAssert : function (ds, subject, predicate, target)
      {
        if ((subject.Value == "urn:mozilla:extension:" + gEmGUID)
            &&
            (predicate.Value == "http://www.mozilla.org/2004/em-rdf#toBeUninstalled")
            &&
            (target instanceof Components.interfaces.nsIRDFLiteral)
            &&
            (target.Value == "true"))
        {
          cleanupSettings();
        }
      },

      onUnassert : function (ds, subject, predicate, target) {},
      onChange : function (ds, subject, predicate, oldtarget, newtarget) {},
      onMove : function (ds, oldsubject, newsubject, predicate, target) {},
      onBeginUpdateBatch : function() {},
      onEndUpdateBatch : function() {}
    },

    addonsAction :
    {
      observe : function (subject, topic, data)
      {
        if ((data == "item-uninstalled") &&
            (subject instanceof Components.interfaces.nsIUpdateItem) &&
            (subject.id == gEmGUID))
        {
          cleanupSettings();
        }
      }
    },

    // for nsISupports
    QueryInterface: function(aIID)
    {
        // add any other interfaces you support here
        if (!aIID.equals(nsISupports))
            throw Components.results.NS_ERROR_NO_INTERFACE;
                
        return this;
    }
}

var BandwagonServiceFactory = {
    singleton: null,
    createInstance: function (aOuter, aIID)
    {
        if (aOuter != null)
            throw Components.results.NS_ERROR_NO_AGGREGATION;

        if (this.singleton == null)
            this.singleton = new BandwagonService();

        return this.singleton.QueryInterface(aIID);
    }
};

var BandwagonServiceModule = {
    registerSelf: function(aCompMgr, aFileSpec, aLocation, aType)
    {
        aCompMgr = aCompMgr.QueryInterface(Components.interfaces.nsIComponentRegistrar);
        aCompMgr.registerFactoryLocation(CLASS_ID, CLASS_NAME, CONTRACT_ID, aFileSpec, aLocation, aType);
    },

    unregisterSelf: function(aCompMgr, aLocation, aType)
    {
        aCompMgr = aCompMgr.QueryInterface(Components.interfaces.nsIComponentRegistrar);
        aCompMgr.unregisterFactoryLocation(CLASS_ID, aLocation);        
    },

    getClassObject: function(aCompMgr, aCID, aIID)
    {
        if (!aIID.equals(Components.interfaces.nsIFactory))
            throw Components.results.NS_ERROR_NOT_IMPLEMENTED;

        if (aCID.equals(CLASS_ID))
            return BandwagonServiceFactory;

        throw Components.results.NS_ERROR_NO_INTERFACE;
    },

    canUnload: function(aCompMgr) { return true; }
};

//module initialization
function NSGetModule(aCompMgr, aFileSpec) { return BandwagonServiceModule; }

