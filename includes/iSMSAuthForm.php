<?php
namespace wp_isms_authform\includes;
defined('ABSPATH') or die( 'Access Forbidden!' );

class iSMSAuthForm {

    private $admin_authform_options;
    public $isms_authform_process;

    function __construct() {
        add_action('admin_menu', array($this,'isms_authform_hook_to_menu') );
        add_action('admin_init', array( $this, 'isms_authform_init' ) );
        add_action("admin_enqueue_scripts", array($this,"isms_authform_scripts_and_style"));
        add_action('wp_enqueue_scripts', array($this,"isms_authform_public_scripts_and_style"));

        $this->admin_authform_options = get_option( 'isms_authform_account_settings' );

        $this->isms_authform_process = new \wp_isms_authform\includes\iSMSAuthFormProcess();
		add_action( 'wp_footer',  array($this,'isms_authform_footer_script') );
		
        add_action( 'wp_ajax_get_authform_list', array($this, 'get_form_list') );
        add_action( 'wp_ajax_nopriv_get_authform_list', array($this, 'get_form_list') );

        add_action( 'wp_ajax_get_mail_sent_list', array($this, 'get_mail_sent_list') );
        add_action( 'wp_ajax_nopriv_get_mail_sent_list', array($this, 'get_mail_sent_list') );

        add_action( 'wp_ajax_add_form', array($this, 'add_form') );
        add_action( 'wp_ajax_nopriv_add_form', array($this, 'add_form') );

        add_action( 'wp_ajax_update_form', array($this, 'update_form') );
        add_action( 'wp_ajax_nopriv_update_form', array($this, 'update_form') );

        add_action( 'init', array($this,'register_shortcodes'));

        add_action( 'wp_ajax_send_email', array($this, 'send_email') );
        add_action( 'wp_ajax_nopriv_send_email', array($this, 'send_email') );

		
        $this->isms_authform_process->check_expired_otp();

        add_action( 'wp_ajax_authform_generate_otp_code', array($this, 'authform_generate_otp_code') );
        add_action( 'wp_ajax_nopriv_authform_generate_otp_code', array($this, 'authform_generate_otp_code') );

        add_action( 'wp_ajax_authform_verify_otp', array($this, 'authform_verify_otp') );
        add_action( 'wp_ajax_nopriv_authform_verify_otp', array($this, 'authform_verify_otp') );

    }
	
	function authform_verify_otp() {
        $dst = sanitize_text_field(filter_var($_POST['dst'], FILTER_SANITIZE_NUMBER_INT));
        $otp = sanitize_text_field(filter_var( $_POST['otp_code'], FILTER_SANITIZE_NUMBER_INT));
        $countrycode = sanitize_text_field($_POST['countrycode']);

        $mobile = "+".$countrycode.$dst;
        $check_otp = $this->isms_authform_process->check_otp( $mobile,$otp);
		 wp_send_json($check_otp);
        if($check_otp) {
           wp_send_json(true);
        }else {
            wp_send_json(false);
        }
    }

    function authform_generate_otp_code() {
        $dst = sanitize_text_field(filter_var($_POST['dst'], FILTER_SANITIZE_NUMBER_INT));
        $countrycode = sanitize_text_field($_POST['countrycode']);

        $mobile = $countrycode.$dst;
        $otp = rand(100000,999999);
        $save = array(
            'code' => $otp,
            'mobile' => "+".$mobile,
            'is_expired' => 0
        );

        $params = array(
            'dstno' => $mobile,
            'msg' => $this->authform_format_message($otp,$this->admin_authform_options['otp-template'])
        );
		
		 
        
        $result = $this->isms_authform_process->send_notification($params);
		
       	$response_code = explode("=",str_replace('"', "",$result['body']));
        if ($response_code[0] == 2000) {
            $save_otp = $this->isms_authform_process->save_otp($save);
            if($save_otp){
               wp_send_json(true);
            }
        }
    }


