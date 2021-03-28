// Create an instance of the Stripe object with your publishable API key
var stripe = Stripe(stripe_public_key);
var checkoutButton = document.getElementById('checkout-button');

var checkoutUrl = rest_url + "/create-checkout-session";
	  	  
checkoutButton.addEventListener('click', function() {
	
	
  // Create a new Checkout Session using the server-side endpoint  
  fetch(
		checkoutUrl, 
		{
			method: 'POST',				
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': nonce				
			},
			body: JSON.stringify(purchase_data) 	  
		}
	)
	.then( function(response) {	
		return response.json();
	})
	.then(function(session) {
			if( session.success )
			{				
				return stripe.redirectToCheckout({ sessionId: session.id });
			}
			else
			{
				alert(session.error);
				return session;
			}
	})
	.then(function(result) {
			
		// If `redirectToCheckout` fails due to a browser or network
		// error, you should display the localized error message to your
		// customer using `error.message`.
		if (result.error) {
			alert(result.error.message);
		}
	})
	.catch( function(error) {	
		// console.error('Error:', error);
	});
  
});
