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
 * Justin Scott <fligtar@gmail.com>.
 * Portions created by the Initial Developer are Copyright (C) 2006
 * the Initial Developer. All Rights Reserved.
 *
 * Contributor(s):
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
class EditorsComponent extends Object {
    var $controller;
    
   /**
    * Save a reference to the controller on startup
    * @param object &$controller the controller using this component
    */
    function startup(&$controller) {
        $this->controller =& $controller;
    }
    
   /**
    * Process review for a nominated add-on.
    * Update add-on, approval, and file info and send emails
    * @param array $addon add-on information
    * @param array $data POST data
    */
    function reviewNominatedAddon($addon, $data) {
        //Make sure add-on is actually nominated
        if ($addon['Addon']['status'] != STATUS_NOMINATED) {
            $this->controller->Error->addError(___('editor_review_error_addon_not_nominated', 'This add-on has not been nominated'));
            return false;
        }
        
        $this->controller->Addon->id = $addon['Addon']['id'];
        $addonData = array();
        
        //Get most recent version
        $version = $this->controller->Version->findByAddon_id($this->controller->Addon->id, null, 'Version.created DESC');

        if ($data['Approval']['ActionField'] == 'public') {
            $addonData['status'] = STATUS_PUBLIC;
            $addonData['higheststatus'] = STATUS_PUBLIC;
        }
        elseif ($data['Approval']['ActionField'] == 'sandbox') {
            $addonData['status'] = STATUS_SANDBOX;
        }
        elseif ($data['Approval']['ActionField'] == 'superreview') {
            $addonData['adminreview'] = 1;
            $addonData['status'] = STATUS_NOMINATED;
        }
        else {
            $this->controller->Error->addError(___('editor_review_error_no_action', 'Please select a review action.'));
            return false;
        }
        
        if (empty($data['Approval']['comments'])) {
            $this->controller->Error->addError(___('editor_review_error_no_comments', 'Please enter review comments.'));
            return false;
        }
        
        $session = $this->controller->Session->read('User');
        
        $approvalData = array('user_id' => $session['id'],
                              'reviewtype' => 'nominated',
                              'action' => $addonData['status'],
                              'addon_id' => $this->controller->Addon->id,
                              'comments' => $data['Approval']['comments'],
                              'file_id' => $version['File'][0]['id']
                             );
        
        if ($this->controller->Error->noErrors()) {
            if ($data['Approval']['ActionField'] == 'public') {
                //Make files of most recent version public
                if (!empty($version['File'])) {
                    foreach ($version['File'] as $file) {
                        $this->controller->File->id = $file['id'];
                        $fileData = array('status' => STATUS_PUBLIC, 'datestatuschanged' => $this->controller->Amo->getNOW());
                        $this->controller->File->save($fileData);
                        
                        // Move to public rsync repo
                        $file = $this->controller->File->read();
                        $this->controller->Amo->copyFileToPublic($approvalData['addon_id'], $file['File']['filename']);
                    }
                }
            }
            
            $this->controller->Approval->save($approvalData);
            $this->controller->Addon->save($addonData);
        }
        else {
            return false;
        }
        
        if (!empty($addon['User'])) {
            foreach ($addon['User'] as $user) {
                $authors[] = $user['email'];
            }
        }
        
        $emailInfo = array('name' => $addon['Translation']['name']['string'],
                           'id' => $this->controller->Addon->id,
                           'reviewer' => $session['firstname'].' '.$session['lastname'],
                           'email' => implode(', ', $authors),
                           'comments' => $data['Approval']['comments'],
                           'version' => !empty($version) ? $version['Version']['version'] : ''
                           );
        
        $this->controller->set('info', $emailInfo);
        
        if ($data['Approval']['ActionField'] != 'superreview') {
            $this->controller->Email->template = 'email/nominated/'.$data['Approval']['ActionField'];
            $this->controller->Email->to = $emailInfo['email'];
            $this->controller->Email->subject = sprintf('Instantbird Add-ons: %s Nomination', $emailInfo['name']);
        }
        else {
            $this->controller->Email->template = 'email/superreview';
            $this->controller->Email->to = 'team@instantbird.org';
            //Doesn't need to be localized
            $this->controller->Email->subject = "Super-review requested: {$emailInfo['name']}";  
        }
        $result = $this->controller->Email->send();
        
        return true;
    }