    function send_email(){
        $error = false;
        $response = array();

        //Mail settings
        $form_id        = sanitize_text_field(filter_var($_POST['form-id'], FILTER_SANITIZE_NUMBER_INT));
        $addedfields    = sanitize_text_field($_POST['added_fields']);
        $mail_to        = sanitize_text_field($_POST['mail_to']);
        $mail_from      = sanitize_email(filter_var($_POST['mail_from'], FILTER_SANITIZE_EMAIL));
        $mail_header    = sanitize_text_field($_POST['mail_header']);
        $mail_subject   = sanitize_text_field($_POST['mail_subject']);
        $mail_body      = sanitize_textarea_field($_POST['mail_body']);
        $html_format    = 'Content-Type: text/html; charset=UTF-8';
        if($_POST['html_format'] == 0) {
            $html_format = "Content-Type: text/plain; charset=UTF-8";
        }
        $attachment = "";

        //Defaults
        $name           = sanitize_text_field($_POST['fname'])." ".sanitize_text_field($_POST['lname']);
        $email          = sanitize_email(filter_var($_POST['email'], FILTER_SANITIZE_EMAIL));
        $subject        = sanitize_text_field($_POST['subject']);
        $message        = sanitize_textarea_field($_POST['message']);
        $country_code   = sanitize_text_field($_POST['isms-authform-country-code']);
        $mobilefield    = sanitize_text_field($_POST['isms_authform_mobilefield']);
        $mobile         = sanitize_text_field($_POST['isms_authform_mobilefield_hidden']);
        

        $fields         = explode(",", $addedfields);
        $fields_array   = array();
        $post_field     = array();

        $form_messages = $this->isms_authform_process->get_db_row(ISMS_AUTHFORM_FORM_MESSAGE,'form_id',$form_id);
        $form_fields = $this->isms_authform_process->get_db_data_id(ISMS_AUTHFORM_FORM_FIELDS,'form_id',$form_id);
          
        foreach($form_fields as $field){
          
            if($field->field_type == 'tel') {
                
                if($_POST[$field->field_name] != "") {
                    $postfield = sanitize_text_field( $_POST[$field->field_name] );
                    if(!preg_match("/^[0-9]{3}-[0-9]{4}-[0-9]{4}$/", $postfield)) {
                        $response['message'] =  "Error: ".$postfield;
                        $error = true;
                    }
                }
            }else if($field->field_type == 'url') {
                if($_POST[$field->field_name] != "") {
                     $postfield = sanitize_text_field( $_POST[$field->field_name] );
                    if (!filter_var($postfield, FILTER_VALIDATE_URL)) {
                        $response['message'] = $form_messages->url_invalid;
                        $error = true;
                    } 
                }
            }else if($field->field_type == 'file') {
                if (!empty($_FILES[$field->field_name]["name"])) {
                    $target_dir = wp_upload_dir();
                    if ( ! function_exists( 'wp_handle_upload' ) ) {
                        require_once( ABSPATH . 'wp-admin/includes/file.php' );
                    }
                    $upload_overrides = array(
                        'test_form' => false
                    );
                
                    $allowed = explode(",",$field->field_accept);
                    
                    $filename = $_FILES[$field->field_name]['name'];
                  
                    $ext = pathinfo($filename, PATHINFO_EXTENSION);
                    if (!in_array($ext, $allowed)) {
                        $response['message'] = $form_messages->upload_file_type_error;
                        $error = true;
                    }else {
                        if ($_FILES[$field->field_name]["size"] > $field->field_limit) {
                            $response['message'] = $form_messages->upload_file_too_large;
                            $error = true;
                        }else {

                            $movefile = wp_handle_upload($_FILES[$field->field_name], $upload_overrides );
         
                            if ( $movefile && ! isset( $movefile['error'] ) ) {
                              $attachment = $movefile;
                            } else {
                                $response['message'] = $form_messages->upload_php_error;
                                $error = true;
                            }  
                        }
                    }
                }
            }
            
        }       

        if(!is_numeric($mobilefield)) {
            $response['message'] = $form_messages->mobile_invalid;
            $error = true;
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $response['message'] = $form_messages->email_invalid;
            $error = true;
        } 

        if(!$error){
            foreach ($fields as $key => $field) {
                array_push($fields_array,'[isms_'.$field.']');
                array_push($post_field, sanitize_text_field($_POST[$field]));
            }
          

            $sent = array(
                'form_id'   => $form_id,
                'mobile'    => $mobile,
                'name'      => $name,
                'email'     => $email,
                'subject'   => $subject,
                'message'   => $message,
                'date'      =>date("Y-m-d h:i:sa")
            );
           
            $headers=array(
                $html_format,
                'From: '.$mail_from,
                $mail_header
                
                
            );
            $msubject = $this->format_mail($fields_array,$post_field,$mail_subject);
            $mbody = $this->format_mail($fields_array,$post_field,$mail_body);
            $mheaders = $this->format_mail($fields_array,$post_field,$headers);
            
            if(wp_mail( $mail_to, $msubject, $mbody, $mheaders,$attachment )){
                $save_data = $this->isms_authform_process->save_data($sent,ISMS_AUTHFORM_SENT);
                
                if($save_data) {
                    $response['status'] = $save_data;
                    $response['message'] = $form_messages->sent_successfully;
                    wp_send_json($response);
                }  
            }else {
                $response['status'] ="Failed to send";
                $response['message'] = $form_messages->failed_to_send;
            }
            
        }else {
             wp_send_json($response);
        }

        
    }

