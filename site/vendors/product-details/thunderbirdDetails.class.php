<?php

    define('LATEST_THUNDERBIRD_VERSION', '2.0.0.22');

    require_once dirname(__FILE__).'/productDetails.class.php';

    /**
     * Holds data related to the current version of Thunderbird.  
     *
     * Q: We're releasing a new version of Thunderbird - what should I do?
     * A:
     *      There is no single answer for this, because there are so many languages
     *      we support.  Use common sense.  A potential scenario is outlined below:
     *
     *      1) Update the LATEST_THUNDERBIRD_VERSION define to the latest version
     *      2) For each language which has the new version, update the filesizes in
     *          the LATEST_THUNDERBIRD_VERSION array
     *      3) For each language which does not have the new version, replace
     *          LATEST_THUNDERBIRD_VERSION with the previous version for that language.
     *      4) Edit history/thunderbirdHistory.class.php to reflect the new version and
     *          date
     *
     * @author Wil Clouser <clouserw@mozilla.com>
     *
     */
    class thunderbirdDetails extends productDetails {

        /**
         * Array holding information about current available builds.  Filesize 
         * is in megabytes. If you add a new language here, make sure it exists in
         * localeDetails::languages too.
         *
         *  If you don't want a download button to appear for a certain platform, just don't put that platform in the array
         *
         *  If you want "Not Yet Available" to appear for the locale, set the version to null.  If getDownloadBlockForLocale()
         *  is called, it will offer the most recent version that actually has a value.
         *
         * @var array
         */
        var $primary_builds = array(

                'af'    => array( LATEST_THUNDERBIRD_VERSION  => array('Windows' => array('filesize' => 6.2), 'OS X' => array('filesize' => 18.0), 'Linux' => array('filesize' => 11) )),

                'be'    => array( LATEST_THUNDERBIRD_VERSION  => array('Windows' => array('filesize' => 6.2), 'OS X' => array('filesize' => 18.0), 'Linux' => array('filesize' => 11) )),

                'bg'    => array( '1.5.0.14'                  => array('Windows' => array('filesize' => 6.1), 'OS X' => array('filesize' => 18.0), 'Linux' => array('filesize' => 10) ),
                                  LATEST_THUNDERBIRD_VERSION  => array('Windows' => array('filesize' => 6.2), 'OS X' => array('filesize' => 18.0), 'Linux' => array('filesize' => 11) )),

                'ca'    => array( '1.5.0.14'                  => array('Windows' => array('filesize' => 6.1), 'OS X' => array('filesize' => 18.0), 'Linux' => array('filesize' => 10) ),
                                  LATEST_THUNDERBIRD_VERSION  => array('Windows' => array('filesize' => 6.2), 'OS X' => array('filesize' => 18.0), 'Linux' => array('filesize' => 11) )),

                'cs'    => array( '1.5.0.14'                  => array('Windows' => array('filesize' => 6.1), 'OS X' => array('filesize' => 18.0), 'Linux' => array('filesize' => 10) ),
                                  LATEST_THUNDERBIRD_VERSION  => array('Windows' => array('filesize' => 6.2), 'OS X' => array('filesize' => 18.0), 'Linux' => array('filesize' => 11) )),

                'da'    => array( '1.5.0.14'                  => array('Windows' => array('filesize' => 6.1), 'OS X' => array('filesize' => 18.0), 'Linux' => array('filesize' => 10) ),
                                  LATEST_THUNDERBIRD_VERSION  => array('Windows' => array('filesize' => 6.2), 'OS X' => array('filesize' => 18.0), 'Linux' => array('filesize' => 11) )),

                'de'    => array( '1.5.0.14'                  => array('Windows' => array('filesize' => 6.1), 'OS X' => array('filesize' => 18.0), 'Linux' => array('filesize' => 10) ),
                                  LATEST_THUNDERBIRD_VERSION  => array('Windows' => array('filesize' => 6.2), 'OS X' => array('filesize' => 18.0), 'Linux' => array('filesize' => 11) )),

                'el'    => array( '1.5.0.14'                  => array('Windows' => array('filesize' => 6.1), 'OS X' => array('filesize' => 18.0), 'Linux' => array('filesize' => 10) ),
                                  LATEST_THUNDERBIRD_VERSION  => array('Windows' => array('filesize' => 6.2), 'OS X' => array('filesize' => 18.0), 'Linux' => array('filesize' => 11) )),

                'en-GB' => array( '1.5.0.14'                  => array('Windows' => array('filesize' => 6.1), 'OS X' => array('filesize' => 18.0), 'Linux' => array('filesize' => 10) ),
                                  LATEST_THUNDERBIRD_VERSION  => array('Windows' => array('filesize' => 6.2), 'OS X' => array('filesize' => 18.0), 'Linux' => array('filesize' => 11) )),

                'en-US' => array( '1.5.0.14'                  => array('Windows' => array('filesize' => 6.1), 'OS X' => array('filesize' => 18.0), 'Linux' => array('filesize' => 10) ),
                                  LATEST_THUNDERBIRD_VERSION  => array('Windows' => array('filesize' => 6.4), 'OS X' => array('filesize' => 19.0), 'Linux' => array('filesize' => 11) )),

                'es-AR' => array( '1.5.0.14'                  => array('Windows' => array('filesize' => 6.1), 'OS X' => array('filesize' => 18.0), 'Linux' => array('filesize' => 10) ),
                                  LATEST_THUNDERBIRD_VERSION  => array('Windows' => array('filesize' => 6.2), 'OS X' => array('filesize' => 18.0), 'Linux' => array('filesize' => 11) )),

                'es-ES' => array( '1.5.0.14'                  => array('Windows' => array('filesize' => 6.1), 'OS X' => array('filesize' => 18.0), 'Linux' => array('filesize' => 10) ),
                                  LATEST_THUNDERBIRD_VERSION  => array('Windows' => array('filesize' => 6.2), 'OS X' => array('filesize' => 18.0), 'Linux' => array('filesize' => 11) )),

                'eu'    => array( '1.5.0.14'                  => array('Windows' => array('filesize' => 6.1), 'OS X' => array('filesize' => 18.0), 'Linux' => array('filesize' => 10) ),
                                  LATEST_THUNDERBIRD_VERSION  => array('Windows' => array('filesize' => 6.2), 'OS X' => array('filesize' => 18.0), 'Linux' => array('filesize' => 11) )),

                'fi'    => array( '1.5.0.14'                  => array('Windows' => array('filesize' => 6.1), 'OS X' => array('filesize' => 18.0), 'Linux' => array('filesize' => 10) ),
                                  LATEST_THUNDERBIRD_VERSION  => array('Windows' => array('filesize' => 6.2), 'OS X' => array('filesize' => 18.0), 'Linux' => array('filesize' => 11) )),

                'fr'    => array( '1.5.0.14'                  => array('Windows' => array('filesize' => 6.1), 'OS X' => array('filesize' => 16.3), 'Linux' => array('filesize' => 8.2) ),
                                  LATEST_THUNDERBIRD_VERSION  => array('Windows' => array('filesize' => 6.5), 'OS X' => array('filesize' => 19.0), 'Linux' => array('filesize' => 11) )),

                'ga-IE' => array( '1.5.0.14'                  => array('Windows' => array('filesize' => 6.1), 'OS X' => array('filesize' => 18.0), 'Linux' => array('filesize' => 10) ),
                                  LATEST_THUNDERBIRD_VERSION  => array('Windows' => array('filesize' => 6.2), 'OS X' => array('filesize' => 18.0), 'Linux' => array('filesize' => 11) )),

                'gu-IN' => array( '1.5.0.14'                  => array('Windows' => array('filesize' => 6.1),     /* not available for OS X */     'Linux' => array('filesize' => 10) )),

                'he'    => array( '1.5.0.14'                  => array('Windows' => array('filesize' => 6.1), 'OS X' => array('filesize' => 18.0), 'Linux' => array('filesize' => 10) ),
                                  LATEST_THUNDERBIRD_VERSION  => array('Windows' => array('filesize' => 6.4),     /* not available for OS X */     'Linux' => array('filesize' => 11) )),

                'hu'    => array( '1.5.0.14'                  => array('Windows' => array('filesize' => 6.1), 'OS X' => array('filesize' => 18.0), 'Linux' => array('filesize' => 10) ),
                                  LATEST_THUNDERBIRD_VERSION  => array('Windows' => array('filesize' => 6.2), 'OS X' => array('filesize' => 18.0), 'Linux' => array('filesize' => 11) )),

                'it'    => array( '1.5.0.14'                  => array('Windows' => array('filesize' => 6.1), 'OS X' => array('filesize' => 18.0), 'Linux' => array('filesize' => 10) ),
                                  LATEST_THUNDERBIRD_VERSION  => array('Windows' => array('filesize' => 6.2), 'OS X' => array('filesize' => 18.0), 'Linux' => array('filesize' => 8.9) )),

                'ja'    => array( '1.5.0.14'                  => array('Windows' => array('filesize' => 6.1), 'OS X' => array('filesize' => 18.0), 'Linux' => array('filesize' => 10) ),
                                  LATEST_THUNDERBIRD_VERSION  => array('Windows' => array('filesize' => 6.2), 'OS X' => array('filesize' => 18.0), 'Linux' => array('filesize' => 11) )),

                'ko'    => array( '1.5.0.14'                  => array('Windows' => array('filesize' => 6.1), 'OS X' => array('filesize' => 18.0), 'Linux' => array('filesize' => 10) ),
                                  LATEST_THUNDERBIRD_VERSION  => array('Windows' => array('filesize' => 6.2), 'OS X' => array('filesize' => 18.0), 'Linux' => array('filesize' => 8.9) )),

                'lt'    => array( '1.5.0.14'                  => array('Windows' => array('filesize' => 6.1), 'OS X' => array('filesize' => 18.0), 'Linux' => array('filesize' => 10) ),
                                  LATEST_THUNDERBIRD_VERSION  => array('Windows' => array('filesize' => 5.8), 'OS X' => array('filesize' => 19.0), 'Linux' => array('filesize' => 9.3) )),

                'mk'    => array( '1.5.0.14'                  => array('Windows' => array('filesize' => 6.1), 'OS X' => array('filesize' => 18.0), 'Linux' => array('filesize' => 10) ),
                                  LATEST_THUNDERBIRD_VERSION  => array('Windows' => array('filesize' => 6.2), 'OS X' => array('filesize' => 19.0), 'Linux' => array('filesize' => 11) )),

                'nb-NO' => array( '1.5.0.14'                  => array('Windows' => array('filesize' => 6.1), 'OS X' => array('filesize' => 18.0), 'Linux' => array('filesize' => 10) ),
                                  LATEST_THUNDERBIRD_VERSION  => array('Windows' => array('filesize' => 6.2), 'OS X' => array('filesize' => 19.0), 'Linux' => array('filesize' => 11) )),

                'nl'    => array( '1.5.0.14'                  => array('Windows' => array('filesize' => 6.1), 'OS X' => array('filesize' => 18.0), 'Linux' => array('filesize' => 10) ),
                                  LATEST_THUNDERBIRD_VERSION  => array('Windows' => array('filesize' => 6.2), 'OS X' => array('filesize' => 18.0), 'Linux' => array('filesize' => 11) )),

                'nn-NO' => array( LATEST_THUNDERBIRD_VERSION  => array('Windows' => array('filesize' => 6.2), 'OS X' => array('filesize' => 18.0), 'Linux' => array('filesize' => 11) )),

                'pa-IN' => array( '1.5.0.14'                  => array('Windows' => array('filesize' => 6.1),    /*  Not Available for OS X */     'Linux' => array('filesize' => 10) ),
                                  LATEST_THUNDERBIRD_VERSION  => array('Windows' => array('filesize' => 6.2), 'OS X' => array('filesize' => 18.0), 'Linux' => array('filesize' => 11) )),

                'pl'    => array( '1.5.0.14'                  => array('Windows' => array('filesize' => 6.1), 'OS X' => array('filesize' => 18.0), 'Linux' => array('filesize' => 10) ),
                                  LATEST_THUNDERBIRD_VERSION  => array('Windows' => array('filesize' => 7.0), 'OS X' => array('filesize' => 18.0), 'Linux' => array('filesize' => 12) )),

                'pt-BR' => array( '1.5.0.14'                  => array('Windows' => array('filesize' => 6.1), 'OS X' => array('filesize' => 18.0), 'Linux' => array('filesize' => 10) ),
                                  LATEST_THUNDERBIRD_VERSION  => array('Windows' => array('filesize' => 6.2), 'OS X' => array('filesize' => 18.0), 'Linux' => array('filesize' => 8.9) )),

                'pt-PT' => array( LATEST_THUNDERBIRD_VERSION  => array('Windows' => array('filesize' => 6.3), 'OS X' => array('filesize' => 18.0), 'Linux' => array('filesize' => 9.1) )),

                'ru'    => array( '1.5.0.14'                  => array('Windows' => array('filesize' => 6.1), 'OS X' => array('filesize' => 18.0), 'Linux' => array('filesize' => 10) ),
                                  LATEST_THUNDERBIRD_VERSION  => array('Windows' => array('filesize' => 6.3), 'OS X' => array('filesize' => 19.0), 'Linux' => array('filesize' => 9.9) )),

                'sk'    => array( '1.5.0.14'                  => array('Windows' => array('filesize' => 6.1), 'OS X' => array('filesize' => 18.0), 'Linux' => array('filesize' => 10) ),
                                  LATEST_THUNDERBIRD_VERSION  => array('Windows' => array('filesize' => 6.7), 'OS X' => array('filesize' => 19.0), 'Linux' => array('filesize' => 9.5) )),

                'sl'    => array( '1.5.0.14'                  => array('Windows' => array('filesize' => 6.1), 'OS X' => array('filesize' => 18.0), 'Linux' => array('filesize' => 10) ),
                                  LATEST_THUNDERBIRD_VERSION  => array('Windows' => array('filesize' => 6.2), 'OS X' => array('filesize' => 18.0), 'Linux' => array('filesize' => 8.9) )),

                'sv-SE' => array( '1.5.0.14'                  => array('Windows' => array('filesize' => 6.1), 'OS X' => array('filesize' => 18.0), 'Linux' => array('filesize' => 10) ),
                                  LATEST_THUNDERBIRD_VERSION  => array('Windows' => array('filesize' => 6.2), 'OS X' => array('filesize' => 18.0), 'Linux' => array('filesize' => 11) )),

                'tr'    => array( '1.5.0.14'                  => array('Windows' => array('filesize' => 6.1), 'OS X' => array('filesize' => 18.0), 'Linux' => array('filesize' => 10) ),
                                  LATEST_THUNDERBIRD_VERSION  => array('Windows' => array('filesize' => 6.2), 'OS X' => array('filesize' => 18.0), 'Linux' => array('filesize' => 8.9) )),

                'uk'    => array( LATEST_THUNDERBIRD_VERSION  => array('Windows' => array('filesize' => 6.5), 'OS X' => array('filesize' => 19.0), 'Linux' => array('filesize' => 11.0) )),

                'zh-CN' => array( '1.5.0.14'                  => array('Windows' => array('filesize' => 6.1), 'OS X' => array('filesize' => 18.0), 'Linux' => array('filesize' => 10) ),
                                  LATEST_THUNDERBIRD_VERSION  => array('Windows' => array('filesize' => 6.2), 'OS X' => array('filesize' => 18.0), 'Linux' => array('filesize' => 8.9) )),

                'zh-TW' => array( LATEST_THUNDERBIRD_VERSION  => array('Windows' => array('filesize' => 6.4), 'OS X' => array('filesize' => 19.0), 'Linux' => array('filesize' => 9.2) ))
        );

        /**
         * Array holding information about currently available beta builds
         *
         * @var array
         */
        var $beta_builds = array(
        );

        /**
         * Constructor.
         */
        function thunderbirdDetails() {
            parent::productDetails();
        }

        /**
         * Returns an HTML block with links for a certain locale
         *
         * @param string locale
         * @param array options no functionality; for compatibility with firefoxDetails
         * @return string HTML block
         */
        function getAncillaryLinksForLocale($locale, $options=array()) {
            $_current_version = $this->getNewestVersionForLocale($locale);

            $_release_notes               = ___('Release Notes');
            $_other_systems_and_languages = ___('Other Systems and Languages');

            $_return = <<<HTML_RETURN
            <p class="download-other">
                <a class="ancillaryLink" href="http://www.mozillamessaging.com/{$locale}/thunderbird/{$_current_version}/releasenotes/">{$_release_notes}</a> -
                <a class="ancillaryLink" href="http://www.mozillamessaging.com/{$locale}/thunderbird/all.html">{$_other_systems_and_languages}</a>
            </p>
HTML_RETURN;

            return $_return;
        }

        /**
         * Overload parent function.  See parent for details.
         * 
         */
        function getDownloadBlockForLocale($locale, $options=array()) {

            $options['product'] = array_key_exists('product', $options) ?
            $options['product'] : 'thunderbird';
            # Used on the sidebar only
            $options['download_title'] = ___('Get Thunderbird');

            return parent::getDownloadBlockForLocale($locale, $options);
        }

        /**
         * Convenience function to return a <table> of Thunderbird primary builds
         *
         * @param array options (more detail in getDownloadBlockForLocale())
         * @return string HTML block
         */
        function getDownloadTableForPrimaryBuilds($options=array()) {

            $options['product']        = array_key_exists('product', $options) ? $options['product'] : 'thunderbird';
            $options['latest_version'] = LATEST_THUNDERBIRD_VERSION;

            return $this->tweakString($this->_getDownloadTableFromBuildArray($this->primary_builds, $options), $options);
        }

        /**
         * Convenience function to return a <table> of Thunderbird beta builds
         *
         * @param array options (more detail in getDownloadBlockForLocale())
         * @return string HTML block
         */
        function getDownloadTableForBetaBuilds($options=array()) {

            $options['product']        = array_key_exists('product', $options) ? $options['product'] : 'thunderbird';
            $options['latest_version'] = LATEST_THUNDERBIRD_VERSION;

            return $this->tweakString($this->_getDownloadTableFromBuildArray($this->beta_builds, $options), $options);
        }

        /**
         * Return a <table> with the links to the older versions of all locales with
         * primary builds.  We keep links to a single previous version.
         *
         * @return string HTML block
         */
        function getDownloadTableForOlderPrimaryBuilds() {

            $options['product']        = array_key_exists('product', $options) ? $options['product'] : 'thunderbird';
            $options['latest_version']  = LATEST_THUNDERBIRD_VERSION;
            $options['product_version'] = 'oldest';

            return $this->tweakString($this->_getDownloadTableFromBuildArray($this->primary_builds, $options), $options);
        }

        /**
         * Return a <table> with the links to the older versions of all locales with
         * beta builds.  We keep links to a single previous version.
         *
         * @return string HTML block
         */
        function getDownloadTableForOlderBetaBuilds() {

            $options['product']        = array_key_exists('product', $options) ? $options['product'] : 'thunderbird';
            $options['latest_version']  = LATEST_THUNDERBIRD_VERSION;
            $options['product_version'] = 'oldest';

            return $this->tweakString($this->_getDownloadTableFromBuildArray($this->beta_builds, $options), $options);
        }

        /**
         * TEMPORARY CODE
         *          20070827_TEMP
         * Overload parent function.  See parent for details.
         * 
         */
        function getNoScriptBlockForLocale($locale, $options=array()) {

            $options['product'] = array_key_exists('product', $options) ? $options['product'] : 'thunderbird';

            return parent::getNoScriptBlockForLocale($locale, $options);
        }

    }
?>
