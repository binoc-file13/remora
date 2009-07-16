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

class AddonTest extends WebTestHelper {

    function AddonTest() {
        $this->WebTestCase("Views->Addons->Display Tests");
        loadModel('Addon');
        loadModel('Tag');
        loadModel('Version');
    }

    function setUp() {
        $this->id = 7;//$_GET['id'];

        $model =& new Addon();
        $model->caching = false;

        $tagModel =& new Tag();
        $tagModel->caching = false;

        $versionModel =& new Version();
        $versionModel->caching = false;

        $this->data = $model->find("Addon.id=$this->id", null , null , 2);
        $this->data['Version'] = $versionModel->findAll("Version.addon_id=$this->id", null, "Version.created DESC", 0);
        //get tag l10n data
        foreach ($this->data['Tag'] as $tagkey => $tagvalue) {
            if ($tagkey == 0)
                $related_tag_query = "Tag.id='${tagvalue['id']}'";
            else
                $related_tag_query = $related_tag_query . " OR Tag.id ='${tagvalue['id']}'";    
        }
        $this->tagData = $tagModel->findAll($related_tag_query);
        
        $this->getAction("/addon/" . $this->id);

        global $TestController;
        $this->helper = new UnitTestHelper();
        $this->controller = $this->helper->getController('Addons', $this);
        $this->controller->base = $TestController->base;
        loadComponent('Image');
        $this->controller->Image =& new ImageComponent();
        $this->controller->Image->startup($this->controller);
    }

    function testRemoraPage() {
        //just checks if the page works or not
        $this->assertWantedPattern('/Mozilla Add-ons/i', "pattern detected");
    }

    function testDisplay() {
        // Title
        $this->title = sprintf(_('addons_display_pagetitle'), $this->data['Translation']['name']['string']). ' :: '.sprintf(_('addons_home_pagetitle'), APP_PRETTYNAME);
        $this->assertTitle($this->title);
        // Author
        $username = $this->data['User'][0]['nickname'];
        $userid = $this->data['User'][0]['id'];
        $this->actionPath = $this->actionPath(""); 
        $this->authorPattern = "@<h4 class=\"author\">by +<a href=\"{$this->actionPath}/user/{$userid}\"  class=\"profileLink\">{$username}</a> ?</h4>@";
        $this->assertWantedPattern($this->authorPattern, htmlentities($this->authorPattern));
        // Icon
        $wantedPattern = "#<img src=\"" . $this->controller->Image->getAddonIconURL($this->id) . "\" class=\"addon-icon\" alt=\"\" />#";
        $this->assertWantedPattern($wantedPattern, htmlentities($wantedPattern));
        // Preview
        $wantedPattern = '#<img src="' . $this->controller->Image->getHighlightedPreviewURL($this->id) . '" alt="" />#';
        $this->assertWantedPattern($wantedPattern, htmlentities($wantedPattern));
        //@TODO Size: Figure out some way to use the Number Helper in this test
        //$this->wantedPattern = "#<span>\(" . $this->data['Version'][0]['File'][0]['size'] . "KB\)</span>#";
        //$this->assertWantedPattern($this->wantedPattern, htmlentities($this->wantedPattern));
        
        //check the main version area
        $this->wantedPattern = "@<h5>" . sprintf(_('addon_display_header_version'), $this->data['Version'][0]['Version']['version']) . " <span title=\"" . strftime(_('datetime'), strtotime($this->data['Version'][0]['Version']['created'])) . "\">&mdash; " . strftime(_('date'), strtotime($this->data['Version'][0]['Version']['created'])) . "</span> &mdash; .*</h5>@";
        $this->assertWantedPattern($this->wantedPattern, htmlentities($this->wantedPattern));
        $this->wantedPattern = "#<p {$this->data['Version'][0]['Translation']['releasenotes']['locale_html']}>" . $this->data['Version'][0]['Translation']['releasenotes']['string'] . "</p>#";
        $this->assertWantedPattern($this->wantedPattern, htmlentities($this->wantedPattern));
        // check the version at the top title
        $this->wantedPattern = "#" . $this->data['Version'][0]['Version']['version'] . "#";
        $this->assertWantedPattern($this->wantedPattern, htmlentities($this->wantedPattern));
        // check if previous versions link is displayed
        $this->wantedPattern = _('addons_display_version_history');
        $this->assertLink($this->wantedPattern, htmlentities($this->wantedPattern));
        // tags
        foreach ($this->tagData as $tag) {
            $this->wantedPattern = "@<li><a href=\"[^\"]+\"( )*>" . $tag['Translation']['name']['string'] . "</a></li>@";
            $this->assertWantedPattern($this->wantedPattern, htmlentities($this->wantedPattern));
        }		        
        // are reviews displayed?
        $this->wantedPattern = "@"._('addons_display_header_reviews')."@";
        $this->assertWantedPattern($this->wantedPattern, htmlentities($this->wantedPattern));

        $this->wantedPattern = "@It works but not well.@";
        $this->assertWantedPattern($this->wantedPattern, htmlentities($this->wantedPattern));

        // If gettext can't find the translation, it just returns what it was given
        $this->assertNotEqual(_('addons_display_version_history'), 'addons_display_version_history');
    }
    
    /**
     * bug 412580 was a bug about some UTF-8 characters breaking out HTML sanitization.
     * Make sure this does not happen anymore.
     */
    function testSanitization() {
        $this->wantedPattern = '@sanitization of signs like &amp; and &quot;@';
        $this->assertWantedPattern($this->wantedPattern, htmlentities($this->wantedPattern));
    }
}
?>
