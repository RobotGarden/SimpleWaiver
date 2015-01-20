$(document).ready(function() { 
	$('#simpleWaiverForm').submit(function(){
		sw_pack_custom_fields();

		jQuery('#simpleWaiverForm').ajaxSubmit({
			url: WaiverAjax.ajaxurl,
			type: "post",
			data:{
				action: 'waiver_submitted'
			},
			success: showResponse,
			error: show_error
		});
		
		jQuery("#simpleWaiverForm").slideUp();
		jQuery(".simpleWrapperSubmitting").slideDown();
		
		return false; 
	});

	jQuery("[name='swMailChimpSignup']").click(function(){jQuery(".simpleWaiverMailChimpGroup").slideToggle()});
	jQuery(".simpleWaiverMailChimpGroupSelection").click(sw_pack_groupings_selections);
			
	
	$('.reset_form').click(function(){
		reset_form();
	});
});

function sw_pack_groupings_selections(){
	var groups = {};

	jQuery(".simpleWaiverMailChimpGroupSelection:checked").each(function(){
		var parentName = jQuery(this).parent().find("[name=id]").val();
		if(!groups[parentName]){
			groups[parentName] = {};
			groups[parentName]["groups"] = [];
			groups[parentName]["form_field"] = jQuery(this).parent().find("[name=form_field]").val();
		}
		groups[parentName]["groups"].push(jQuery(this).attr("name"));
	})

	jQuery("[name='simple_waiver_settings_global[mailchimp_list_groups]'").val(JSON.stringify(groups));

	jQuery("[name='swMailChimpSelectedGroup'").val(JSON.stringify(groups));
}

function prime_reset_form(){
	jQuery(".simpleWrapperSubmitting").slideUp()
	jQuery(".simpleWrapperSubmitted").slideDown()
	setTimeout(reset_form,10000)
}

function show_error(){
	jQuery(".simpleWrapperSubmitting").slideUp()
	jQuery(".simpleWrapperError").slideDown()
}

function reset_form(){
	jQuery(".simpleWrapperSubmitted").slideUp()
	
	if(SimpleWaiverOptions.mailchimp_default_checked == "true" && !jQuery("[name='swMailChimpSignup']").prop('checked')){
		jQuery("[name='swMailChimpSignup']").prop('checked', true);
		jQuery(".simpleWaiverMailChimpGroup").show();
	}
	
	jQuery("#simpleWaiverForm")[0].reset()
	jQuery("#simpleWaiverForm").slideDown()
}

function sw_pack_custom_fields(){
	var customFields = {};
	
	for(key in JSON.parse(SimpleWaiverOptions.custom_fields)){
		var value = jQuery("[name="+key+"]").val();
		customFields[key] = value;
	}
	
	jQuery("[name=swCustomFields]").val(JSON.stringify(customFields));
}

function sw_check_date(date){
	if(SimpleWaiverOptions.require_guardian === 'false')
		return;
	
	if(sw_getAge(date.value)<18){
		$(".simpleWaiverGuardian").slideDown();
		$("[name=swGuardianName]").prop("required", true);
		$("[name=swGuardianEmail]").prop("required", true);
		$("[name=swGuardianSignature]").prop("required", true);
	}else{
		$(".simpleWaiverGuardian").slideUp();
		$("[name=swGuardianName]").prop("required", false);
		$("[name=swGuardianEmail]").prop("required", false);
		$("[name=swGuardianSignature]").prop("required", false);
	}
}

function sw_getAge(dateString) {
    var today = new Date();
    var birthDate = new Date(dateString);
    var age = today.getFullYear() - birthDate.getFullYear();
    var m = today.getMonth() - birthDate.getMonth();
    if (m < 0 || (m === 0 && today.getDate() < birthDate.getDate())){age--;}
    return age;
}
 
// post-submit callback 
function showResponse(responseText, statusText, xhr, $form){
	console.log("Response:")
    console.log(responseText);

	try{
	    if(jsonresponse = JSON.parse(responseText)){
	    	jQuery("[name=swNonce]").val(jsonresponse["nonce"]);
	    }
	    
		prime_reset_form();
	}catch(e){
		show_error();
	}
}