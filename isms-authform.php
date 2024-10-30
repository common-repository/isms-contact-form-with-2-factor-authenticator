<?php 
/* @package iSMS Contact Form with 2 Factor Authenticator*/
/**
 * Plugin Name:       iSMS Contact Form with 2 Factor Authenticator
 * Plugin URI:        https://www.isms.com.my
 * Description:       Contact Form with 2-Factor Authentication SMS OTP Verification
 * Version:           1.1
 * Requires at least: 5.4
 * Requires PHP:      5.6
 * Author:            Mobiweb
 * Author URI:        https://www.mobiweb.com.my
 * License:           GPLv2 or later
 * Text Domain:       isms-authform
 */

defined('ABSPATH') or die( 'Access Forbidden!' );

global $wpdb;
define('ISMS_AUTHFORM_FORM',$wpdb->prefix. "isms_authform_form" );
define('ISMS_AUTHFORM_SENT',$wpdb->prefix. "isms_authform_form_sent" );
define('ISMS_AUTHFORM_FORM_META',$wpdb->prefix. "isms_authform_form_form_meta" );
define('ISMS_AUTHFORM_FORM_MESSAGE',$wpdb->prefix. "isms_authform_form_message" );
define('ISMS_AUTHFORM_FORM_FIELDS',$wpdb->prefix. "isms_authform_form_fields" );
define('ISMS_AUTHFORM_OTP',$wpdb->prefix. "isms_authform_otp" );


require_once(dirname(__FILE__) . '/includes/Plugin.php');
require_once(dirname(__FILE__) . '/includes/iSMSAuthFormProcess.php');
if(!class_exists('WP_List_Table')){
  require_once(dirname(__FILE__) . '/includes/WPListTable.php');
}
require_once(dirname(__FILE__) . '/includes/iSMSAuthFormTableList.php');

class wp_isms_authform extends wp_isms_authform\includes\Plugin {
    private $isms = null;
    
    public function __construct() {
        $this->name = plugin_basename(__FILE__);
        $this->pre = strtolower(__CLASS__);
        $this->version = '1.0.0.0';

         $this->actions = array(
            'plugins_loaded'        =>  false
        );

        $this->register_plugin($this->name, __FILE__, true);
    }

    public function plugins_loaded() {
        require_once(dirname(__FILE__) . '/includes/iSMSAuthForm.php');
        $this->isms = new \wp_isms_authform\includes\iSMSAuthForm();    

    }
}

function isms_authform_activate() {
    global $wpdb;
    $wpdb->query('CREATE TABLE `'.ISMS_AUTHFORM_SENT.'` (
              `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
			         `form_id` int(11) NOT NULL,
              `mobile` varchar(255) NOT NULL,
              `name` varchar(255) NOT NULL,
              `email` varchar(255) NOT NULL,
              `subject` varchar(255) NOT NULL,
              `message` longtext NOT NULL,
              `date` datetime NOT NULL,
              PRIMARY KEY (`id`)
              ) ENGINE=InnoDB DEFAULT CHARSET=latin1'
    );

    $wpdb->query('CREATE TABLE `'.ISMS_AUTHFORM_FORM.'` (
              `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
              `title` varchar(255) NOT NULL,
              `form_data` text NOT NULL,
              `shortcode` varchar(255) DEFAULT NULL,
              `author` varchar(255) NOT NULL,
              `date` datetime NOT NULL,
              PRIMARY KEY (`id`)
              ) ENGINE=InnoDB DEFAULT CHARSET=latin1'
    );

    $wpdb->query('CREATE TABLE `'.ISMS_AUTHFORM_FORM_META.'` (
              `id` int(11) UNSIGNED NOT NULL  AUTO_INCREMENT,
              `form_id` int(11) NOT NULL,
              `mail_to` varchar(255) NOT NULL,
              `mail_from` varchar(255) NOT NULL,
              `mail_subject` text NOT NULL,
              `mail_additional_header` varchar(255) NOT NULL,
              `mail_body` text NOT NULL,
              `fields` text NOT NULL,
              `html_format` int(11) NOT NULL,
              PRIMARY KEY (`id`)
              ) ENGINE=InnoDB DEFAULT CHARSET=latin1'
    );
	$wpdb->query('CREATE TABLE `'.ISMS_AUTHFORM_FORM_FIELDS.'` (
			  `id` int(11)  UNSIGNED NOT NULL  AUTO_INCREMENT,
			  `form_id` int(11) DEFAULT NULL,
			  `field_type` varchar(255) NOT NULL,
			  `field_name` varchar(255) NOT NULL,
			  `field_min` int(11) DEFAULT NULL,
			  `field_max` int(11) DEFAULT NULL,
			  `field_accept` varchar(255) DEFAULT NULL,
			  `field_limit` int(11) DEFAULT NULL,
			  `is_required` tinyint(1) NOT NULL,
			  PRIMARY KEY (`id`)
			  ) ENGINE=InnoDB DEFAULT CHARSET=latin1'
    );
	
    $wpdb->query('CREATE TABLE `'.ISMS_AUTHFORM_FORM_MESSAGE.'` (
              `id` int(11) UNSIGNED NOT NULL  AUTO_INCREMENT,
              `form_id` int(11) DEFAULT NULL,
              `mobile_invalid` varchar(255) NOT NULL,
              `sent_successfully` varchar(255) NOT NULL,
              `failed_to_send` varchar(255) NOT NULL,
              `referred_to_as_spam` varchar(255) NOT NULL,
              `upload_error` varchar(255) NOT NULL,
              `upload_file_type_error` varchar(255) NOT NULL,
              `upload_file_too_large` varchar(255)  NOT NULL,
              `upload_php_error` varchar(255) NOT NULL,
              `tel_invalid` varchar(255) NOT NULL,
              PRIMARY KEY (`id`)
              ) ENGINE=InnoDB DEFAULT CHARSET=latin1'
    );
     $wpdb->query('CREATE TABLE `'.ISMS_AUTHFORM_OTP.'`(
      `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
      `code` varchar(255) NOT NULL,
      `mobile` varchar(255) NOT NULL,
      `is_expired` int(11) NOT NULL,
      `timestamp` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`)) 
      ENGINE=InnoDB DEFAULT CHARSET=latin1'
    );
}

register_activation_hook(__FILE__,'isms_authform_activate');

$GLOBALS['ISMS_AUTHFORM'] = new wp_isms_authform();

?>