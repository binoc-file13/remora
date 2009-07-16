<?php
/* ***** BEGIN LICENSE BLOCK *****
 * Version: MPL 1.1/GPL 2.0/LGPL 2.1
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
 * The Original Code is addons.mozilla.org site.
 *
 * The Initial Developer of the Original Code is
 * The Mozilla Foundation.
 * Portions created by the Initial Developer are Copyright (C) 2008
 * the Initial Developer. All Rights Reserved.
 *
 * Contributor(s):
 *   Ryan Doherty <rdoherty@mozilla.com>
 *   Frederic Wenzel <fwenzel@mozilla.com>
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
class CollectionsController extends AppController
{
    var $name = 'Collections';
    var $beforeFilter = array('checkCSRF', 'getNamedArgs', '_checkSandbox');
    var $uses = array('Addon', 'AddonTag', 'Addontype', 'Application',
        'Feature', 'File', 'Platform', 'Preview', 'Tag', 'Translation', 
        'Review', 'Version', 'Collection');
    var $components = array('Amo', 'Image', 'Pagination', 'Session', 'Userfunc', 'Search');
    var $helpers = array('Html', 'Link', 'Time', 'Localization', 'Ajax', 'Number', 'Pagination', 'Form');
    var $exceptionCSRF = array("/collections/install");
    var $namedArgs = true;

    var $securityLevel = 'low';

    function beforeFilter() {
        // Disable ACLs because this controller is entirely public.
        $this->SimpleAuth->enabled = false;
        $this->SimpleAcl->enabled = false;
        $this->layout='mozilla';
        $this->publish('collapse_categories', true);
        $this->pageTitle = 'Collections' . " :: " . sprintf(_('addons_home_pagetitle'), APP_PRETTYNAME);
        $this->publish('jsAdd', array('jquery.autocomplete.pack.js'), false);
    }
    
    function index() {
          $subscriptions = false;
          if($user = $this->Session->read('User')) {
              $subscriptions = $this->User->getSubscriptions($user['id']);
              $this->publish('subscriptions', $subscriptions);
          }
          
          $collections = $this->Collection->findAll(array('Collection.listed' => '1'));
          $this->publish('collections', $collections);

          
          $userCollections = $this->User->getCollections($user['id']);
          $this->publish('userCollections', $userCollections);
    }
    
    /**
     * Creates a collection if POSTed to, otherwise shows a collection creation form
     */
    function add() {
        $this->Amo->checkLoggedIn(); // must be logged in
        
        if (isset($this->data['Collection'])) {
            $this->Amo->clean($this->data['Collection']);
            $user = $this->Session->read('User');
            $this->data['Collection']['user_id'] = $user['id'];
            $this->data['Collection']['application_id'] = APP_ID; // defaults to current app
            if ($this->Collection->save($this->data)) {
                $collectionid = $this->Collection->id; // new collection id
                $_coll = $this->Collection->findById($collectionid, array('Collection.uuid'));
                
                $this->Collection->addUser($this->Collection->id, $user['id'], COLLECTION_ROLE_OWNER);
                
                if (!empty($this->params['form']['addons'])) {
                    // add-ons preselected
                    $this->Amo->clean($this->params['form']['addons']);
                    foreach ($this->params['form']['addons'] as &$addon) {
                        $this->Collection->addAddonToCollection($collectionid, $user['id'], $addon);
                    }
                }
                
                $this->Session->write('collection_created', $collectionid);
                $this->redirect("/collections/view/{$_coll['Collection']['uuid']}");
                return;
            }
        }
    }
    
    /**
     * Adds an add-on to a collection
     */
    function addtocollection() {
        if($this->data['addon_id'] && $this->data['collection_uuid']) {
            $this->Amo->clean($this->data);
            $collection_id = $this->Collection->getIdForUUID($this->data['collection_uuid']);
            $user = $this->Session->read('User');
            $added = $this->Collection->addAddonToCollection($collection_id, $user['id'], $this->data['addon_id']);
        }
    }
    
    
    function view($uuid = NULL) {  

        if (!$uuid) {
            $this->flash(sprintf(_('error_missing_argument'), 'collection_id'), '/', 3);
            return;
        }
        
        $id = $this->Collection->getIdForUUID($uuid);
        if ($id) {
            $_conditions['Collection.id'] = $id;
        } else {
            $_conditions['Collection.nickname'] = $uuid;
        }

        $this->Collection->unbindFully();
        $collection = $this->Collection->find($_conditions, null, null, 1);
        
        if($collection == null) {
            $this->flash(_('collection_not_found'), '/', 3);
            return;
        }

        $addonIds = $this->Addon->getAddonsFromCollection($collection['Collection']['id']);
     
        $addons = $this->Addon->getAddonList($addonIds,array(
            'all_tags', 'authors', 'compatible_apps', 'files', 'latest_version',
            'list_details'));
        
        foreach($addons as &$addon) {
            $addonId = $addon['Addon']['id'];
            $addon['Addon']['dateadded'] = $this->Addon->getDateAddedToCollection($addonId, $collection['Collection']['id']);
        }
        
        $this->publish('addons', $addons);
        $this->publish('collection', $collection);
        $this->pageTitle = $collection['Translation']['name']['string'] . " :: " . sprintf(_('addons_home_pagetitle'), APP_PRETTYNAME);
        
        // was the collection just created? show success message
        $collection_created = $this->Session->read('collection_created');
        if ($collection_created == $id) $this->Session->delete('collection_created');
        $this->publish('collection_created', ($collection_created == $id));
        
        if (isset($_GET['format']) && $_GET['format'] == 'rss') {
            $this->publish('rss_title', $collection['Translation']['name']['string']);
            $this->publish('rss_description', $collection['Translation']['description']['string']);
            $this->render('rss/collection', 'rss');
        }
    }
    
    /**
     * Subscribes user to the collection
     */
    function subscribe($uuid = NULL) {

        $this->Amo->checkLoggedIn(); // must be logged in
        $id = $this->Collection->getIdForUUID($uuid);
        
        if(!$id || !is_numeric($id)) {
            $this->flash(sprintf(_('error_missing_argument'), 'collection_id'), '/', 3);
            return;
        }
        
        $user = $this->Session->read('User');
        $this->Collection->subscribe($id, $user['id']);
    }
    
    /**
     * Unsubscribe a user from a collection
     */
    function unsubscribe($uuid = NULL) {
        $this->Amo->checkLoggedIn(); // must be logged in
        $id = $this->Collection->getIdForUUID($uuid);
        
        if(!$id || !is_numeric($id)) {
            $this->flash(sprintf(_('error_missing_argument'), 'collection_id'), '/', 3);
            return;
        }
        
        $user = $this->Session->read('User');
        $this->Collection->unsubscribe($id, $user['id']);
    }
    
    /**
     * Edit collection
     */
    function edit($uuid) {
        $this->Amo->checkLoggedIn(); // must be logged in
        $id = $this->Collection->getIdForUUID($uuid);
        
        if(!empty($this->data)) {
            
            switch ($this->data['action']) {
                case 'Update Collection':
                    list($localizedFields, $unlocalizedFields) = $this->Collection->splitLocalizedFields($this->data['Collection']);

                    if(!isset($unlocalizedFields['listed'])) {
                        $unlocalizedFields['listed'] = 0;
                    }

                    $this->Collection->id = $id;
                    $this->Collection->saveTranslations($id, $this->params['form']['data']['Collection'], $localizedFields);
                    $this->Collection->save($unlocalizedFields);
                break;
                
                case 'Add User':
                    $this->Amo->clean($this->data['email']);
                    $user = $this->User->findByEmail($this->data['email']);
                    $this->Amo->clean($this->data['role']);
                    $this->Collection->addUser($id, $user['User']['id'], $this->data['role']);
                break;
                
                case 'Delete user':
                    $this->Amo->clean($this->data['id']);
                    $this->Collection->removeUser($id, $this->data['id']);
                break;
                
                default:
                    # code...
                break;
            }
        }
        
        
        $collection = $this->Collection->findById($id);
        $this->data['Collection'] = $collection;
        
        $this->publish('collection', $collection);
    }
    
    /**
     * Deletes a collection
     */
    function delete($uuid) {
        $this->Amo->checkLoggedIn(); // must be logged in
        $id = $this->Collection->getIdForUUID($uuid);

        if(!$id || !is_numeric($id)) {
            $this->flash(sprintf(_('error_missing_argument'), 'collection_id'), '/', 3);
            return;
        }
        
        $this->Collection->delete($id);
    }
    
    
    /**
     * Special "interactive collections" page for first-time users
     */
    function interactive() {
        // this is Firefox only, for now.
        if (true) {
            $this->redirect('/');
            exit();
        }
        
        // XXX: for accessibility (bug 462411), we use a hand-bundled accordion
        // + core jquery UI file. Once jquery UI is updated to post-1.6.2rc2,
        // the regular jquery UI accordion should be used (cf. jquery ui bug
        // 3553, http://ui.jquery.com/bugs/ticket/3553)
        $this->set('jsAdd', array('jquery-ui/jq-ui-162rc2-accordion-bundle-a11y.min.js', 'jquery-ui/jqModal.js'));
        $this->set('cssAdd', array('collection-style'));
        
        $addonIds = $this->Addon->getCategorizedAddonsFromCollection(1); // special collection ID
        $addons = array();
        foreach ($addonIds as $catid => $cataddons) {
            $addons[$catid] = $this->Addon->getAddonList($cataddons, array('files', 'latest_version', 'list_details'));
        }
        $this->publish('addons', $addons);
        
        // prepare view, then render
        $this->publish('suppressHeader', true, false);
        $this->publish('suppressLanguageSelector', true, false);
        $this->publish('suppressCredits', true, false);
        $this->pageTitle = 'Fashion Your Firefox';
    }
    
    /**
     * installation dialog for collections
     * @param string $method 'html' (with layout) or 'ajax' (without)
     */
    function install($method = 'html') {
        $addons = array();
        if (!empty($_POST['addon'])) {
            $addons = $this->Addon->getAddonList($_POST['addon'], array(
            'compatible_apps', 'files', 'latest_version', 'list_details'));
            
            // XXX: ugly hack allowing signed add-ons to be installed separately
            // due to bug 462108 and 453545. The DB doesn't know which ones are
            // signed or not so we need to hardcode them here.
            $signed_addons = array(
                5579, // Cooliris
                3615, // Delicious
                8384, // Digg
                5202, // Ebay
                2410, // Foxmarks
                1512 // LinkedIn
            );
            foreach ($addons as &$addon) {
                $addon['Addon']['signed_xpi'] = in_array($addon['Addon']['id'], $signed_addons);
            }
        }
        $this->publish('addons', $addons);
        
        // fetch all platforms
        $this->Platform->unbindFully();
        $platforms_all = $this->Platform->findAll();
        $platforms = array();
        foreach ($platforms_all as $pf) {
            $platforms[$pf['Platform']['id']] = $pf['Translation']['name']['string'];
        }
        $this->publish('platforms', $platforms);
        
        // prepare and display view
        $this->pageTitle = 'Collections' . " :: " . sprintf(_('addons_home_pagetitle'), APP_PRETTYNAME);
        $is_ajax = ($method=='ajax');
        $this->publish('is_ajax', $is_ajax, false);
        $this->publish('suppressHeader', true, false);
        $this->publish('suppressLanguageSelector', true, false);
        if ($is_ajax) {
            $this->layout = null;
            $this->render('install', false);
        } else {
            $this->render();
        }
    }

    /**
     * "Success!" screen
     */
    function success() {
        if (isset($_GET['i'])) {
            $installed = explode(',', $_GET['i']);
            $installed = array_filter($installed, 'is_numeric');
            if (!empty($installed)) {
                $addons = $this->Addon->getAddonList($installed);
            } else {
                $addons = array();
            }
        } else {
            $addons = array();
        }
        $this->publish('addons', $addons);
        
        $this->set('cssAdd', array('collection-style'));
        $this->publish('suppressHeader', true, false);
        $this->publish('suppressLanguageSelector', true, false);
        $this->publish('suppressCredits', true, false);
        $this->pageTitle = 'Fashion Your Firefox';
    }
    
    /**
     * AJAX action for looking up add-ons to add to a collection
     */
    function addonLookup() {
        global $valid_status;
        
        // Rather than change our cake parameter regex, use a normal get var
        $name = $_GET['q'];
        $this->Amo->clean($name);
        $addons = $this->Addon->findAll(array('Translation.name' => "LIKE %{$name}%",
            'Addon.status' => $valid_status, 'Addon.inactive' => 0),
            array('Addon.id', 'Addon.name'), 'Translation.name');
        if (!empty($addons)) {
            foreach ($addons as &$_addon) {
                // add icons
                $_addon['Addon']['iconpath'] = $this->Image->getAddonIconURL($_addon['Addon']['id']);
            }
        } else {
            $addons = false;
        }

        $this->publish('addons', $addons);
        $this->render('ajax/addon_lookup', 'ajax');
    }

}
