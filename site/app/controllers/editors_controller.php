<?php
/* ***** BEGIN LICENSE BLOCK *****
 * Version: MPL 1.1/GPL 2.0/LGPL 2.1
 *
 * The contents of this file are subject to the Mozilla Public License Version
 * 1.1 (the "License"); you may not use this file except in compliance with
 * the License. You may obtain a copy of the License at
 * http://www.mozilla.org/MPL/e
 *
 * Software distributed under the License is distributed on an "AS IS" basis,
 * WITHOUT WARRANTY OF ANY KIND, either express or implied. See the License
 * for the specific language governing rights and limitations under the
 * License.
 *
 * The Original Code is addons.mozilla.org site.
 *
 * The Initial Developer of the Original Code is
 * Justin Scott <fligtar@gmail.com>.
 * Portions created by the Initial Developer are Copyright (C) 2006
 * the Initial Developer. All Rights Reserved.
 *
 * Contributor(s):
 *      Wil Clouser <clouserw@gmail.com>
 *      Frederic Wenzel <fwenzel@mozilla.com>
 *      Les Orchard <lorchard@mozilla.com>
 *      Cesar Oliveira <a.sacred.line@gmail.com>
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

class EditorsController extends AppController
{
    var $name = 'Editors';
    var $uses = array('Addon', 'AddonTag', 'Addontype', 'Application', 'Approval',
        'Appversion', 'Cannedresponse', 'EditorSubscription', 'Eventlog', 'Favorite',
        'File', 'Platform', 'Review', 'ReviewsModerationFlag', 'Tag', 'Translation',
        'User', 'Version');
    var $components = array('Amo', 'Audit', 'Developers', 'Editors', 'Email', 'Error', 'Image', 'Pagination');
    var $helpers = array('Html', 'Javascript', 'Ajax', 'Listing', 'Localization', 'Pagination');

   /**
    * Require login for all actions
    */
    function beforeFilter() {
        //beforeFilter() is apparently called before components are initialized. Cake++
        $this->Amo->startup($this);
        
        $this->Amo->checkLoggedIn();
        
        $this->layout = 'mozilla';
        $this->pageTitle = _('editors_pagetitle').' :: '.sprintf(_('addons_home_pagetitle'), APP_PRETTYNAME);
        
        $this->cssAdd = array('editors', 'admin');
        $this->publish('cssAdd', $this->cssAdd);
        
        $this->publish('jsAdd', array('jquery-compressed.js',
                                    'jquery.autocomplete.pack.js',
                                    'editors'));

        $this->breadcrumbs = array(_('editors_pagetitle') => '/editors/index');
        $this->publish('breadcrumbs', $this->breadcrumbs);
        $this->publish('suppressJQuery', 1);
	
        $this->publish('subpagetitle', _('editors_pagetitle'));

        // disable query caching so devcp changes are visible immediately
        foreach ($this->uses as $_model) {
            $this->$_model->caching = false;
        }
        
        //Get counts
        $pending = $this->File->query("SELECT COUNT(*) FROM `files` WHERE `status`=".STATUS_PENDING." GROUP BY `status`");
        $nominated = $this->Addon->query("SELECT COUNT(*) FROM `addons` WHERE `status`=".STATUS_NOMINATED." GROUP BY `status`");
        $reviews = $this->Review->query("SELECT COUNT(*) FROM `reviews` WHERE `editorreview`=1 GROUP BY `editorreview`");
        
        $count['pending'] = !empty($pending) ? $pending[0][0]['COUNT(*)'] : 0;
        $count['nominated'] = !empty($nominated) ? $nominated[0][0]['COUNT(*)'] : 0;
        $count['reviews'] = !empty($reviews) ? $reviews[0][0]['COUNT(*)'] : 0;
        $this->publish('count', $count);
        
        $this->count = $count;
    }
    
   /**
    * Index
    */
    function index() {
        $this->summary();
    }
    
   /**
    * Summary
    */
    function summary() {
        $this->cssAdd[] = 'summary';
        $this->publish('cssAdd', $this->cssAdd);
        
        //Total reviews
        $totalReviews = $this->Approval->query("SELECT users.firstname, users.lastname, COUNT(*) as total FROM approvals LEFT JOIN users ON users.id=approvals.user_id GROUP BY approvals.user_id ORDER BY total DESC LIMIT 5");
        $this->set('totalReviews', $totalReviews);
        
        //Reviews this month
        $monthStart = date('Y-m-01');
        $monthReviews = $this->Approval->query("SELECT users.firstname, users.lastname, COUNT(*) as total FROM approvals LEFT JOIN users ON users.id=approvals.user_id WHERE approvals.created >= '{$monthStart} 00:00:00' GROUP BY approvals.user_id ORDER BY total DESC LIMIT 5");
        $this->set('monthReviews', $monthReviews);
        
        //New editors
        $newEditors = $this->Eventlog->query("SELECT users.firstname, users.lastname, eventlog.created FROM eventlog LEFT JOIN users ON eventlog.added=users.id WHERE eventlog.type='admin' AND eventlog.action='group_addmember' AND eventlog.changed_id='2' ORDER BY eventlog.created DESC LIMIT 5");
        $this->set('newEditors', $newEditors);
        
        //Recent activity
        $logs = $this->Eventlog->findAll(array('type' => 'editor'), null, 'Eventlog.created DESC', 5);
        $logs = $this->Audit->explainLog($logs);
        $this->set('logs', $logs);
        
        $this->set('page', 'summary');
        $this->render('summary');
    }
    
   /**
    * Review queue
    */
    function queue($mode = 'pending') {
        //If queues are disabled, show appropriate error
        if ($this->Config->getValue('queues_disabled') == 1 && !$this->SimpleAcl->actionAllowed('*', '*', $this->Session->read('User'))) {
            $this->flash(_('editors_queues_disabled'), '/', 3);
            return;
        }
        
        // if num=... argument is set, jump to specific item in queue
        if (isset($this->params['url']['num']) && is_numeric($this->params['url']['num']))
            $this->Editors->redirectByQueueRank($mode, $this->params['url']['num']);
        
        $this->publish('collapse_categories', true);
        
        $count = $this->count;
        
        $this->Amo->clean($mode);
        $this->breadcrumbs[_('editors_review_queue_pagetitle')] = '/editors/queue';
        $this->publish('breadcrumbs', $this->breadcrumbs);
        $this->publish('subpagetitle', _('editors_review_queue_pagetitle'));
        
        $this->publish('mode', $mode);

        if ($mode == 'pending') {
            // Setup our pagination
            $this->Pagination->total = $count['pending'];
            $_pagination_options = array('sortByClass' => 'Version', 'sortBy' => 'created');

            if (!array_key_exists('show', $_GET) && $this->Session->read('editor_queue_pending_show')) {
                $_pagination_options['show'] = $this->Session->read('editor_queue_pending_show');
            } else {
                // If $_GET['show'] exists it overrides this in the pagination component
                $_pagination_options['show'] = 50;
            }
            list($_order,$_limit,$_page) = $this->Pagination->init(null, null, $_pagination_options);
            $this->Session->write('editor_queue_pending_show', $_limit);
            
            //Pull any files that have STATUS_PENDING
            $pending = $this->File->findAllByStatus(STATUS_PENDING,
                                                    array('File.id', 'File.platform_id', 'Version.id', 
                                                          'Version.addon_id', 'Version.version', 
                                                          'Version.created'
                                                          ), $_order, $_limit, $_page, 0);
            
            if (!empty($pending)) {
                foreach ($pending as $k => $file) {
                    $addon = $this->Addon->findById($file['Version']['addon_id'],
                                                    array('Addon.id', 'Addon.name', 'Addon.defaultlocale',
                                                          'Addon.addontype_id', 'Addon.prerelease',
                                                          'Addon.sitespecific', 'Addon.externalsoftware',
                                                          ));
                    $pending[$k] = array_merge_recursive($pending[$k], $addon);
                }
            }
            $addons = $pending;
        }
        elseif ($mode == 'nominated') {
            // Setup our pagination
            $this->Pagination->total = $count['nominated'];
            $_pagination_options = array('sortByClass' => 'Addon', 'sortBy' => 'nominationdate');
            if (!array_key_exists('show', $_GET) && $this->Session->read('editor_queue_nominated_show')) {
                $_pagination_options['show'] = $this->Session->read('editor_queue_nominated_show');
            } else {
                // If $_GET['show'] exists it overrides this in the pagination component
                $_pagination_options['show'] = 50;
            }
            list($_order,$_limit,$_page) = $this->Pagination->init(null,null,$_pagination_options);
            $this->Session->write('editor_queue_nominated_show', $_limit);

            //Pull any add-ons that have STATUS_NOMINATED
            $nominated = $this->Addon->findAllByStatus(STATUS_NOMINATED,
                                                    array('Addon.id', 'Addon.name', 'Addon.defaultlocale',
                                                          'Addon.addontype_id', 'Addon.prerelease',
                                                          'Addon.sitespecific', 'Addon.externalsoftware',
                                                          'Addon.created', 'Addon.nominationdate'
                                                          ), $_order, $_limit, $_page, 0);
            if (!empty($nominated)) {
                foreach ($nominated as $k => $addon) {
                    $version = $this->Version->findByAddon_id($addon['Addon']['id'],
                                                    array('Version.id', 'Version.addon_id',
                                                          'Version.version', 'Version.modified'
                                                          ), 'Version.created DESC');
                    $nominated[$k] = array_merge_recursive($nominated[$k], $version);
                }
            }
            $addons = $nominated;
        }
        elseif ($mode == 'reviews') {
            $this->_reviews($count['reviews']);
            return;
        }
        else {
            $this->redirect('/editors');
            return;
        }
        
        $platforms = $this->Amo->getPlatformName();
        $applications = $this->Amo->getApplicationName();

        $submissionTypes = array(   'new' => _('editors_submissiontype_new'),
                                    'updated' => _('editors_submissiontype_updated')
                                 );
        
        //make modifications to the queue array
        if (!empty($addons)) {
            foreach ($addons as $k => $addon) {
                //get min/max versions
                if ($targetApps = $this->Amo->getMinMaxVersions($addon['Version']['id'])) {
                    foreach ($targetApps as $targetApp) {
                        $appName = $targetApp['translations']['localized_string'];
                        $addons[$k]['targetApps'][$appName]['min'] = $targetApp['min']['version'];
                        $addons[$k]['targetApps'][$appName]['max'] = $targetApp['max']['version'];
                    }
                }

                //Age
                if ($mode == 'pending') {
                    $age = time() - strtotime($addon['Version']['created']);
                }
                elseif ($mode == 'nominated') {
                    $age = time() - strtotime($addon['Addon']['created']);
                    $nominationage = time() - strtotime($addon['Addon']['nominationdate']);
                    $addons[$k]['nominationage'] = $this->_humanizeAge($nominationage);
                }
                
                $addons[$k]['age'] = $this->_humanizeAge($age);

                //Generate any additional notes
                $addons[$k]['notes'] = array();
                
                //Platform-specific?
                if (!empty($addon['Version'][0]['File'][0]['platform_id']) && $addon['Version'][0]['File'][0]['platform_id'] != 1) {
                    $os = array();
                    foreach ($addon['Version'][0]['File'] as $file) {
                        $os[] = $platforms[$file['platform_id']];
                    }
                    $addons[$k]['notes'][] = sprintf(_('editors_platform_x_only'), implode(', ', $os));
                }
                elseif (!empty($addon['File']['platform_id']) && $addon['File']['platform_id'] != 1) {
                    $addons[$k]['notes'][] = sprintf(_('editors_platform_x_only'), $platforms[$addon['File']['platform_id']]);
                }
                
                //Featured?
                //@TODO
                
                //Site specific?
                if ($addon['Addon']['sitespecific'] == 1) {
                    $addons[$k]['notes'][] = _('editors_site_specific');
                }
                //Pre-release?
                if ($addon['Addon']['prerelease'] == 1) {
                    $addons[$k]['notes'][] = _('editors_pre-release');
                }
                //External software?
                if ($addon['Addon']['externalsoftware'] == 1) {
                    $addons[$k]['notes'][] = _('editors_external_software');
                }
            }
        }
        //pr($addons);
        //Filters
        $selected = array(  'Addontype' => array(),
                            'Application' => array(),
                            'Platform' => array(),
                            'SubmissionType' => array()
                          );
        $filtered = false;
                          
        if (!empty($this->data['Approval']['Addontype'])) {
            foreach ($this->data['Approval']['Addontype'] as $option) {
                $selected['Addontype'][$option] = true;
            }
            $filtered = true;
        }

        if (!empty($this->data['Approval']['Application'])) {
            foreach ($this->data['Approval']['Application'] as $option) {
                $selected['Application'][$option] = true;
            }
            $filtered = true;
        }
        
        if (!empty($this->data['Approval']['Platform'])) {
            foreach ($this->data['Approval']['Platform'] as $option) {
                $selected['Platform'][$option] = true;
            }
            $filtered = true;
        }
        
        if (!empty($this->data['Approval']['SubmissionType'])) {
            foreach ($this->data['Approval']['SubmissionType'] as $option) {
                $selected['SubmissionType'][$option] = true;
            }
            $filtered = true;
        }
        
        if (isset($this->data['filter'])) {
            $filtered = true;
        }
        elseif (isset($this->data['clear'])) {
            $filtered = false;
        }

        $this->publish('addons', $addons);
        $this->set('platforms', $platforms);
        $this->set('addontypes', $this->Addontype->getNames());
        $this->set('applications', $applications);
        $this->set('submissionTypes', $submissionTypes);
        $this->publish('mode', $mode);
        $this->publish('selected', $selected);
        $this->publish('filtered', $filtered);
        $this->render('queue');
    }
    
   /**
    * Review a specific version
    * @param int $id The version id
    */
    function review($id) {
        $this->Amo->clean($id);
        $this->publish('subpagetitle', _('editors_addon_review_pagetitle'));
        $this->breadcrumbs[_('editors_addon_review_pagetitle')] = '/editors/review/'.$id;
        $this->publish('breadcrumbs', $this->breadcrumbs);
        $this->publish('collapse_categories', true);

        //Bind necessary models
        $this->User->bindFully();
        $this->Addontype->bindFully();
        $this->Version->bindFully();
        $this->Addon->bindFully();

        if (!$version = $this->Version->findById($id, null, null, 1)) {
            $this->flash(_('error_version_notfound'), '/editors/queue');
            return;
        }
        
        if (!$addon = $this->Addon->findById($version['Version']['addon_id'])) {
            $this->flash(_('error_addon_notfound'), '/editors/queue');
            return;
        }
        
        //Make sure user is not an author (or is an admin)
        $session = $this->Session->read('User');
        if (!$this->SimpleAcl->actionAllowed('*', '*', $session)) {
            foreach ($addon['User'] as $author) {
                if ($author['id'] == $session['id']) {
                    $this->flash(_('editors_error_self_reviews_forbidden'), '/editors/queue');
                    return;
                }
            }
        }

        if (!empty($this->data)) {
            //pr($this->data);
            if ($this->data['Approval']['ActionField'] == 'info') {
                // request more information
                $this->Editors->requestInformation($addon, $this->data);
            }
            elseif ($this->data['Approval']['Type'] == 'nominated') {
                $this->Editors->reviewNominatedAddon($addon, $this->data);
            }
            else {
                $this->Editors->reviewPendingFiles($addon, $this->data);
            }
            
            if ($this->Error->noErrors()) {
                // if editor chose to be reminded of the next upcoming update, save this
                if ($this->data['Approval']['subscribe'])
                    $this->EditorSubscription->subscribeToUpdates($session['id'], $addon['Addon']['id']);
                
                $this->flash(_('editors_reviewed_successfully'), '/editors/queue/'.$this->data['Approval']['Type']);
                return;
            }
        }
        
        if (!empty($addon['Tag'])) {
            foreach ($addon['Tag'] as $tag) {
                $tags[] = $tag['id'];
            }
            $addon['Tags'] = $this->Tag->findAll("Tag.id IN (".implode(', ', $tags).")");
        }
        else
            $addon['Tags'] = array();
        
        $platforms = $this->Amo->getPlatformName();   
        
        //get min/max versions
        if ($targetApps = $this->Amo->getMinMaxVersions($id)) {
            foreach ($targetApps as $targetApp) {
                $appName = $targetApp['translations']['localized_string'];
                $addon['targetApps'][$appName]['min'] = $targetApp['min']['version'];
                $addon['targetApps'][$appName]['max'] = $targetApp['max']['version'];
            }
        }
        
        if (!empty($version['File'])) {
            $version['pendingCount'] = 0;
            foreach ($version['File'] as $k => $file) {
                if ($file['status'] == STATUS_PENDING) {
                    $version['File'][$k]['disabled'] = 'false';
                    $version['pendingCount']++;
                }
                else {
                    $version['File'][$k]['disabled'] = 'true';
                }
            }
        }
        
        if ($responses = $this->Cannedresponse->findAll()) {
            foreach ($responses as $response) {
                $cannedresponses[$response['Translation']['response']['string']] = $response['Translation']['name']['string'];
            }
            $this->publish('cannedresponses', $cannedresponses);
        }
        
        $this->publish('jsLocalization', array( 'action' => _('editors_review_action'),
                                            'comments' => _('editors_review_comments'),
                                            'os' => _('editors_tested_os'),
                                            'applications' => _('editors_tested_app'),
                                            'errors' => _('editors_error_js-formerror'),
                                            'files' => _('editors_error_review_one_file')
                                    ));
                                    
        //Review History
        if ($history = $this->Approval->findAll(array('Approval.addon_id' => $addon['Addon']['id'], 'reply_to IS NULL'))) {
            foreach ($history as $k => &$hist) {
                if (!empty($hist['File']['id'])) {
                    $vLookup = $this->Version->findById($hist['File']['version_id'], array('Version.version'));
                    $history[$k] = array_merge_recursive($history[$k], $vLookup);
                }
                
                // add replies to information requests
                if ($hist['Approval']['reviewtype'] == 'info') {
                    $hist['replies'] = $this->Approval->findAll(array('Approval.reply_to' => $hist['Approval']['id']), null, 'Approval.created');
                }
            }
        }
        
        //pr($history);
        
        if ($addon['Addon']['status'] == STATUS_NOMINATED) {
            $reviewType = 'nominated';
        } else {
            $reviewType = 'pending';
        }
        
        // rank in nomination/update queue
        if (isset($this->params['url']['num']) && is_numeric($this->params['url']['num']))
            $queueRank = $this->params['url']['num'];
        else
            $queueRank = false;
        $this->publish('queueRank', $queueRank);

        $this->publish('has_public', $this->File->getLatestFileByAddonId($addon['Addon']['id']) != 0);
        $this->publish('addon', $addon);
        $this->publish('version', $version);
        $this->publish('platforms', $platforms);
        $this->publish('addontypes', $this->Addontype->getNames());
        $this->publish('addontype', $addon['Addon']['addontype_id']);
        $this->publish('approval', $this->Amo->getApprovalStatus());  
        $this->publish('history', $history); 
        $this->publish('errors', $this->Error->errors);
        $this->publish('reviewType', $reviewType, false);
        
        $this->render('review');
    }
    
   /**
    * Reads the approval file
    * @param int $id The file id
    */
    function file($id) {
	$this->Amo->clean($id);
        $this->File->id = $id;
        
        if (!$file = $this->File->read()) {
            $this->flash(_('error_file_notfound'), '/editors/queue');
        }
        
        $this->Addon->id = $file['Version']['addon_id'];
        $this->Version->id = $file['Version']['id'];
        
        $filename = $file['File']['filename'];
        $file = REPO_PATH.'/'.$this->Addon->id.'/'.$filename;
        
        if (file_exists($file)) {
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Length: ' . filesize($file));
            header('Content-Disposition: attachment; filename=' . $filename);
            
            readfile($file);
        }
        else {
            $this->flash(sprintf(_('error_file_x_notfound'), $file), '/editors/review/'.$this->Version->id);
        }
        exit;
    }
    
   /**
    * Moderated Reviews Queue
    */
    function _reviews($count) {
        if (!empty($this->data)) {
            foreach ($this->data['Reviews'] as $k => $review) {
                if ($review != 'skip') {
                    preg_match("/^review(\d+)$/", $k, $matches);
                    $this->Review->id = intval($matches[1]);
                    
                    if ($review == 'approve') {
                        //Log editor action
                        $this->Eventlog->log($this, 'editor', 'review_approve', null, $this->Review->id);
                        
                        $this->Review->save(array('editorreview' => 0));
                        // HACK: not sure how this is done without deleteAll()
                        $reviews_flags = $this->ReviewsModerationFlag->query(
                            'DELETE FROM reviews_moderation_flags WHERE review_id='.$this->Review->id
                        );
                    }
                    elseif ($review == 'delete') {
                        //Pull review for log
                        $this->Review->setLang('en-US', $this);
                        $review = $this->Review->read();
                        $this->Review->setLang(LANG, $this);
                        
                        $reviewArray = array('title' => $review['Translation']['title']['string'],
                                             'body' => $review['Translation']['body']['string']);
                        //Log editor action
                        $this->Eventlog->log($this, 'editor', 'review_delete', null, $this->Review->id, null, null, serialize($reviewArray));
                        
                        // HACK: not sure how this is done without deleteAll()
                        $reviews_flags = $this->ReviewsModerationFlag->query(
                            'DELETE FROM reviews_moderation_flags WHERE review_id='.$this->Review->id
                        );
                        $this->Review->delete();
                    }
                }
            }
            
            $this->flash(_('editors_reviews_processed'), '/editors/queue/reviews');
            return;
        }
        
        $criteria = array('Review.editorreview' => '1');
        
        // initialize pagination
        $this->Pagination->total = $count;
        if (!array_key_exists('show', $_GET) && $this->Session->read('editor_queue_reviews_show')) {
            // Have to modify $_GET because pagination component pulls directly from it
            $_GET['show'] = $this->Session->read('editor_queue_reviews_show');
        }
        list($order,$limit,$page) = $this->Pagination->init($criteria);
        $this->Session->write('editor_queue_reviews_show', $limit);

		$_reviews = $this->Review->findAll($criteria, "Review.id", 'Review.modified ASC', $limit, $page, -1);
		$_review_ids = array();
		foreach ($_reviews as $_id) $_review_ids[] = $_id['Review']['id'];
		unset($_reviews);
		$reviews = $this->Review->getReviews($_review_ids, true);
		foreach ($reviews as $k => $review) {
			$reviews[$k]['Addon'] = $this->Addon->findById($review['Review']['addon_id'], array('id', 'name'), null, -1);
			if (!empty($review['Review']['reply_to'])) {
				$_replyto = $this->Review->getReviews($review['Review']['reply_to']);
				if (!empty($_replyto))
					$reviews[$k]['Review']['reply_to'] = $_replyto[0];
			}
		}
		unset($_replyto);

        // Gather and collate all available flags per review on the page.
        // NOTE: User info isn't included here, keeping things anonymous.
        $reviews_flags = array();
        $reviews_flags_notes = array();
        $this->ReviewsModerationFlag->unbindFully();
        $_reviews_flags = $this->ReviewsModerationFlag->findAll(array(
            'ReviewsModerationFlag.review_id' => $_review_ids
        ));
        foreach ($_reviews_flags as $flag) {
            $review_id = $flag['ReviewsModerationFlag']['review_id'];
            $flag_name = $flag['ReviewsModerationFlag']['flag_name'];
            
            // Count the occurrences of each flag per review, building 
            // the data structure as we go.
            if (!isset($reviews_flags[$review_id])) 
                $reviews_flags[$review_id] = array();
            if (!isset($reviews_flags[$review_id][$flag_name]))
                $reviews_flags[$review_id][$flag_name] = 1;
            else
                $reviews_flags[$review_id][$flag_name] += 1;

            // Collect freeform notes in a separate list.
            if ($flag_name == 'review_flag_reason_other') {
                if (!isset($reviews_flags_notes[$review_id]))
                    $reviews_flags_notes[$review_id] = array();
                $reviews_flags_notes[$review_id][] = 
                    $flag['ReviewsModerationFlag']['flag_notes'];
            }
        }

        $this->publish('reviews', $reviews);
        $this->publish('reviews_flags', $reviews_flags);
        $this->publish('reviews_flags_notes', $reviews_flags_notes);
        $this->publish('review_flag_reasons', 
            $this->ReviewsModerationFlag->reasons);
        
        $this->render('reviews_queue');
    }

   /**
    * Featured Add-ons
    * params are for ajax callbacks
    */
    function featured($command=null, $ajax=null) {
        $this->Amo->clean($this->data);
        
        switch($command) {
            case 'add':
                if (preg_match('/\[(\d+)\]/', $this->data['Addon']['id'], $matches)) {
                    $this->data['Addon']['id'] = $matches[1];
                } 

                if (!is_numeric($this->data['Addon']['id']) || !is_numeric($this->data['Tag']['id'])) {
                    header('HTTP/1.1 400 Bad Request');
                    $this->flash(_('editors_featured_addon_add_failure'), '/editors/featured');
                    return;
                }

                $_addon = $this->Addon->getAddon($this->data['Addon']['id']);
                if ($_addon['Addon']['status'] != STATUS_PUBLIC) {
                    header('HTTP/1.1 400 Bad Request');
                    $this->flash(_('editors_featured_addon_edit_failure'), '/editors/featured');
                    return;
                }

                // If the add-on isn't in the category, we'll add it.
                $_new_feature_query = "REPLACE INTO addons_tags (addon_id, tag_id, feature) VALUES ( '{$this->data['Addon']['id']}', '{$this->data['Tag']['id']}', 1)";

                if ($this->AddonTag->query($_new_feature_query)) {
                    header('HTTP/1.1 400 Bad Request');
                    $this->flash(_('editors_featured_addon_add_failure'), '/editors/featured');
                } else {
                    $this->Eventlog->log($this, 'editor', 'feature_add', '', $this->data['Addon']['id'], $this->data['Addon']['id']);
                    $this->flash(_('editors_featured_addon_add_success'), '/editors/featured', 3);
                }

                return;
            case 'edit':
                global $valid_languages;

                if (!empty($this->data['AddonTag']['feature_locales'])) {
                    if (count(array_diff(explode(',',$this->data['AddonTag']['feature_locales']), array_keys($valid_languages))) > 0) {
                        header('HTTP/1.1 400 Bad Request');
                        $this->flash(_('editors_featured_addon_invalid_locale'), '/editors/featured');
                        return;
                    }
                }

                if (!is_numeric($this->data['Addon']['id']) || !is_numeric($this->data['Tag']['id']) || preg_match('/[^A-Za-z,-]/',$this->data['AddonTag']['feature_locales'])) {
                    header('HTTP/1.1 400 Bad Request');
                    $this->flash(_('editors_featured_addon_edit_failure'), '/editors/featured');
                    return;
                }

                $this->Eventlog->log($this, 'editor', 'feature_locale_change', 'feature-locales', $this->data['Addon']['id']);

                // Reorder the locales
                $_locales = array_unique(explode(',', $this->data['AddonTag']['feature_locales']));
                sort($_locales);
                $this->data['AddonTag']['feature_locales'] = implode(',',$_locales);

                $_edit_feature_query = "UPDATE addons_tags 
                                        SET feature_locales='{$this->data['AddonTag']['feature_locales']}' 
                                        WHERE addon_id='{$this->data['Addon']['id']}' 
                                        AND tag_id='{$this->data['Tag']['id']}'";

                if ($this->AddonTag->query($_edit_feature_query)) {
                    header('HTTP/1.1 400 Bad Request');
                    $this->flash(_('editors_featured_addon_edit_failure'), '/editors/featured');
                } else {
                    $this->flash(_('editors_featured_addon_edit_success'), '/editors/featured', 3);
                }
                return;

            case 'remove':
                if (is_numeric($this->data['Tag']['id']) && is_numeric($this->data['Addon']['id'])) {

                    $this->Eventlog->log($this, 'editor', 'feature_remove', null, $this->data['Addon']['id'], null, $this->data['Addon']['id']);

                    // Neither query() nor execute() return success from a DELETE call, even when the row is deleted. wtf.
                    $this->AddonTag->execute("DELETE FROM `addons_tags` WHERE addon_id='{$this->data['Addon']['id']}' AND tag_id='{$this->data['Tag']['id']}' AND feature=1 LIMIT 1");

                    // Assume we succeeded
                    $this->flash(_('editors_featured_addon_remove_success'), '/editors/featured', 3);
                    return;
                }

                header('HTTP/1.1 400 Bad Request');
                $this->flash(_('editors_featured_addon_remove_failure'), '/editors/featured');

                return;

            default:
                break;
        }

        // Setup title and breadcrumbs
        $this->breadcrumbs[_('editors_featured_addons_pagetitle')] = '/editors/featured';
        $this->publish('breadcrumbs', $this->breadcrumbs);
        $this->publish('subpagetitle', _('editors_featured_addons_pagetitle'));

        // Get all featured Addons
        $features = $this->AddonTag->findAllByFeature(1, array('addon_id'));
        $_addon_ids = $addons_by_tag = array();

        if (!empty($features)) {
            foreach ($features as $feature) { $_addon_ids[] = $feature['AddonTag']['addon_id']; }
            $_addon_ids = array_unique($_addon_ids);

            // Big ol' array
            $features = $this->Addon->findAll(array('Addon.id' => $_addon_ids), array('Addon.id', 'Addon.name', 'Addon.addontype_id'), 'Translation.name');

            foreach ($features as $feature) {
                // Dump them into the array sorted by category
                foreach ($feature['AddonTag'] as $attributes) {
                    if ($attributes['feature'] == 1) {
                        // override the AddonTag array for the view.  Even though an add-on will have multiple tags, we only want one for this view
                        $feature['AddonTag'] = array( 0 => $attributes );

                        $addons_by_tag[$attributes['tag_id']][] = $feature;
                    }
                    
                }

            }
        }

        // Reorganize the tags so it's easier to use them in the view.  TheLittleThingsWearMeDown++ :(
        $tags = array();
        foreach ($this->Tag->findAll('', null, array('Tag.application_id', 'Tag.addontype_id', 'Translation.name')) as $tag) {
            $tags[$tag['Tag']['id']] = $tag;
        }
        
        $this->set('applications', $this->Amo->getApplicationName());
        $this->set('addontypes', $this->Addontype->getNames());
        $this->set('tags', $tags);
        $this->set('mode', 'featured');
        $this->publish('addons_by_tag', $addons_by_tag);
        $this->render('featured');

    }

   /**
    * Display logs
    */
    function logs() {
        $this->breadcrumbs[_('editorcp_logs_page_heading')] = '/editors/logs';
        $this->set('breadcrumbs', $this->breadcrumbs);
        
        $conditions = array();
        
        if (!empty($this->data)) {
            $filter = explode(':', $this->data['Eventlog']['filter']);
            $conditions['type'] = $filter[0];
            
            if ($filter[1] != '*') {
                $conditions['action'] = $filter[1];
            }
        }
        $conditions['type'] = 'editor';
        
        $logs = $this->Eventlog->findAll($conditions, null, 'Eventlog.created DESC');
        
        $logs = $this->Audit->explainLog($logs);
        
        $this->set('logs', $logs);
        
        $this->set('page', 'logs');
        $this->render('logs');
    }
    
    function reviewlog() {
        //Default conditions are the current month
        $monthStart = date('Y-m-01');
        $conditions = "Approval.created >= '{$monthStart} 00:00:00'";
        $startdate = $monthStart;
        $enddate = 'YYYY-MM-DD';
        
        //If user has specified own conditions, use those
        if (!empty($this->params['url']['start'])) {
            $start_time = strtotime($this->params['url']['start']);
            $end_time = strtotime($this->params['url']['end']);
            if ($start_time !== false && $start_time != -1) {
                $conditions = array(
                    "Approval.created >= FROM_UNIXTIME('{$start_time}')"
                );
                $startdate = $this->params['url']['start'];
                
                if ($end_time !== false && $end_time != -1) {
                    $conditions[] = "Approval.created < FROM_UNIXTIME('".strtotime('+1 day', $end_time)."')";
                    $enddate = $this->params['url']['end'];
                }
            }
        }
            
        // set up pagination
        list($order,$limit,$page) = $this->Pagination->init($conditions, null,
            array('modelClass'=>'Approval', 'show'=>50));
        
        $approvals = $this->Approval->findAll($conditions, null, $order, $limit, $page);
        foreach ($approvals as $k => $approval) {
            $approvals[$k]['Addon'] = $this->Addon->getAddon($approval['Approval']['addon_id']);
        }
        
        $this->publish('approvals', $approvals);
        $this->publish('startdate', $startdate);
        $this->publish('enddate', $enddate);
        
        $this->set('page', 'reviewlog');
        $this->render('reviewlog');
    }

    /* Humanizes a Unix Timestamp */
    function _humanizeAge($age) {
        $humanized = '';

        //days
        if ($age >= (60*60*24*2)) {
            $humanized = sprintf(_('editors_x_days'), floor($age/(60*60*24)));
        }
        //1 day
        elseif ($age >= (60*60*24)) {
            $humanized = _('editors_one_day');
        }
        //hours
        elseif ($age >= (60*60*2)) {
            $humanized = sprintf(_('editors_x_hours'), floor($age/(60*60)));
        }
        //hour
        elseif ($age >= (60*60)) {
            $humanized = _('editors_one_hour');
        }
        //minutes
        elseif ($age > 60) {
            $humanized = sprintf(_('editors_x_minutes'), floor($age/60));
        }
        //minute
        else {
            $humanized = _('editors_one_minute');
        }

        return $humanized;
    }
}

?>