    function get_messages($id,$field) {
        return $this->isms_authform_process->get_db_row(ISMS_AUTHFORM_FORM_MESSAGE,'form_id',$id);

    }
   
    function register_shortcodes(){
        add_shortcode('isms-authform', array($this,'isms_authform_function'));
        add_shortcode('isms-authform-field', array($this,'isms_authform_field_function'));
    }

    function isms_authform_field_function($atts, $content = null){
        extract(shortcode_atts(array(
            'ty'    => null, //input type
            'fr'    => null, //required field
            'fn'    => null, //field name
            'dv'    => null, //default value
            'ph'    => null, //place holder
            'fid'   => null, //field ID
            'fc'    => null, //field Class
            'fl'    => null, //field label
            'ffa'   => null, //file accept
            'nmin'  => null, //number min
            'nmax'  => null //number max
        ), $atts));

        ob_start();
        
        $req = "";

        if($fr){
            $req = "required";
        }
        
        $createfield = '<p><input type="'.$ty.'" '.$req.' name="'.$fn.'" id="'.$fid.'" class="'.$fc.'" ';
     
        if($ty == "textarea"){

            if($ph != null && $ph == $dv) {
                $createfield ='<textarea name="'.$fn.'" '.$req.' placeholder="'.$ph.'" id="'.$fid.'" class="'.$fc.'"></textarea>'; 
            }else {
                $createfield ='<textarea name="'.$fn.'" '.$req.' id="'.$fid.'" class="'.$fc.'">'.$dv.'</textarea>'; 
            }
           
        }else{

            if($ty != 'file' && $ty != 'submit' && $ty != 'checkbox' && $ty != 'radio'){

                if($ph != null && $ph == $dv) {
                    $createfield .='placeholder="'.$ph.'" ';
                }else {
                    $createfield .='value="'.$dv.'" ';
                }
            }
            
            if($ty == 'file'){
                $createfield .='accept="'.$ffa.'" ';
            }

            if($ty == 'submit'){
                $createfield .='value="'.$fl.'" ';  
            }

            if($ty == 'number'){
                if($ph != null && $ph == $dv) {
                    $createfield .='min="'.$nmin.'" max="'.$nmax.'" ';
                }else {
                   $createfield .='min="'.$nmin.'" max="'.$nmax.'" ';
                }  
            }

            if($ty == 'checkbox' || $ty == "radio"){
                $createfield .= "><label for='".$fn."'class='checkandradiolabel'>".$fl."</label>";  

            }else {
                $createfield .= '>';
            }   
        }

        echo $createfield;
        $output_string = ob_get_contents();

        ob_end_clean();
        return $output_string;
    }

    function isms_authform_function($atts, $content = null){
        extract(shortcode_atts(array(
          'id'      => null,
          'title'   => null, 
        ), $atts));
        
        ob_start();

        $form_data = $this->isms_authform_process->get_db_row(ISMS_AUTHFORM_FORM,'id',$id);
        $form_meta_data = $this->isms_authform_process->get_db_row(ISMS_AUTHFORM_FORM_META,'form_id',$id);

        echo '<form id="isms-authform-'.$id.'" class="isms-authform" method="post" enctype="multipart/form-data"><input type="hidden" name="html_format" value="'.$form_meta_data->html_format.'"/><input type="hidden" name="form-id" value="'.$id.'"/><input type="hidden" name="action" value="send_email"><input type="hidden" name="added_fields" value="'.$form_meta_data->fields.'"><input type="hidden" name="mail_to" value="'.$form_meta_data->mail_to.'"><input type="hidden" name="mail_from" value="'.$form_meta_data->mail_from.'"><input type="hidden" name="mail_header" value="'.$form_meta_data->mail_additional_header.'"><input type="hidden" name="mail_subject" value="'.stripslashes(base64_decode($form_meta_data->mail_subject)).'"><input type="hidden" name="mail_body" value="'.stripslashes(base64_decode($form_meta_data->mail_body)).'">';

            echo do_shortcode(stripslashes(base64_decode($form_data->form_data)));
            
        echo '</form><div class="isms-response-holder isms-hidden"></div>';

        $output_string = ob_get_contents();
        ob_end_clean();

        return $output_string;
    }
	
