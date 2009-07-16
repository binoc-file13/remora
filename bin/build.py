#!/usr/bin/env python
"""Mozilla Add-ons build script

If you update this script to touch a file in SVN you MUST update clean.sh
to remove that file.  On preview things are run in the following order:
  `./clean.sh` then `svn up` then `./build.py`
"""

__license__ = """\
***** BEGIN LICENSE BLOCK *****
Version: MPL 1.1/GPL 2.0/LGPL 2.1

The contents of this file are subject to the Mozilla Public License Version 
1.1 (the "License"); you may not use this file except in compliance with 
the License. You may obtain a copy of the License at 
http://www.mozilla.org/MPL/

Software distributed under the License is distributed on an "AS IS" basis,
WITHOUT WARRANTY OF ANY KIND, either express or implied. See the License
for the specific language governing rights and limitations under the
License.

The Original Code is the addons.mozilla.org site.

The Initial Developer of the Original Code is
Frederic Wenzel <fwenzel@mozilla.com>.
Portions created by the Initial Developer are Copyright (C) 2008
the Initial Developer. All Rights Reserved.

Contributor(s):
    Brian Krausz <bkrausz@mozilla.com>

Alternatively, the contents of this file may be used under the terms of
either the GNU General Public License Version 2 or later (the "GPL"), or
the GNU Lesser General Public License Version 2.1 or later (the "LGPL"),
in which case the provisions of the GPL or the LGPL are applicable instead
of those above. If you wish to allow use of your version of this file only
under the terms of either the GPL or the LGPL, and not to allow others to
use your version of this file under the terms of the MPL, indicate your
decision by deleting the provisions above and replace them with the notice
and other provisions required by the GPL or the LGPL. If you do not delete
the provisions above, a recipient may use your version of this file under
the terms of any one of the MPL, the GPL or the LGPL.

***** END LICENSE BLOCK *****
"""

import sys, os
from subprocess import Popen, PIPE
import xml.parsers.expat
from tempfile import mkstemp

# contants
REVISIONS_PHP_TEMPLATE = """\
<?php
define('REVISION', %d);
define('CSS_REVISION', %d);
define('JS_REVISION', %d);
?>"""


# globals
script_dir = os.path.dirname(sys.argv[0])
java = None # path to Java runtime


class RevisionParser(object):
    """XML parser to get latest SVN revision off 'svn info'"""
    parser = None
    __rev = 0
    
    def __createParser(self):
        """create XML parser object"""
        self.__rev = 0
        self.parser = xml.parsers.expat.ParserCreate()
        self.parser.StartElementHandler = self.__revisionElementParser
    
    def __revisionElementParser(self, name, attrs):
        """Element parser, pulling latest revision out of elements passed to it by expat"""
        if name == "commit":
            self.__rev = attrs['revision']
        
    def getLatestRevision(self, repo):
        """For a given SVN repository, find the latest changed revision
        
        returns the revision number (int), or 0 in the case of error"""
        try:
            self.__createParser()
            svninfo = Popen(["svn", "info", "--xml", repo], stdout=PIPE).communicate()[0]
            self.parser.Parse(svninfo, True)
            return int(self.__rev)
        except:
            return 0
    
    def getMaxRevision(self, repos):
        """From a list of SVN repositories, get their maximum revision"""
        return max([self.getLatestRevision(repo) for repo in repos])


class Minifier(object):
    """Concatenate and minify JS and CSS files"""
    
    def concatFiles(self, destName=None, sourceNames=[]):
        """concatenate some source files into a destination file
        
        Parameters:
        destName -- path of destination file; if None, temporary file is created
        sourceNames -- list of: String (source file path) or Dict {prefix, file, suffix}
        Returns: destination file path or None if no source files provided
        """
        if not sourceNames: return None
        try:
            try:
                destinationFile = open(destName, "w")
            except TypeError: # can't open None
                tempfile = mkstemp()
                destinationFile = os.fdopen(tempfile[0], "w")
                destName = tempfile[1]
            try:
                for source in sourceNames:
                    try:
                        prefix = source['prefix']
                        sourceFileName = source['file']
                        suffix = source['suffix']
                    except TypeError:
                        prefix = suffix = ''
                        sourceFileName = source
                    sourceFile = open(sourceFileName)
                    try:
                        destinationFile.write(prefix)
                        destinationFile.writelines(sourceFile)
                        destinationFile.write(suffix)
                    finally:
                        sourceFile.close()
            finally:
                destinationFile.close()
            return destName
        except Exception, e:
            try:
                os.remove(tempfile[1])
            except:
                pass
            print e
    
    def minify(self, type, source, destination):
        """minify a JS or CSS file
        
        Parameters:
        type -- either 'js' or 'css'
        source -- path of source file
        destination -- path of destination file
        """
        compressor = Popen([java, '-jar', os.path.join(script_dir, 'yuicompressor-2.3.4', 'build', 'yuicompressor-2.3.4.jar'),
            '--type', type, source], stdout=PIPE)
        destFile = open(destination, 'w')
        destFile.writelines(compressor.stdout)
        destFile.close()


