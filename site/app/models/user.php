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
 * Portions created by the Initial Developer are Copyright (C) 2006
 * the Initial Developer. All Rights Reserved.
 *
 * Contributor(s):
 *   Andrei Hajdukewycz <sancus@off.net> (Original Author)
 *   Justin Scott <fligtar@gmail.com>
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

class User extends AppModel
{
    var $name = 'User';
    var $hasAndBelongsToMany = array('Group' =>
                                          array('className'             => 'Group',
                                                'joinTable'             => 'groups_users',
                                                'foreignKey'            => 'user_id',
                                                'associationForeignKey' => 'group_id'
                                         )
                                    );

    var $hasAndBelongsToMany_full = array('Addon' =>
                                          array('className'             => 'Addon',
                                                'joinTable'             => 'addons_users',
                                                'foreignKey'            => 'user_id',
                                                'associationForeignKey' => 'addon_id'
                                          ),
                                      'CollectionSubscriptions' =>
                                          array('className'             => 'Collection',
                                                'joinTable'             => 'collection_subscriptions',
                                                'foreignKey'            => 'user_id',
                                                'associationForeignKey' => 'collection_id'
                                         ),
                                      'Collections' => 
                                          array('className'             => 'Collection',
                                                'joinTable'             => 'collections_users',
                                                'foreignKey'            => 'user_id',
                                                'associationForeignKey' => 'collection_id')
                                    );
    var $hasMany_full = array('Approval' =>
                              array('className'   => 'Approval',
                                    'conditions'  => '',
                                    'order'       => '',
                                    'limit'       => '',
                                    'foreignKey'  => 'user_id',
                                    'dependent'   => true,
                                    'exclusive'   => false,
                                    'finderSql'   => ''
                              ),
                              'Reviewrating' =>
                              array('className'   => 'Reviewrating',
                                    'conditions'  => '',
                                    'order'       => '',
                                    'limit'       => '',
                                    'foreignKey'  => 'user_id',
                                    'dependent'   => true,
                                    'exclusive'   => false,
                                    'finderSql'   => ''
                              ),
                              'Favorite' =>
                              array('className'   => 'Favorite',
                                    'conditions'  => '',
                                    'order'       => '',
                                    'limit'       => '',
                                    'foreignKey'  => 'user_id',
                                    'dependent'   => true,
                                    'exclusive'   => false,
                                    'finderSql'   => ''
                              ),
                              'Review' =>
                              array('className'   => 'Review',
                                    'conditions'  => '',
                                    'order'       => '',
                                    'limit'       => '',
                                    'foreignKey'  => 'user_id',
                                    'dependent'   => true,
                                    'exclusive'   => false,
                                    'finderSql'   => ''
                              ),
                         );

    var $translated_fields = array('bio');

    var $validate = array(
        'email'     => VALID_EMAIL,
        'password'  => VALID_NOT_EMPTY,
        'homepage'  => VALID_URL_OPT
    );

    /**
     * Get number of add-ons this user is affiliated with
     *
     * @param int $id user id
     * @return int number of add-ons
     */
    function getAddonCount($id) {
        $res = $this->execute("SELECT COUNT(*) as c FROM addons_users AS au WHERE au.user_id = '{$id}';");
        return $res[0][0]['c'];
    }
    
    /**
     * Anonymize a user account.
     * This is the user-facing "delete account" feature, which does not delete
     * the actual row in the DB (to prevent untraceable spam etc.), but removes
     * all personal information from the user account.
     *
     * @param int $id user id
     * @return bool success
     */
    function anonymize($id) {
        // generate anonymized data array
        $data = array('User' => array(
            'id' => $id,
            'email' => null,
            'password' => '', // empty pw will result in login failure
            'firstname' => '',
            'lastname' => '',
            'nickname' => 'Deleted User',
            'homepage' => ''
            ));
        return $this->save($data, false, array_keys($data['User']));
    }
    
    /**
     * Enforce one of the name fields not to be empty
     */
    function beforeValidate() {
        if (!$this->data) return false;
        if (array_key_exists('nickname', $this->data['User']) &&
            array_key_exists('lastname', $this->data['User']) &&
            array_key_exists('firstname', $this->data['User']) &&
            empty($this->data['User']['nickname']) && empty($this->data['User']['firstname'])
            && empty($this->data['User']['lastname'])) {
            $this->invalidate('firstname');
            $this->invalidate('lastname');
            $this->invalidate('nickname');
        }
        
        return parent::beforeValidate();
    }

