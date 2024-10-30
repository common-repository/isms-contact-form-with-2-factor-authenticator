<?php
if( !defined( 'ABSPATH') && !defined('WP_UNINSTALL_PLUGIN') )
	    exit();
global $wpdb;		

//delete_option( 'isms_contact_account_settings' );

$isms_authform_form = $wpdb->prefix. "isms_authform_form" ;
$isms_authform_form_sent = $wpdb->prefix. "isms_authform_form_sent" ;
$isms_authform_form_form_meta = $wpdb->prefix. "isms_authform_form_form_meta";
$isms_authform_form_message= $wpdb->prefix. "isms_authform_form_message";
$isms_authform_form_fields= $wpdb->prefix. "isms_authform_form_fields";
$isms_authform_otp= $wpdb->prefix. "isms_authform_otp";


$wpdb->query("DROP TABLE `".$isms_contact_form."`");
$wpdb->query("DROP TABLE `".$isms_contact_sent."`");
$wpdb->query("DROP TABLE `".$isms_contact_form_meta."`");
$wpdb->query("DROP TABLE `".$isms_contact_form_message."`");
$wpdb->query("DROP TABLE `".$isms_authform_form_fields."`");
$wpdb->query("DROP TABLE `".$isms_authform_otp."`");