	function isms_authform_footer_script (){
        $send_interval = $this->admin_authform_options['send-interval'];
    ?>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
				var formID = jQuery(".isms-authform").attr('id');
                $('#mobile-field-selector').closest('tr').fadeOut('slow');
                
                $('<table class="isms-authform-table-otp"><tr><td>Mobile Number: </td><td><input type="hidden" id="isms-otp-validated" name="is_otp_validated" value="<?php if(isset($_POST['is_otp_validated'])){ esc_attr_e($_POST['is_otp_validated']); }else { esc_attr_e('false'); } ?>"/><input type="hidden" id="isms-auth-send-interval" value="<?php esc_attr_e($send_interval); ?>"/><input type="hidden" id="isms-auth-form-selector" value="'+formID+'"/><input type="hidden" id="isms-authform-country-code" name="isms-authform-country-code" value="<?php if(isset($_POST['isms-authform-country-code'])){ esc_attr_e($_POST['isms-authform-country-code']); }else { esc_attr_e('60'); } ?>"/><input type="text" class="input-text" name="isms_authform_mobilefield" id="isms_authform_mobilefield" required="required" autocomplete="off" value="<?php if(isset($_POST['isms_authform_mobilefield_hidden'])){ esc_attr_e($_POST['isms_hidden_reg_mobile_phone']); } ?>"/></td></tr><tr id="isms-otp-tr-holder" style="display:none"><td>Enter Verification Code sent</td><td><input type="text" class="input-text" name="isms_reg_otp" id="isms_reg_otp"/></td></tr><tr id="isms-otp-button-holder" style="display:none"><td colspan="2"><input type="button" value="Resend OTP" id="isms-resend-otp"><input type="button" value="Verify OTP" id="isms-verify-otp"></td></tr><tr><td colspan="2"><div class="isms-auth-response-holder" style="display:none"></div></td></tr></table>').insertBefore('#'+formID+' input[type="submit"]');
                    
                    $(document).on('click', 'ul#country-listbox li', function() {
                        $('#isms-authform-country-code').val($(this).attr('data-dial-code'));
                    });
            });
        </script>

    <?php }
	
	function add_form() {
        $response = array();    
		$error = false;

		
        $title          = sanitize_text_field($_POST['title']);
        $form           = wp_kses_post($_POST['form-data']);
        $addedfields    = sanitize_text_field($_POST['addedfields']);
        $user           = wp_get_current_user();

        $mailto         = sanitize_text_field($_POST['email-to']);
        $mailfrom       = sanitize_email($_POST['email-from']);
        $mailsubject    = sanitize_text_field($_POST['email-subject']);
        $mailheaders    = sanitize_text_field($_POST['email-headers']);
        $mailbody       = sanitize_textarea_field($_POST['email-body']);
        $html_format    = 0;
        if($_POST['html-format'] != NULL) {
            $html_format    = 1;
        }
        
        
        if($title == ""){
            $title = "Untitled";
        }

        $form = array(
            'title'     => $title,
            'form_data' => base64_encode($form),
            'author'    => $user->user_login,      
            'date'      => date("Y-m-d h:i:sa")
        );

        $meta = array(
            'mail_to'       => $mailto,
            'mail_from'     => $mailfrom,
            'mail_subject'  => base64_encode($mailsubject),
            'mail_additional_header' => $mailheaders,
            'mail_body'     => base64_encode($mailbody),
            'fields'        => $addedfields,
            'html_format'   => $html_format
        );

        $messages = array (
            'mobile_invalid' => sanitize_text_field($_POST['mobile_invalid']),
            'sent_successfully' => sanitize_text_field($_POST['sent_successfully']),
            'failed_to_send' => sanitize_text_field($_POST['failed_to_send']),
            'referred_to_as_spam' => sanitize_text_field($_POST['referred_to_as_spam']),
            'upload_error' => sanitize_text_field($_POST['upload_error']),
            'upload_file_type_error' => sanitize_text_field($_POST['upload_file_type_error']),
            'upload_file_too_large' => sanitize_text_field($_POST['upload_file_too_large']),
            'upload_php_error' => sanitize_text_field($_POST['upload_php_error']),
            'tel_invalid' => sanitize_text_field($_POST['tel_invalid']),
        );
        
		if (!filter_var($mailfrom, FILTER_VALIDATE_EMAIL)) {
			 $response['message'] = "Invalid From data";
			 $response['status'] ="Error";
            	$error = true;
		}
       
        if(!$error){
			$save_data = $this->isms_authform_process->save_form($form,$meta,$messages);

			if($save_data) {
			   foreach($_POST['fields'] as $value) {
					$field_data = array (
						'form_id'       => $save_data,
						'field_type'    => sanitize_text_field($value['field_type']),
						'field_name'    => sanitize_text_field($value['field_name']),
						'field_min'     => sanitize_text_field($value['field_min']),
						'field_max'     => sanitize_text_field($value['field_max']),
						'field_accept'  => sanitize_text_field($value['field_accept']),
						'field_limit'   => sanitize_text_field($value['field_limit']),
						'is_required'   => sanitize_text_field($value['is_required']),
					);

					$this->isms_authform_process->save_data($field_data,ISMS_AUTHFORM_FORM_FIELDS);
				}
			   // wp_send_json($save_data);
				$response['status'] = $save_data;
				$response['message'] = $save_data;
				

			}else{
				$response['status'] = "Failed";
				$response['message'] = "Failed to create form";
				
			}
		}
		 wp_send_json($response);
    }


    function update_form() {
        global $wpdb;
		$error = false;
        $response = array();
		
        $title          = sanitize_text_field($_POST['title']);
        $form           = wp_kses_post($_POST['form-data']);
        $form_id        = sanitize_text_field(filter_var($_POST['form-id'], FILTER_SANITIZE_NUMBER_INT));
        $addedfields    = sanitize_text_field($_POST['addedfields']);

        $mailto         = sanitize_text_field($_POST['email-to']);
        $mailfrom       = sanitize_email($_POST['email-from']);
        $mailsubject    = sanitize_text_field($_POST['email-subject']);
        $mailheaders    = sanitize_text_field($_POST['email-headers']);
        $mailbody       = sanitize_textarea_field($_POST['email-body']);
        $html_format    = filter_var($_POST['html-format'], FILTER_SANITIZE_NUMBER_INT);
        
        if($title == ""){
            $title = "Untitled";
        }

        $form_data = array(
           'title'      => $title,
           'form_data'  =>base64_encode($form),     
           'shortcode'  => '[isms-authform id="'.$form_id.'" title="'.$title.'"]'
        );

        $meta = array(
            'mail_to'       => $mailto,
            'mail_from'     => $mailfrom,
            'mail_subject'  => base64_encode($mailsubject),
            'mail_additional_header' => $mailheaders,
            'mail_body'     => base64_encode($mailbody),
            'fields'        => $addedfields,
            'html_format'   => $html_format
        );

         $messages = array (
            'mobile_invalid' => sanitize_text_field($_POST['mobile_invalid']),
            'sent_successfully' => sanitize_text_field($_POST['sent_successfully']),
            'failed_to_send' => sanitize_text_field($_POST['failed_to_send']),
            'referred_to_as_spam' => sanitize_text_field($_POST['referred_to_as_spam']),
            'upload_error' => sanitize_text_field($_POST['upload_error']),
            'upload_file_type_error' => sanitize_text_field($_POST['upload_file_type_error']),
            'upload_file_too_large' => sanitize_text_field($_POST['upload_file_too_large']),
            'upload_php_error' => sanitize_text_field($_POST['upload_php_error']),
            'tel_invalid' => sanitize_text_field($_POST['tel_invalid']),
        );

		if (!filter_var($mailfrom, FILTER_VALIDATE_EMAIL)) {
			 $response['message'] = "Invalid From data";
			 $response['status'] ="Error";
            $error = true;
		}
       
        if(!$error){
			$update_form = $this->isms_authform_process->update_form($form_id,$form_data,$meta,$messages);

			if($update_form){
				$wpdb->delete(
					ISMS_AUTHFORM_FORM_FIELDS,
					[ 'form_id' => $form_id ],
					[ '%d' ]
				);
				foreach($_POST['fields'] as $value) {
					$field_data = array (
						'form_id'       => $form_id,
						'field_type'    => sanitize_text_field($value['field_type']),
						'field_name'    => sanitize_text_field($value['field_name']),
						'field_min'     => sanitize_text_field($value['field_min']),
						'field_max'     => sanitize_text_field($value['field_max']),
						'field_accept'  => sanitize_text_field(stripslashes($value['field_accept'])),
						'field_limit'   => sanitize_text_field($value['field_limit']),
						'is_required'   => sanitize_text_field($value['is_required']),
					);

					$this->isms_authform_process->save_data($field_data,ISMS_AUTHFORM_FORM_FIELDS);
				}
				//wp_send_json($update_form);
				$response['status'] = $update_form;
				$response['message'] = $update_form;
			}else{
			  //  wp_send_json("Faild to update.");
				$response['status'] = "Failed";
				$response['message'] = "Failed: No changes applied" ;
			}
		}
		wp_send_json($response);
    }
    
    public function isms_authform_init() {
        register_setting(
            'isms_authform_admin_settings', // Option group
            'isms_authform_account_settings', // Option name
            array( $this, 'sanitize' ) // Sanitize
        );

        add_settings_section(
            'authform_setting_section_id', // ID
            'iSMS Account Setting', // Title
            array( $this, 'print_section_info' ), // Callback
            'my-authform-setting-admin' // Page
        );

        add_settings_field(
            'sendid', // ID
            'Sender ID', // Title
            array( $this, 'sendid_callback' ), // Callback
            'my-authform-setting-admin', // Page
            'authform_setting_section_id' // Section
        );
        add_settings_field(
            'username', // ID
            'Username', // Title
            array( $this, 'username_callback' ), // Callback
            'my-authform-setting-admin', // Page
            'authform_setting_section_id' // Section
        );

        add_settings_field(
            'phone',
            'Admin Phone',
            array( $this, 'phone_callback' ),
            'my-authform-setting-admin',
            'authform_setting_section_id'
        );
        add_settings_field(
            'password',
            'Password',
            array( $this, 'password_callback' ),
            'my-authform-setting-admin',
            'authform_setting_section_id'
        );

        add_settings_field(
            'send-interval',
            'Minutes to resend OTP',
            array( $this, 'send_interval_callback' ),
           'my-authform-setting-admin',
            'authform_setting_section_id'
        );
        add_settings_field(
            'otp-template',
            'OTP Template',
            array( $this, 'otp_template_callback' ),
            'my-authform-setting-admin',
            'authform_setting_section_id'
        );
        
    }

    function isms_authform_hook_to_menu() {

        add_menu_page(
            'iSMS Contact Form with Authenticator',
            'iSMS Contact with Authenticator',
            'manage_options',
            'isms-authform-setting',
             array( $this, 'create_authform_admin_page' ),'',6
        );

         add_submenu_page(
            'isms-authform-setting',
            'iSMS Form List',
            'Forms',
            'manage_options',
            'isms-authform-list',
            array( $this, 'isms_authform_list' ),''
        );

        add_submenu_page(
            'isms-authform-setting',
            'iSMS Add new form',
            'Add new',
            'manage_options',
            'isms-authform-new',
            array( $this, 'isms_authform_new' ),''
        );

        add_submenu_page(
            'isms-authform-setting',
            'iSMS Sent Email',
            'Sent Mail',
            'manage_options',
            'isms-authform-sent',
            array( $this, 'isms_authform_sent' ),''
        );

        add_submenu_page(
            'isms-authform-setting',
            'iSMS Update form',
            '',
            'manage_options',
            'isms-authform-update',
            array( $this, 'isms_authform_update' ),''
        );
    }

      function create_authform_admin_page() { ?>
        <div class="wrap">
            <h1>iSMS Authenticator Settings</h1>
            <div class="isms-divider"></div>
            <?php
            $balance = $this->isms_authform_process->get_data('isms_balance');
            $expiration = $this->isms_authform_process->get_data('isms_expiry_date');
                    
            if($this->admin_authform_options){ ?>
                <div>
                    <h6>Your credit balance: <?php echo str_replace('"', "", $balance['body']); ?></h6>
                    <h5>valid until <?php echo str_replace('"', "", $expiration['body']); ?> </h5>

                </div>
            <?php } ?>

            <form method="post" action="options.php">
                <?php
                
                // This prints out all hidden setting fields
                settings_fields( 'isms_authform_admin_settings' );
                do_settings_sections( 'my-authform-setting-admin' );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Sanitize each setting field as needed
     *
     * @param array $input Contains all settings fields as array keys
     */
    public function sanitize( $input ) {
        $new_input = array();
        if( isset( $input['sendid'] ) )
            $new_input['sendid'] = sanitize_text_field( $input['sendid'] );

        if( isset( $input['username'] ) )
            $new_input['username'] = sanitize_text_field( $input['username'] );

        if( isset( $input['ismsauthformphone'] ) )
            $new_input['ismsauthformphone'] = sanitize_text_field( $input['ismsauthformphone'] );

        if( isset( $input['password'] ) )
            $new_input['password'] = sanitize_text_field( $input['password'] );


        if( isset( $input['form-selector'] ) )
            $new_input['form-selector'] = sanitize_text_field( $input['form-selector'] );
        if( isset( $input['submit-btn-selector'] ) )
            $new_input['submit-btn-selector'] = sanitize_text_field( $input['submit-btn-selector'] );
        if( isset( $input['create-mobile-field'] ) )
            $new_input['create-mobile-field'] = sanitize_text_field( $input['create-mobile-field'] );
        if( isset( $input['mobile-field-selector'] ) )
            $new_input['mobile-field-selector'] = sanitize_text_field( $input['mobile-field-selector'] );
        if( isset( $input['send-interval'] ) )
            $new_input['send-interval'] = sanitize_text_field( $input['send-interval'] );
        if( isset( $input['otp-template'] ) )
            $new_input['otp-template'] = sanitize_text_field( $input['otp-template'] );

        return $new_input;
    }

    /**
     * Print the Section text
     */

    public function print_section_info() {
        print 'Enter your iSMS credentials';
    }

    public function sendid_callback() {
        printf(
            '<input type="text" style="width: 210px" id="sendid" autocomplete="off" name="isms_authform_account_settings[sendid]" value="%s" required="required"/>',
            isset( $this->admin_authform_options['sendid'] ) ? esc_attr( $this->admin_authform_options['sendid']) : ''
        );
    }

    public function username_callback() {
        printf(
            '<input type="text" style="width: 210px" id="username" autocomplete="off" name="isms_authform_account_settings[username]" value="%s" required="required"/>',
            isset( $this->admin_authform_options['username'] ) ? esc_attr( $this->admin_authform_options['username']) : ''
        );
    }

    public function phone_callback() {
        printf(
            '<input type="text" style="width: 210px" id="ismsauthformphone" autocomplete="off" name="isms_authform_account_settings[ismsauthformphone]" value="%s" required="required"/>',
            isset( $this->admin_authform_options['ismsauthformphone'] ) ? esc_attr( $this->admin_authform_options['ismsauthformphone']) : ''
        );
    }

    public function password_callback() {
        printf(
            '<input type="password" style="width: 210px" id="password" autocomplete="off" name="isms_authform_account_settings[password]" value="%s" required="required"/>',
            isset( $this->admin_authform_options['password'] ) ? esc_attr( $this->admin_authform_options['password']) : ''
        );
    }

    public function send_interval_callback() { ?>
        <input type="number" style="width: 210px"  placeholder="e.g 3" min="1" id="send-interval" autocomplete="off" name="isms_authform_account_settings[send-interval]" value="<?php if(isset( $this->admin_authform_options['send-interval'] )){ echo $this->admin_authform_options['send-interval']; }else{ echo '3'; } ?>" required="required"/>
        <?php
    }

    public function OTP_template_callback() { ?>
        <textarea id="otp-template" style="width: 210px"  cols="30" rows="5" name="isms_authform_account_settings[otp-template]" ><?php if(isset( $this->admin_authform_options['otp-template'] )){ echo $this->admin_authform_options['otp-template']; }else{ echo 'Your verification code is: iSMS_FORM_OTP_CODE'; } ?></textarea>,
        <?php
    }

    function isms_authform_new () {
        $this->isms_authform_template('add-new');
    }

    function isms_authform_update () {
        $this->isms_authform_template('update-form');
    }

    function isms_authform_list () { ?>
        <div class="isms-authform">
            <div class="row isms-authform">
                <div class="col-md-12 mt-3">
                    <div class="col-md-6">
                        <h3>Contact Forms  <a href="<?php echo get_site_url();?>/wp-admin/admin.php?page=isms-authform-new" class="btn btn-primary" id="add-new-form">Add New</a></h3>
                    </div>
                    <div class="col-md-6 search-form-holder">
                        <form action="" method="post">                            
                            <input type="text" name="s" id="search" value="<?php the_search_query(); ?>" />
                            <button class="btn btn-primary">Search</button>
                        </form>  
                    </div>
                </div>
            </div> 
            <div class="isms-divider"></div>

            <div class="row">
                <div class="col-md-12">
                    <form method="post">
                        <?php 
                        $wp_list_table = new iSMSAuthFormTableList(); 
                        $wp_list_table->prepare_items();
                        $wp_list_table->display();
                        ?>
                     </form>
                </div>
            </div> 
        </div>                  
       
    <?php }

  
    function isms_authform_sent () {?>
        <div class="isms-authform">
            <div class="row isms-authform">
                <div class="col-md-12 mt-3">
                    <div class="col-md-6">
                        <h3>
                        <?php if(isset($_REQUEST['formID'])){
                            $form = $this->isms_authform_process->get_db_row(ISMS_AUTHFORM_FORM,'id',$_REQUEST['formID']);
                            echo $form->title;

                        } else {
                            echo 'Sent Mail';
                        } ?>       
                        </h3>
                    </div>
                    <div class="col-md-6 search-form-holder">
                        <form action="" method="post">                            
                            <input type="text" name="s" id="search" value="<?php the_search_query(); ?>" />
                            <button class="btn btn-primary">Search</button>
                        </form>  
                    </div>
                </div>
            </div> 
            <div class="isms-divider"></div>

            <div class="row">
                <div class="col-md-12">
                    <form method="post">
                        <?php 
                            $wp_list_table = new iSMSAuthFormTableList(); 

                            $wp_list_table->prepare_items();
                            $wp_list_table->display();
                        ?>
                    </form>
                </div>
            </div>
        </div> 

    <?php }

    function isms_authform_scripts_and_style($hook){
       
        if($hook == 'toplevel_page_isms-authform-setting' || $hook == 'isms-contact-with-authenticator_page_isms-authform-list' || $hook == 'isms-contact-with-authenticator_page_isms-authform-new' || $hook == 'isms-contact-with-authenticator_page_isms-authform-update' || $hook == 'isms-contact-with-authenticator_page_isms-authform-sent'){
           wp_enqueue_style("isms-authform-bootstrap", plugins_url('../assets/css/bootstrap.min.css', __FILE__));
            wp_enqueue_style("isms-authform-prefix", plugins_url('../assets/prefix/css/intlTelInput.css', __FILE__));
            wp_enqueue_style("isms-authform-style", plugins_url('../assets/css/ismsAuthFormstyle.css', __FILE__));

            wp_enqueue_script("isms-authform-bootstrap", plugins_url('../assets/js/bootstrap.min.js', __FILE__));
            wp_enqueue_script("isms-authform-prefix-js", plugins_url('../assets/prefix/js/intlTelInput.js', __FILE__));
            wp_enqueue_script("isms-authform-js", plugins_url('../assets/js/ismsAuthForm.js', __FILE__));
            
            wp_localize_script('isms-authform-js', 'ismsauthformajaxurl ', array("scriptismsauthform" => admin_url('admin-ajax.php')));

            wp_localize_script('isms-authform-js', 'ismsAuthFormScript', array(
                'pluginsUrl' => plugin_dir_url( __FILE__ ),
            ));
        }
    }

    function isms_authform_public_scripts_and_style($hook){
        wp_enqueue_style("isms-authform-prefix", plugins_url('../assets/prefix/css/intlTelInput.css', __FILE__));
        wp_enqueue_style("isms-authform-style", plugins_url('../assets/public/css/ismsAuthFormstyle.css', __FILE__));
        wp_enqueue_script('jquery');
        wp_enqueue_script("isms-authform-prefix-js", plugins_url('../assets/prefix/js/intlTelInput.js', __FILE__));

        wp_enqueue_script("isms-authform-js", plugins_url('../assets/public/js/ismsAuthForm.js', __FILE__));
        wp_localize_script( 'isms-authform-js', 'isms_authform_public_ajax', array( "ajaxurl" => admin_url('admin-ajax.php') ) );

        wp_localize_script('isms-authform-js', 'ismsAuthFormScript', array(
            'pluginsUrl' => plugin_dir_url( __FILE__ ),
        ));
    }

    private function format_mail($fields,$replace_with,$str) {
        return str_replace($fields,$replace_with,$str);
    }
    private function authform_format_message($otp,$message) {
        return str_replace('iSMS_FORM_OTP_CODE',$otp,$message);
    }
    
    private function isms_authform_template($file) {
        include(dirname(__FILE__) . '/'.$file.'.php');
    }
}

?>