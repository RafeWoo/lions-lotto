function myClick(number) {
		
	//test whether can still buy selected number
	//if can lock number
	url = rest_url + "/lock_number?number="+ number;
	
	fetch(
		url,
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
		
		if( result.locked == number ) {		
			window.location.href = "buy-number?number="+number;
		} else {			
			location.reload();
		}
	});
	
}


