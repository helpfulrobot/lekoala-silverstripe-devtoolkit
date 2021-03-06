<?php
if (!defined('DEVTOOLKIT_PATH')) {
    define('DEVTOOLKIT_PATH', rtrim(basename(dirname(__FILE__))));
}

// Use _ss_environment.php, otherwise Director::isDev won't work properly
// See sample _ss_environment file in /ressources folder
require_once('conf/ConfigureFromEnv.php');

// Define logging - don't forget to disable access to log files in htaccess, see ressources folder for sample htaccess
ini_set('error_log', Director::baseFolder().'/error.log');
SS_Log::add_writer(new SS_LogFileWriter(Director::baseFolder().'/silverstripe.log'),
    SS_Log::INFO, '<=');

// Configure according to environment
if (Director::isDev()) {
    // Display all errors
    error_reporting(-1);

    // Add a debug logger
    SS_Log::add_writer(new SS_LogFileWriter(Director::baseFolder().'/debug.log'),
        SS_Log::DEBUG, '=');

    // Send emails to admin
    Email::send_all_emails_to(Email::config()->admin_email);

    // Disable DynamicCache
    if (class_exists('DynamicCache')) {
        DynamicCache::config()->enabled = false;
    }

    // See where are included files
    Config::inst()->update('SSViewer', 'source_file_comments', true);

    // Fix this issue https://github.com/silverstripe/silverstripe-framework/issues/4146
    if (isset($_GET['flush'])) {
        i18n::get_cache()->clean(Zend_Cache::CLEANING_MODE_ALL);
    }

    // Include Kint
    require_once(__DIR__.'/code/thirdparty/Kint/Kint.class.php');
} else {
    // In production, sanitize php environment to avoid leaking information
    ini_set('display_errors', false);

    // Warn admin if errors occur
    SS_Log::add_writer(new SS_LogEmailWriter(Email::config()->admin_email),
        SS_Log::ERR, '<=');

    // Prevent errors when Kint is called
    require_once(__DIR__.'/code/KintLiveShorthands.php');
}

// Protect website if env = isTest
if (Director::isTest()) {
    // If php runs under cgi, Http auth might not work by default. Don't forget to update htaccess
    if (!isset($_SERVER['PHP_AUTH_USER'])) {
        if (isset($_SERVER['HTTP_AUTHORIZATION']) && (strlen($_SERVER['HTTP_AUTHORIZATION'])
            > 0)) {
            list($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']) = explode(':',
                base64_decode(substr($_SERVER['HTTP_AUTHORIZATION'], 6)));
            if (strlen($_SERVER['PHP_AUTH_USER']) == 0 || strlen($_SERVER['PHP_AUTH_PW'])
                == 0) {
                unset($_SERVER['PHP_AUTH_USER']);
                unset($_SERVER['PHP_AUTH_PW']);
            }
        }
    }
    BasicAuth::protect_entire_site();
}

// CodeEditorField integration
if (class_exists('CodeEditorField')) {
    HtmlEditorConfig::get('cms')->enablePlugins(array(
        'aceeditor' => '../../../codeeditorfield/javascript/tinymce/editor_plugin_src.js'
    ));
    HtmlEditorConfig::get('cms')->insertButtonsBefore('fullscreen', 'aceeditor');
    HtmlEditorConfig::get('cms')->removeButtons('code');
}

if (defined('DEVTOOLKIT_USE_APC') && DEVTOOLKIT_USE_APC) {
    SS_Cache::add_backend('two_level', 'Two-Levels',
        array(
        'slow_backend' => 'File',
        'fast_backend' => 'APC',
        'slow_backend_options' => array(
            'cache_dir' => TEMP_FOLDER
        )
    ));
    SS_Cache::pick_backend('two_level', 'any', 10);
}
if (defined('DEVTOOLKIT_USE_MEMCACHED') && DEVTOOLKIT_USE_MEMCACHED) {
    // Note : this use the Memcache extension, not the Memcached extension
    // (with a d - which use libmemcached)
    SS_Cache::add_backend('two_level', 'Two-Levels',
        array(
        'slow_backend' => 'File',
        'fast_backend' => 'Memcached',
        'slow_backend_options' => array(
            'cache_dir' => TEMP_FOLDER
        ),
        'fast_backend_options' => array(
            'servers' => array(
                'host' => 'localhost',
                'port' => 11211,
                'persistent' => true,
                'weight' => 1,
                'timeout' => 5,
                'retry_interval' => 15,
                'status' => true,
                'failure_callback' => null
            )
        )
    ));
    SS_Cache::pick_backend('two_level', 'any', 10);
}

// Really basic newrelic integration
if(defined('NEWRELIC_APP_NAME')) {
    newrelic_set_appname(NEWRELIC_APP_NAME . ";Silverstripe");
}