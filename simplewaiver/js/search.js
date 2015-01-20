$(document).ready(function() { 
	$('#simpleWaiverSearch').submit(function(){
		jQuery('#simpleWaiverSearch').ajaxSubmit({
			url: WaiverAjax.ajaxurl,
			type: "post",
			data:{
				action: 'waiver_search'
			},
			success: showResponse
		});
		
		return false; 
	});
});

function showResponse(responseText, statusText, xhr, $form){
	console.log("Response:")
    console.log(responseText);

	try{
	    if(jsonresponse = JSON.parse(responseText)){
	    	clearTable();
	    	showResponses(jsonresponse);
	    }
	}catch(e){
		console.log("error");
		show_error();
	}
}

function show_error(){
	//TODO: Should probably give some response
}

function showResponses(data){
	var headers = [];
	console.log("Parsing data");
	for(var key in data){
		console.log("Row: "+key);
		var row = data[key];
		var str = "<tr>";
		for(var key in row){
			headers[key]=true;
			
			console.log("Key: "+key);
			var element = row[key];
			str += "<td>"+element+"</td>";
		}
		str += "<tr>";
		
		addRow(str);
	}
	
	var headerstr = "<tr>";
	for(var key in headers){
			headerstr += "<th>"+key+"</th>";
	}
	headerstr += "</tr>";
	
	console.log(headerstr);
	
	addHeaders(headerstr);
}

function addHeaders(data){
	console.log("Adding row: "+data);
	jQuery('#simpleWaiverSearchResult > tbody:last').prepend(data);
}

function clearTable(){
	jQuery('#simpleWaiverSearchResult > tbody:last').empty();
}

function addRow(data){
	console.log("Adding row: "+data);
	jQuery('#simpleWaiverSearchResult > tbody:last').append(data);
}