    /* Password handling inspired by Django. */

    /**
     * Check a raw password against the User's stored password.
     * If the User has an old-style md5 password it will be updated
     * to the new hashing scheme if the $rawPassword checks.
     *
     * $self must be an assoc array containing 'password' and 'id'.
     */
    function checkPassword($self, $rawPassword) {
        $storedPassword = $self['password'];
        if (strpos($storedPassword, '$') === false) {
            // Old-style password.
            $hashedPassword = md5($rawPassword);
            $valid = !empty($storedPassword) && $storedPassword == $hashedPassword;
            // Update to the new scheme.
            if ($valid) {
                // Using SQL so we don't upset $this->User.
                $newPassword = $this->createPassword($rawPassword);
                $this->execute("UPDATE users
                                SET `password`='{$newPassword}'
                                WHERE `id`={$self['id']}");
            }
            return $valid;
        }
        return $this->_checkPassword($rawPassword, $storedPassword);
    }

    /**
     * Validate a new-style password.
     */
    function _checkPassword($rawPassword, $encPassword) {
        if (empty($encPassword)) {
            return false;
        }
        list($algo, $salt, $storedPassword) = split('\$', $encPassword);
        $hashedPassword = $this->getHexDigest($algo, $salt, $rawPassword);
        // Check isset to make sure the split worked.
        return isset($storedPassword) && $storedPassword == $hashedPassword;
    }

    /**
     * Create a password that looks like '$algorithm$salt$encrypted'.
     */
    function createPassword($rawPassword, $algo='sha512') {
        // 64 chars ought to be enough salt for anybody.
        $salt = $this->getHexDigest($algo, uniqid(rand(), true), uniqid(rand(), true));
        $salt = substr($salt, 0, 64);

        $hashedPassword = $this->getHexDigest($algo, $salt, $rawPassword);
        $password = $algo.'$'.$salt.'$'.$hashedPassword;
        return $password;
    }

    /**
     * Returns a string of the hexdigest of the given plaintext password and
     * salt using the given algorithm.
     */
    function getHexDigest($algo, $salt, $rawPassword) {
        return hash($algo, $salt.$rawPassword);
    }

    function setResetCode($user_id) {
        $code = md5(mt_rand());
        $expires = strtotime(PASSWORD_RESET_EXPIRES.' days');
        $this->save(array('id' => $user_id,
                          'resetcode' => $code,
                          'resetcode_expires' => date('Y-m-d H:i:s', $expires)));
        return $code;
    }

    function checkResetCode($user_id, $code) {
        $user = $this->find(array("User.id = {$user_id}",
                                  "User.resetcode_expires > NOW()"));
        /* Backwards compat, should be removed after push on 4/9/09. */
        if (!$user) {
            $user = $this->findById($user_id);
            return (empty($user['User']['resetcode']) &&
                    $code == md5($user['User']['password']));
        }
        return $user && $code == $user['User']['resetcode'];
    }
    
    /**
     * Get subscriptions
     *
     * @param int $userId user id
     */
    function getSubscriptions($userId) {

        // Just bind to the collection subscriptions relation.
        $this->bindModel(array(
            'hasAndBelongsToMany' => array(
                'CollectionSubscriptions' => 
                    $this->hasAndBelongsToMany_full['CollectionSubscriptions']
            )
        ));
        $user = $this->findById($userId);

        $collectionIds = array();
        //Fetch collections to get translations
        foreach($user['CollectionSubscriptions'] as $collection) {
           $collectionIds[] = $collection['id'];
        }

        $criteria = array('Collection.id' => $collectionIds);
        $subscriptions = $this->Collection->findAll($criteria);
        return $subscriptions;
    }
    
    /**
     * Get collections
     * 
     * @param int $userId user id
     */
    function getCollections($userId) {

        // Just bind to the collections relation.
        $this->bindModel(array(
            'hasAndBelongsToMany' => array(
                'Collections' => 
                    $this->hasAndBelongsToMany_full['Collections'],
            )
        ));
        $user = $this->findById($userId);

        $collectionIds = array();
        $collections = array();
        
        foreach($user['Collections'] as $collection) {
            $collectionIds[] = $collection['id'];
        }
        
        if (!empty($collectionIds)) {
            $criteria = array(
                'Collection.id' => $collectionIds,
            );
            $collections = $this->Collection->findAll($criteria, null, 'Translation.name');
        }
        return $collections;
    }
}
?>
