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
 *   Mike Morgan <morgamic@mozilla.com> (Original Author)
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

class AdminServerStatusTest extends WebTestHelper {

    function AdminServerStatusTest() {
        $this->WebTestCase("Views->Admin->Server Status Test");

        loadModel('Memcaching');
    }

    function setUp() {
        $this->cache =& new Memcaching();

        $this->login();
        $this->getAction('/admin/serverstatus/');
    }

    function testRemoraPage() {
        $this->assertWantedPattern('/Mozilla Add-ons/i', "pattern detected");
        $this->assertResponse('200');
    }

    function testMemcacheStatus() {
        if (defined('QUERY_CACHE') && QUERY_CACHE) {

            if ($_data = $this->cache->getExtendedStats()) {

                foreach ($_data as $server=>$stats) {
                    $pattern = '#<h3>'.$server.'</h3>#';
                    $this->assertWantedPattern($pattern, htmlentities($pattern));

                    if (!empty($stats) && is_array($stats)) {
                        $pattern = '#<li>Gets: [0-9]+</li>#';
                        $this->assertWantedPattern($pattern, htmlentities($pattern));

                        $pattern = '#<li>Misses: [0-9]+</li>#';
                        $this->assertWantedPattern($pattern, htmlentities($pattern));

                        $pattern = '#<li>Total Gets: [0-9]+</li>#';
                        $this->assertWantedPattern($pattern, htmlentities($pattern));

                        $pattern = '#<li>Hit %: [0-9]+.[0-9]+</li>#';
                        $this->assertWantedPattern($pattern, htmlentities($pattern));

                        $pattern = '#<li>Quota %: [0-9]+.[0-9]+</li>#';
                        $this->assertWantedPattern($pattern, htmlentities($pattern));

                        foreach ($stats as $key=>$val) {
                            $pattern = "/{$key}: [0-9]+/";
                            $this->assertWantedPattern($pattern, htmlentities($pattern));
                        }
                    }
                }
            }
        } else {
            $pattern = '#<p>Memcache is not enabled \(QUERY_CACHE is false\)\.</p>#';
            $this->assertWantedPattern($pattern, htmlentities($pattern));
        }
    }

}
?>