   /**
    * Process review for pending files
    * Update approval and file info and send emails
    * @param array $addon add-on information
    * @param array $data POST data
    */
    function reviewPendingFiles($addon, $data) {
        if (empty($data['Approval']['File'])) {
            $this->controller->addError(___('editor_review_error_no_files', 'Please select at least one file to review.'));
            return false;
        }
            
        $this->controller->Addon->id = $addon['Addon']['id'];
        $fileData = array('datestatuschanged' => $this->controller->Amo->getNOW());

        //Get most recent version
        $version = $this->controller->Version->findByAddon_id($this->controller->Addon->id, null, 'Version.created DESC');

        if ($data['Approval']['ActionField'] == 'public') {
            $fileData['status'] = STATUS_PUBLIC;
        }
        elseif ($data['Approval']['ActionField'] == 'sandbox') {
            $fileData['status'] = STATUS_SANDBOX;
        }
        elseif ($data['Approval']['ActionField'] == 'superreview') {
            $addonData = array('adminreview' => 1);
            $fileData['status'] = STATUS_PENDING;
        }
        else {
            $this->controller->Error->addError(___('editor_review_error_no_action', 'Please select a review action.'));
            return false;
        }
        
        if (empty($data['Approval']['comments'])) {
            $this->controller->Error->addError(___('editor_review_error_no_comments', 'Please enter review comments.'));
            return false;
        }

        if (empty($data['Approval']['applications'])) {
            $this->controller->Error->addError(___('editor_review_error_no_applications', 'Please enter the tested applications.'));
            return false;
        }
        
        if (empty($data['Approval']['os'])) {
            $this->controller->Error->addError(___('editor_review_error_no_operating_system', 'Please enter the tested operating systems.'));
            return false;
        }
        
        $session = $this->controller->Session->read('User');
        $platforms = $this->controller->Amo->getPlatformName();
        $files = array();
        
        // Loop through checked files
        foreach ($data['Approval']['File'] as $file_id) {
            if ($file_id > 0) {
                $this->controller->File->id = $file_id;
                $file = $this->controller->File->read();
                
                // Make sure file is pending review
                if ($file['File']['status'] != STATUS_PENDING) {
                    $this->controller->Error->addError(___('editor_review_error_file_not_pending', 'File not pending review.'));
                    return false;
                }
                
                $approvalData = array('user_id' => $session['id'],
                                      'reviewtype' => 'pending',
                                      'action' => $fileData['status'],
                                      'addon_id' => $this->controller->Addon->id,
                                      'file_id' => $file_id,
                                      'comments' => $data['Approval']['comments'],
                                      'os' => $data['Approval']['os'],
                                      'applications' => $data['Approval']['applications']
                                     );
                
                if ($this->controller->Error->noErrors()) {
                    // Save approval log and new file status
                    $this->controller->Approval->save($approvalData);
                    $this->controller->File->save($fileData);
                    
                    // Move to public rsync repo
                    if ($fileData['status'] == STATUS_PUBLIC) {
                        $this->controller->Amo->copyFileToPublic($approvalData['addon_id'], $file['File']['filename']);
                    }
                    
                    if (!empty($addonData)) {
                        $this->controller->Addon->save($addonData);
                    }
                    
                    $files[] = $addon['Translation']['name']['string'].' '.$version['Version']['version'].' - '.$platforms[$file['File']['platform_id']];
                }
                else {
                    return false;
                }
            }
        }
        
        if (!empty($addon['User'])) {
            foreach ($addon['User'] as $user) {
                $authors[] = $user['email'];
            }
        }
        
        $emailInfo = array('name' => $addon['Translation']['name']['string'],
                           'id' => $this->controller->Addon->id,
                           'reviewer' => $session['firstname'].' '.$session['lastname'],
                           'email' => implode(', ', $authors),
                           'comments' => $data['Approval']['comments'],
                           'os' => $data['Approval']['os'],
                           'apps' => $data['Approval']['applications'],
                           'version' => !empty($version) ? $version['Version']['version'] : '',
                           'files' => $files
                           );
        $this->controller->set('info', $emailInfo);
        
        if ($data['Approval']['ActionField'] != 'superreview') {
            $this->controller->Email->template = 'email/pending/'.$data['Approval']['ActionField'];
            $this->controller->Email->to = $emailInfo['email'];
            $this->controller->Email->subject = sprintf('Instantbird Add-ons: %s %s', $emailInfo['name'], $emailInfo['version']);
        }
        else {
            $this->controller->Email->template = 'email/superreview';
            $this->controller->Email->to = 'team@instantbird.org';
            //Doesn't need to be localized
            $this->controller->Email->subject = "Super-review requested: {$emailInfo['name']}";  
        }
        $result = $this->controller->Email->send();
        
        return true;
    }
    
