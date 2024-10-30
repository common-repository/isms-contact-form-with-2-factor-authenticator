window.addEventListener('load', function () {
  	function validateForm() {
        var isValid = true;
        var formselector = jQuery('#isms-auth-form-selector').val();
        jQuery(formselector+' input').each(function() {
            if(jQuery(this).attr('required')){
                if(jQuery(this).val() === ''){
                    isValid = false;
                }
            }
        });
        return isValid;
    }
	
	
    jQuery(document).on('click', '.isms-authform ul#country-listbox li', function() {
        jQuery('.isms-authform #isms-authform-country-code').val(jQuery(this).attr('data-dial-code'));
        jQuery('.isms-authform input[name="isms_authform_mobilefield_hidden"]').val('+'+jQuery(this).attr('data-dial-code')+jQuery('#isms_authform_mobilefield').val());
                
    });


     if(jQuery('.isms-authform #isms_authform_mobilefield').length) {
  		var input = document.querySelector("#isms_authform_mobilefield");
  		
        window.intlTelInput(input, {
            //allowDropdown: false,
            // autoHideDialCode: false,
            //autoPlaceholder: "off",
            // dropdownContainer: document.body,
            // excludeCountries: ["us"],
            // formatOnDisplay: false,
            //geoIpLookup: function (callback) {
            //   jQuery.get("http://ipinfo.io", function () {
            //   }, "jsonp").always(function (resp) {
            //       var countryCode = (resp && resp.country) ? resp.country : "";
            //      callback(countryCode);
            //   });
            // },
            hiddenInput: "isms_authform_mobilefield_hidden",

            // initialCountry: "auto",
            // localizedCountries: { 'de': 'Deutschland' },
            // nationalMode: false,
            // onlyCountries: ['us', 'gb', 'ch', 'ca', 'do'],
            placeholderNumberType: "MOBILE",
            preferredCountries: ['my', 'jp'],
            separateDialCode: true,
            utilsScript: ismsAuthFormScript.pluginsUrl + "../assets/prefix/js/utils.js?1581331045115",
        });

        jQuery(".isms-authform #isms_authform_mobilefield").keyup(function () {
            jQuery(this).val(jQuery(this).val().replace(/^0+/, ''));
            jQuery('.isms-authform input[name="isms_authform_mobilefield_hidden"]').val('+'+jQuery('.isms-authform #isms-authform-country-code').val()+jQuery(this).val().replace(/^0+/, ''));

        });


       /* jQuery('#'+formID+' input[type="submit"]').click(function(event){
			var original_val = jQuery(this).val();
			
			
        	if(document.querySelector('form#'+formID).checkValidity()){
				
                if(validateForm()) {
                   	event.preventDefault();
					if(original_val == "Submit") {
						jQuery(this).val("Submitting...");
					}else if(original_val == "Send") {
						jQuery(this).val("Submitting...");
					}
					jQuery('.isms-response-holder').removeClass('isms-bg-success');
					jQuery('.isms-response-holder').removeClass('isms-bg-danger');
                    jQuery('.isms-response-holder').fadeOut('slow');	
                  	var myform = document.getElementById(formID);
   					var fd = new FormData(myform );
                   
                    jQuery.ajax({
                    	url: isms_authform_public_ajax.ajaxurl,
                       	data: fd,

				        processData: false,
				        contentType: false,

				        type: 'POST',
                            success:function(data) {
								console.log(original_val);
								jQuery('#'+formID+' input[type="submit"]').val(original_val);
                            	console.log(data);
                                if(parseFloat(data.status)) {
                                    jQuery('.isms-response-holder').addClass('isms-bg-success');
                                    jQuery('.isms-response-holder').html(data.message);
                                    jQuery('.isms-response-holder').fadeIn('slow');
                                }else{
                                    jQuery('.isms-response-holder').removeClass('isms-bg-success');
                                    jQuery('.isms-response-holder').addClass('isms-bg-danger');
                                    jQuery('.isms-response-holder').html(data.message);
                                    jQuery('.isms-response-holder').fadeIn('slow');
                                   
                                }

                            },
                            error: function(errorThrown){
                                console.log(errorThrown);
                             }
                        });
                    }
                }
        });	*/
		 var formID = jQuery(".isms-authform").attr('id');
		 var submit_btn = jQuery('#'+formID+' input[type="submit"]');
         var send_interval = jQuery('#isms-auth-send-interval').val();
         var submit_original = jQuery(submit_btn).val();
         var sec_countdown = send_interval * 60000;
		
        console.log(submit_original);
        

		jQuery('#'+formID+' input[type="submit"]').click(function (event) {
                jQuery('.isms-auth-response-holder').removeClass('isms-bg-danger');
                jQuery('.isms-auth-response-holder').html('');
                jQuery('.isms-auth-response-holder').fadeOut('slow');
                if(jQuery('#isms-otp-validated').val() == 'false'){
				
                    if(document.querySelector('form#'+formID).checkValidity()){
                        if(validateForm()) {
                            event.preventDefault();
                            jQuery(submit_btn).val("Sending OTP code...");
                            
                            var mobile_phone = jQuery('#isms_authform_mobilefield').val();

                            if(parseFloat(mobile_phone)){
                                jQuery.ajax({
                                    type : 'POST',
                                    url: isms_authform_public_ajax.ajaxurl,
                                    dataType: 'json',
                                    data : {
                                        action      : 'authform_generate_otp_code',
                                        dst         :  mobile_phone,
                                        countrycode : jQuery('#isms-authform-country-code').val()

                                    },
                                    success:function(data) {
										console.log(data);
                                        if(data) {
                                            jQuery('.isms-auth-response-holder').removeClass('isms-bg-danger');
                                            jQuery('.isms-auth-response-holder').html('');
                                            jQuery('.isms-auth-response-holder').fadeOut('slow');
                                            jQuery(submit_btn).val(submit_original);
                                            jQuery(submit_btn).attr('disabled','disabled');
                                            jQuery('#isms-otp-tr-holder').fadeIn('slow');
                                            jQuery('#isms-otp-button-holder').fadeIn('slow');
                                            jQuery('#isms_reg_otp').attr('required','required');
                                            jQuery('#isms-resend-otp').prop('disabled', true);

                                            setTimeout(countDown,1000);
                                        }else{
											jQuery(submit_btn).val(submit_original);
                                            jQuery('.isms-auth-response-holder').addClass('isms-bg-danger');
                                            jQuery('.isms-auth-response-holder').html('Failed to send OTP code. Please check you mobile number.');
                                            jQuery('.isms-auth-response-holder').fadeIn('slow');
                                        }

                                    },
                                    error: function(errorThrown){
                                        console.log(errorThrown);
                                    }
                                });
                            }else {
                                jQuery('.isms-auth-response-holder').addClass('isms-bg-danger');
                                jQuery('.isms-auth-response-holder').html('Invalid mobile number!');
                                jQuery('.isms-auth-response-holder').fadeIn('slow');
                            }

                        }
                    }
                }
            });

			 jQuery('.isms-authform #isms-resend-otp').click(function (event) {
                event.preventDefault();
                var send_interval = jQuery('#isms-auth-send-interval').val();
                jQuery(this).val("Re-sending OTP code...");
                var mobile_phone = jQuery('#isms_authform_mobilefield').val();
               
                if(parseFloat(mobile_phone)){
                jQuery.ajax({
                    type : 'POST',
                    url: isms_authform_public_ajax.ajaxurl,
                    dataType: 'json',
                    data : {
                        action      : 'authform_generate_otp_code',
                        dst         : mobile_phone,
                        countrycode : jQuery('#isms-authform-country-code').val()

                    },
                    success:function(data) {
                        jQuery('.isms-auth-response-holder').removeClass('.isms-bg-danger');
                        jQuery('.isms-auth-response-holder').html('');
                        jQuery('.isms-auth-response-holder').fadeOut('slow');  
                        if(data) {
                            jQuery('#isms-resend-otp').val("Resend OTP");
                            jQuery('#isms-resend-otp').prop('disabled', true);
                            sec_countdown = send_interval * 60000;
                           setTimeout(countDown,1000);
                        }else {

                        }
                    },
                    error: function(errorThrown){
                        console.log(errorThrown);

                    }
                });
                }else {
                    jQuery('.isms-auth-response-holder').addClass('isms-bg-danger');
                    jQuery('.isms-auth-response-holder').html('Invalid mobile number!');
                    jQuery('.isms-auth-response-holder').fadeIn('slow');
                }

            });
		 
		 jQuery('.isms-authform #isms-verify-otp').click(function (event) {
                event.preventDefault();

                jQuery(this).val("Verifying...");
                var mobile_phone = jQuery('#isms_authform_mobilefield').val();
			 
                if(parseFloat(mobile_phone)){
                    jQuery.ajax({
                        type : 'POST',
                        url: isms_authform_public_ajax.ajaxurl,
                        dataType: 'json',
                        data : {
                            action      : 'authform_verify_otp',
                            otp_code    : jQuery('#isms_reg_otp').val(),
                            dst         : mobile_phone,
                            countrycode : jQuery('#isms-authform-country-code').val()

                        },
                        success:function(data) {
							
                            jQuery('#isms-verify-otp').val("Verify OTP");

                            if(data) {
                                jQuery('.isms-auth-response-holder').removeClass('.isms-bg-danger');
                                jQuery('.isms-auth-response-holder').html('');
                                jQuery('.isms-auth-response-holder').fadeOut('slow');  
                                jQuery('#isms-otp-tr-holder').fadeOut('slow');
                                jQuery('#isms-otp-button-holder').fadeOut('slow');
                                jQuery(submit_btn).removeAttr('disabled');
                                jQuery('#isms-otp-validated').val('true');
                                    localStorage.setItem("mobilephone", jQuery('isms_authform_mobilefield_hidden').val());
								submit_form();
                                
                            }else {
                                jQuery('.isms-auth-response-holder').removeClass('isms-bg-success');
                                jQuery('.isms-auth-response-holder').addClass('isms-bg-danger');
                                jQuery('.isms-auth-response-holder').html('Invalid OTP code!');
                                jQuery('.isms-auth-response-holder').fadeIn('slow');
                            }
                        },
                        error: function(errorThrown){
                            console.log(errorThrown);

                        }
                    });
                }else {
                    jQuery('.isms-auth-response-holder').addClass('isms-bg-danger');
                    jQuery('.isms-auth-response-holder').html('Invalid mobile number!');
                    jQuery('.isms-auth-response-holder').fadeIn('slow');
                }

            });

			function submit_form() {
				if(submit_original == "Submit") {
					jQuery(submit_btn).val("Submitting...");
				}else if(submit_original == "Send") {
					jQuery(submit_btn).val("Submitting...");
				}
								jQuery('.isms-response-holder').removeClass('isms-bg-success');
								jQuery('.isms-response-holder').removeClass('isms-bg-danger');
								jQuery('.isms-response-holder').fadeOut('slow');	
                                var myform = document.getElementById(formID);
								var fd = new FormData(myform );

								jQuery.ajax({
									url: isms_authform_public_ajax.ajaxurl,
									data: fd,

									processData: false,
									contentType: false,

									type: 'POST',
										success:function(data) {
											
											jQuery('#'+formID+' input[type="submit"]').val(submit_original);
											console.log(data);
											if(parseFloat(data.status)) {
												jQuery('.isms-response-holder').addClass('isms-bg-success');
												jQuery('.isms-response-holder').html(data.message);
												jQuery('.isms-response-holder').fadeIn('slow');
											}else{
												jQuery('.isms-response-holder').removeClass('isms-bg-success');
												jQuery('.isms-response-holder').addClass('isms-bg-danger');
												jQuery('.isms-response-holder').html(data.message);
												jQuery('.isms-response-holder').fadeIn('slow');

											}

										},
										error: function(errorThrown){
											console.log(errorThrown);
										 }
									}); 
			}

			function countDown(){
               sec_countdown = sec_countdown - 1000;
                
               if(sec_countdown > 0){
                  setTimeout(countDown,1000);
                   jQuery('#isms-resend-otp').val('Resend OTP ('+millisToMinutesAndSeconds(sec_countdown)+')');
               }else {
                  jQuery('#isms-resend-otp').prop('disabled', false);
                  jQuery('#isms-resend-otp').val('Resend OTP');
               }    
             
            }
            function millisToMinutesAndSeconds(millis) {
              var minutes = Math.floor(millis / 60000);
              var seconds = ((millis % 60000) / 1000).toFixed(0);
              return minutes + ":" + (seconds < 10 ? '0' : '') + seconds;
            }

        
    }

});