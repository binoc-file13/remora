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
 *   Ryan Doherty <rdoherty@mozilla.com>    
 *   l.m.orchard <lorchard@mozilla.com>
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

class Collection extends AppModel
{
    var $name = "Collection";

    var $hasAndBelongsToMany = array(
        'Addon' => array(
            'className' => 'Addon',
            'joinTable' => 'addons_collections',
            'foreignKey' => 'collection_id',
            'associationForeignKey' => 'addon_id'
        ),
        'Subscriptions' => array(
            'className' => 'User',
            'joinTable' => 'collection_subscriptions',
            'foreignKey' => 'collection_id',
            'associationForeignKey' => 'user_id'
        ),
        'Users' => array(
            'className' => 'User',
            'joinTable' => 'collections_users',
            'foreignKey' => 'collection_id',
            'associationForeignKey' => 'user_id'
        )
    );
    
    var $hasAndBelongsToMany_full = array(
        'Tag' => array(
            'className'  => 'Tag',
            'joinTable'  => 'collections_tags',
            'foreignKey' => 'collection_id',
            'associationForeignKey'=> 'tag_id'
        ),
        'Addon' => array(
            'className' => 'Addon',
            'joinTable' => 'addons_collections',
            'foreignKey' => 'collection_id',
            'associationForeignKey' => 'addon_id'
        ),
        'Subscriptions' => array(
            'className' => 'User',
            'joinTable' => 'collection_subscriptions',
            'foreignKey' => 'collection_id',
            'associationForeignKey' => 'user_id'
        ),
        'Users' => array(
            'className' => 'User',
            'joinTable' => 'collections_users',
            'foreignKey' => 'collection_id',
            'associationForeignKey' => 'user_id'
        )
    );

    var $validate = array(
        'name'          => VALID_NOT_EMPTY,
        'description'   => VALID_NOT_EMPTY
    );
    
    var $translated_fields = array(
        'name',
        'description',
    );

    const COLLECTION_TYPE_NORMAL = 0;
    const COLLECTION_TYPE_AUTOPUBLISHER = 1;
    const COLLECTION_TYPE_EDITORSPICK = 2;

    /**
     * Generates a pseudo-random UUID.
     * Slightly modified version of a function submitted to php.net:
     * http://us2.php.net/manual/en/function.com-create-guid.php#52354
     *
     * @access public
     */
    function uuid() {
        mt_srand((double)microtime()*10000);
        $charid = md5(uniqid(rand(), true));
        $hyphen = chr(45);// "-"
        $uuid = substr($charid, 0, 8).$hyphen
              . substr($charid, 8, 4).$hyphen
              . substr($charid,12, 4).$hyphen
              . substr($charid,16, 4).$hyphen
              . substr($charid,20,12);

        return $uuid;
    } 

    /**
     * Before saving, set a UUID if none yet set.
     */
    function beforeSave() {
        if (empty($this->id) && empty($this->data[$this->name]['id'])) {
            // If no ID set yet, assume this is a new record and give it a UUID
            $this->data[$this->name]['uuid'] = $this->uuid();
        }
        return parent::beforeSave();
    }
    
    /**
     * Subscribes a user to a collection
     *
     * @param int $id - the id of the collection
     * @param int $userId - the id of the user
     */
    function subscribe($id, $userId) {
        return $this->execute("INSERT INTO collection_subscriptions (collection_id, user_id) VALUES({$id}, {$userId})");
    }
    
    /**
     * Unsubscribe a user to a collection
     *
     * @param int $id - id of the collection
     * @param int $userId - id of user
     */
    function unsubscribe($id, $userId) {
        return $this->execute("DELETE FROM collection_subscriptions WHERE user_id = {$userId} AND collection_id = {$id}");
    }
    