    /**
     * Request more information from an author regarding an update/nomination
     * request
     */
    function requestInformation($addon, $data) {
        global $valid_status;
        
        // store information request
        $session = $this->controller->Session->read('User');
        foreach($data['Approval']['File'] as $_fid) {
            if ($_fid > 0) {
                $file_id = $_fid;
                break;
            }
        }
        $approvalData = array(
            'user_id' => $session['id'],
            'reviewtype' => 'info',
            'action' => 0,
            'addon_id' => $addon['Addon']['id'],
            'comments' => $data['Approval']['comments']
        );
        $this->controller->Approval->save($approvalData);
        $infoid = $this->controller->Approval->getLastInsertID();
        
        // send email to all authors
        $authors = array();
        foreach ($addon['User'] as &$user) $authors[] = $user['email'];
        
        $versionid = $this->controller->Version->getVersionByAddonId($addon['Addon']['id'], $valid_status);
        $version = $this->controller->Version->findById($versionid, null, null, -1);
        
        $emailInfo = array(
            'name' => $addon['Translation']['name']['string'],
            'infoid' => $infoid,
            'reviewer' => $session['firstname'].' '.$session['lastname'],
            'comments' => $data['Approval']['comments'],
            'version' => !empty($version) ? $version['Version']['version'] : ''
        );
        $this->controller->publish('info', $emailInfo, false);
        $this->controller->Email->template = 'email/inforequest';
        $this->controller->Email->to = implode(', ', $authors);
        $this->controller->Email->subject = sprintf('Instantbird Add-ons: %s %s', $emailInfo['name'], $emailInfo['version']);
        $this->controller->Email->send();
    }
    
    /**
     * Jump to specific item in queue
     * redirects to review page if item was found, to queue otherwise
     * @param string $listtype 'nominated' or 'pending'
     * @param int $rank list entry to jump to
     * @return void
     */
    function redirectByQueueRank($listtype, $rank) {
        switch($listtype) {
        case 'nominated':
            $addon = $this->controller->Addon->findAll(array('Addon.status' => STATUS_NOMINATED),
                array('Addon.id'), null, 1, $rank);
            if (!empty($addon)) {
                $addon = $this->controller->Addon->getAddon($addon[0]['Addon']['id'], array('latest_version'));
                if (!empty($addon['Version'])) {
                    $this->controller->redirect("/editors/review/{$addon['Version'][0]['id']}?num={$rank}");
                    return;
                }
            }
            break;
        
        case 'pending':
            $file = $this->controller->File->findAll(array('File.status'=>STATUS_PENDING),
                array('File.version_id'), null, 1, $rank);
            if (!empty($file)) {
                $this->controller->redirect("/editors/review/{$file[0]['File']['version_id']}?num={$rank}");
                return;
            }
            break;
        
        default:
            return false;
        }
        
        // if we did not find anything, redirect to queue
        $this->controller->redirect("/editors/queue/{$listtype}");
    }
    
    /**
     * Notify subscribed editors of an add-on's update
     * @param int $addonid ID of add-on that was updated
     * @param int $versionid ID of the add-on's new version
     */
    function updateNotify($addonid, $versionid) {
        $_ids = $this->controller->EditorSubscription->getSubscribers($addonid);
        if (empty($_ids)) return;
        $subscribers = $this->controller->User->findAllById($_ids, null, null, null, null, -1);
        
        $addon = $this->controller->Addon->getAddon($addonid);
        $version = $this->controller->Version->findById($versionid, null, null, null, null, -1);
        
        // send out notification email(s)
        $emailInfo = array(
            'id' => $addonid,
            'name' => $addon['Translation']['name']['string'],
            'versionid' => $versionid,
            'version' => $version['Version']['version']
        );
        $this->controller->publish('info', $emailInfo, false);
        
        $this->controller->Email->template = '../editors/email/notify_update';
        $this->controller->Email->subject = sprintf('Instantbird Add-ons: %s Updated', $emailInfo['name']);
        
        foreach ($subscribers as &$subscriber) {
            $this->controller->Email->to = $subscriber['User']['email'];
            $result = $this->controller->Email->send();
            // unsubscribe user from further updates
            $this->controller->EditorSubscription->cancelUpdates($subscriber['User']['id'], $addonid);
        }
        
    }
}
?>
