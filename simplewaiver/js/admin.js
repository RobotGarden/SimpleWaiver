jQuery(document).ready(function () {
    var $wrapper = jQuery('.sw-custom-fields', this);
    jQuery(".sw-add-field").click(function (e) {
        sw_add_field($wrapper).find('input').val('').focus();
    });
    
    sw_parse_form($wrapper);
    
	jQuery(".sw_list_group_selection").click(sw_pack_groupings_selections);
});

function sw_add_field($wrapper,data) {
    var $field = sw_field_primitive().appendTo($wrapper);

    jQuery('.remove-field', $field).click(function () {
        jQuery(this).parent('.custom-field').remove();
    });
        
    jQuery('input, select', $field).change(function(){
        sw_update_form();
    });
    
    if(data){
    	jQuery.each(data, function(name, value) {
    		if(name == "required"){
    			$field.find(".custom-field-"+name).prop('checked', value == "1");	
    		}else{
				$field.find(".custom-field-"+name).val(value);
			}
	    });
    }

    return $field;
}

function sw_field_primitive() {
    return jQuery('\
    <div class="custom-field">\
    	<input type="text" class="custom-field-name" placeholder="'+simple_waiver.description+'">\
    	<input type="text" class="custom-field-placeholder" placeholder="'+simple_waiver.placeholder+'">\
    	<select class="custom-field-type"><option>text</option><option>color</option><option>date</option><option>datetime</option><option>datetime-local</option><option>email</option><option>month</option><option>number</option><option>range</option><option>search</option><option>tel</option><option>time</option><option>url</option><option>week</option></select>\
    	<input type="checkbox" class="custom-field-required" value="1"> Required field  \
    	<button type="button" class="remove-field button">'+simple_waiver.remove+'</button>\
    </div>');
}

function sw_generate_id(input){
	return sw_camelize("sw "+input.replace(/[^\w\s]/gi, ''));
}

function sw_camelize(str) {
  return str.replace(/(?:^\w|[A-Z]|\b\w)/g, function(letter, index) {
    return index == 0 ? letter.toLowerCase() : letter.toUpperCase();
  }).replace(/\s+/g, '');
}

function sw_update_form() {
	console.log("Updating");
    var data = {};

    jQuery('.custom-field').each(function(){
    	var name = jQuery(this).find(".custom-field-name").val();
    	var id = sw_generate_id(name);
        data[id] = {	"name": name,
        				"placeholder": jQuery(this).find(".custom-field-placeholder").val(),
        				"type": jQuery(this).find(".custom-field-type").val(),
        				"required": jQuery(this).find(".custom-field-required").prop("checked")?"1":"0"
        			};
    });
        
    jQuery('[name="simple_waiver_settings_global[custom_fields]"]').val(JSON.stringify(data));
}

function sw_parse_form($wrapper) {
    var data = JSON.parse(jQuery('[name="simple_waiver_settings_global[custom_fields]"]').val());
    
    jQuery.each(data, function(name, data) {
        sw_add_field($wrapper,data);
    });
}

function sw_pack_groupings_selections(){
	var groups = {};

	jQuery(".sw_list_group_selection:checked").each(function(){
		var parentName = jQuery(this).parent().find("[name=name]").val();
		if(!groups[parentName]){
			groups[parentName] = {};
			groups[parentName]["groups"] = {};
			groups[parentName]["form_field"] = jQuery(this).parent().find("[name=form_field]").val();
			groups[parentName]["id"] = jQuery(this).parent().find("[name=id]").val();
		}
		groups[parentName]["groups"][jQuery(this).val()] = jQuery(this).attr("name");
	})

	jQuery("[name='simple_waiver_settings_global[mailchimp_list_groups]'").val(JSON.stringify(groups));
}