def updateRevisions():
    """Find latest revisions for tree, CSS and JS, and write them to revisions.php"""
    print "Updating Revisions"
    
    rp = RevisionParser()
    
    tree_rev = rp.getLatestRevision('https://svn.mozilla.org/addons/trunk/')
    print "Latest Tree Revision:", tree_rev
    
    css_repo = 'https://svn.mozilla.org/addons/trunk/site/app/webroot/css/'
    css_files = ['color.css', 'screen.css', 'type.css', 'print.css']
    css_rev = rp.getMaxRevision([ css_repo + css_file for css_file in css_files ])
    print "CSS Revision:", css_rev
    
    js_repo = 'https://svn.mozilla.org/addons/trunk/site/app/webroot/js/'
    js_files = ['jquery-compressed.js', 'addons.js']
    js_rev = rp.getMaxRevision([ js_repo + js_file for js_file in js_files ])
    print "JS Revision:", js_rev
    
    revs_file = os.path.join(script_dir, '..', 'site', 'app', 'config', 'revisions.php')
    try:
        rf = open(revs_file, 'w')
        rf.write(REVISIONS_PHP_TEMPLATE % (tree_rev, css_rev, js_rev))
        rf.close()
    except IOError, e:
        print "Error writing revision.php file:", e


def concatAndMinify():
    """concatenate and minify JS and CSS files"""
    minifier = Minifier()
    
    webroot = os.path.join(script_dir, '..', 'site', 'app', 'webroot')
    
    print 'Concatenating JS'
    js_files = ['jquery-compressed.js', 'addons.js']
    js_concatenated = minifier.concatFiles(sourceNames=[ os.path.join(webroot, 'js', js_file) for js_file in js_files ])
    print 'Minifying JS'
    minifier.minify('js', os.path.join(webroot, 'js', '__utm.js'), os.path.join(webroot, 'js', '__utm.min.js'))
    minifier.minify('js', js_concatenated, os.path.join(webroot, 'js', 'jquery.addons.min.js'))
    os.remove(js_concatenated)
    
    print 'Concatenating CSS'
    css_concatenated = minifier.concatFiles(sourceNames=[
        os.path.join(webroot, 'css', 'type.css'),
        os.path.join(webroot, 'css', 'color.css'),
        {'prefix': "@media screen, projection {", 'file': os.path.join(webroot, 'css', 'screen.css'), 'suffix':'}'},
        {'prefix': "@media print {", 'file': os.path.join(webroot, 'css', 'print.css'), 'suffix':'}'},
        ])
    print 'Minifying CSS'
    minifier.minify('css', css_concatenated, os.path.join(webroot, 'css', 'style.min.css'))
    os.remove(css_concatenated)


def compilePo():
    """compile all .po files to Gettext .mo files"""
    print 'Compiling .po Files'
    localedir = os.path.join(script_dir, '..', 'site', 'app', 'locale')
    Popen([os.path.join(localedir, 'compile-mo.sh'), localedir])


def main(argv = None):
    global java
    
    if argv is None:
        argv = sys.argv
    
    try:
        java = argv[1]
    except IndexError:
        java = Popen(["which", "java"], stdout=PIPE).communicate()[0].strip()
    if not java:
        print "Usage: %s path_to_jre" % argv[0]
        sys.exit(1)
    
    updateRevisions()
    concatAndMinify()
    compilePo()
    print 'Done.'


if __name__ == "__main__":
    main()