    /**
     * Add an add-on to a collection
     *
     * @param int $collectionId - collection id
     * @param int $addonId - add-on id
     */
    function addAddonToCollection($collection_id, $user_id, $addon_id) {
        $db =& ConnectionManager::getDataSource($this->useDbConfig);
        
        $collection_id = $db->value($collection_id);
        $user_id = $db->value($user_id);
        $addon_id = $db->value($addon_id);

        return $this->execute("
            INSERT INTO addons_collections 
            (addon_id, user_id, collection_id, added) 
            VALUES 
            ({$addon_id}, {$user_id}, {$collection_id}, NOW())
        ");
    }
    
    /**
     * Adds a user to a collection so they can edit it
     *
     * @param int $collectionId - id of the collection
     * @param int $userId - id of the user
     * @param int $role - role type
     */
    function addUser($collectionId, $userId, $role) {
        return $this->execute("INSERT INTO collections_users (collection_id, user_id, role) VALUES ({$collectionId}, {$userId}, {$role})");
    }
    
    /**
     * Deletes a collection
     *
     * @param int $id - collection id
     */
    function delete($id) {
        $this->execute("DELETE FROM collections_users WHERE collection_id = {$id}");
        $this->execute("DELETE FROM addons_collections WHERE collection_id = {$id}");
        $this->execute("DELETE FROM collections_tags WHERE collection_id = {$id}");
        $this->execute("DELETE FROM collection_subscriptions WHERE collection_id = {$id}");
        $this->execute("DELETE FROM collections WHERE id = {$id}");
        return true;
    }
    
    /**
     * Remove a user
     * 
     * @param int $collectionId - id of the collection
     * @param int $userId - id of the user
     */
    function removeUser($collectionId, $userId) {
        return $this->execute("DELETE FROM collections_users WHERE user_id = {$userId} AND collection_id={$collectionId}");
    }

    /**
     * Get a list of users and roles
     * 
     * @param int id of the collection
     * @param array (optional) list of roles for which users should be fetched
     */
    function getUsers($collectionId, $roles=null) {
        if (!is_numeric($collectionId)) return null;
        
        // Build SQL to look up user IDs and roles for collection
        $sql = "
            SELECT user_id, role 
            FROM collections_users 
            WHERE collection_id={$collectionId}
        ";

        // Add an IN clause if roles supplied.
        if (null !== $roles && is_array($roles)) {
            $s_roles = array();
            foreach ($roles as $role) if (is_numeric($role)) 
                $s_roles[] = $role;
            $sql .= " AND role IN ( ". join(',', $s_roles) . " )";
        }

        // Fetch the rows and map them to user IDs.
        $rows = $this->execute($sql);
        $user_map = array();
        foreach ($rows as $row) {
            $user_map[$row['collections_users']['user_id']] = 
                $row['collections_users'];
        }

        // Look up users with user IDs, merge the role info into each found.
        $users = $this->User->findAllById(array_keys($user_map));
        for ($i=0; $i<count($users); $i++) {
            // HACK: CollectionUser used in lieu of an actual model class.
            $users[$i]['CollectionUser'] =
                $user_map[$users[$i]['User']['id']];
        }

        return $users;
    }

    /**
     * Decide whether or not a given collection is writable by a user.
     * 
     * @param int id of the collection
     * @param int id of the user
     */
    function isWritableByUser($collection_id, $user_id) {
        
        if (!is_numeric($collection_id)) return null;
        if (!is_numeric($user_id)) return null;

        $roles = join(',', array(
            COLLECTION_ROLE_OWNER, 
            COLLECTION_ROLE_ADMIN, 
            COLLECTION_ROLE_PUBLISHER 
        ));

        $rows = $this->execute("
            SELECT user_id 
            FROM collections_users 
            WHERE 
                collection_id={$collection_id} AND
                user_id={$user_id} AND
                role IN ( {$roles} )
        ");

        return !empty($rows);
    }

    /**
     * Look up the ID for a collection by UUID, less expensive than a full 
     * fetch.
     *
     * @param   string Collection UUID
     * @returns string Collection ID
     */
    function getIdForUuid($uuid) {
        $db =& ConnectionManager::getDataSource($this->useDbConfig);
        $uuid = $db->value($uuid);
        $rows = $this->execute("
            SELECT id
            FROM collections
            WHERE uuid={$uuid}
        ");
        $id = null;
        foreach ($rows as $row) {
            $id = $row['Collection']['id'];
        }
        return $id;
    }

    /**
     * Determine the last modified time for a collection, found either by
     * ID or UUID.  If a UUID is supplied, it's converted to an ID via 
     * query first.
     *
     * @param   string Collection ID
     * @param   string Collection UUID, replaces ID if supplied
     * @returns string Last modified date for collection and addons
     */
    function getLastModifiedForCollection($id=null, $uuid=null) {
        $db =& ConnectionManager::getDataSource($this->useDbConfig);

        if (null !== $uuid) {
            $id = $this->getIdForUUID($uuid);
        }

        $id = $db->value($id);

        $dates = array();

        $rows = $this->execute("
            SELECT added, modified
            FROM addons_collections
            WHERE collection_id={$id}
            ORDER BY added DESC
            LIMIT 1
        ");
        foreach ($rows as $row) {
            $dates[] = $row['addons_collections']['added'];
            $dates[] = $row['addons_collections']['modified'];
        }
            
        $rows = $this->execute("
            SELECT modified
            FROM collections
            WHERE id={$id} 
        ");
        foreach ($rows as $row) {
            $dates[] = $row['Collection']['modified'];
        }

        if (empty($dates)) return null;
        rsort($dates);
        return strtotime($dates[0]);
    }

}
