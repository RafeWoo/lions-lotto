function get_ticket() {
	let target_url = rest_url + "/get_next_number";
	
	
	document.getElementById("gt_error").innerHTML = "get_ticket_start";
	
	fetch(
		target_url,
		{
			method: 'POST',				
			headers: {
				'X-WP-Nonce': nonce			
			}			
		}		
	)
	.then( (response) => {
		return response.json();//extract JSON from the http response
	})
	.then( (result) =>{
		document.getElementById("gt_error").innerHTML = "get_ticket_result";
		if( result.success ) {		
			window.location.href = redirect_url+"?number="+result.number;
		} else {
			document.getElementById("gt_error").innerHTML = "get_ticket_error";			
			//location.reload();
			//alert("error");
		}
	})
	.catch( function(error) {	
			document.getElementById("gt_error").innerHTML = error;
		// console.error('Error:', error);
		}
	);
	
	
}