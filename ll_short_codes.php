<?php 
add_shortcode( 'lionslotto_grid', 'display_200_grid' );
add_shortcode( 'lionslotto_purchase', 'display_200_purchase_form' );
add_shortcode( 'lionslotto_success', 'lionslotto_handle_success');
add_shortcode( 'lionslotto_cancel', 'lionslotto_handle_cancel');

//queries database for all unused numbers
function get_available_numbers()
{
	global $wpdb;
	
	$results = $wpdb->get_results(
		"        
		SELECT ID, display_value 
        FROM wp_lionslotto_numbers
        WHERE state = 'UNUSED'       
		"
	);
	
	$numbers = array();
	
	foreach ( $results as $result ) {	
		$numbers[] = $result->display_value;		
	}	
	return $numbers;
}


function display_200_grid() {
		
	$rest_url = get_site_url()."/wp-json/"."lionslotto/v1"; 
	$wp_nonce = wp_create_nonce( 'wp_rest' );
	
	// can we load java script file in better way? - wp add inline_script
	echo "<script type=\"application/javascript\" >";
	echo "var rest_url = \"$rest_url\";";
	echo "var nonce = \"$wp_nonce\";";
	include dirname(__FILE__).'/lock_ticket.js';
	echo "</script>";
		
	echo "<h3>Click on the number you would like to buy</h3>";	
	echo "<div class=\"grid_container\">";	
	$available_numbers = get_available_numbers();
	
	for( $row = 0; $row < 20; $row++)
	{
		for( $col = 0; $col < 10; $col++)
		{
			echo "<div class=\"grid_column\">";		
			$number = $row*10+$col+1;
			if ( in_array($number, $available_numbers)) {
				echo "<button class=\"grid_button\" onclick=\"myClick($number)\">";			
			}
			else{			
				echo "<button disabled class=\"grid_button_disabled\">";
			}
			echo $number;	
			echo "</button>";			
			echo "</div>";
		}		
	}
	echo "</div>";	
}

///////////////////////////////////////////////////////////////////////
function display_200_purchase_form() {
			
	if( isset( $_GET['number'] ) ) {	
						     
		$buying = $_GET['number'];
				
		$stripe_public_key = 'pk_test_TYooMQauvdEDq54NiTphI7jx';
		$options = get_option( 'lionslotto_settings_fields' );
		if( $options )
		{
			$stripe_public_key = $options['stripe_public_key'];	
		}
		
		$rest_url = get_site_url()."/wp-json/"."lionslotto/v1";
		$wp_nonce = wp_create_nonce( 'wp_rest' );		

		echo "<script src=\"https://js.stripe.com/v3/\"></script>";  //load the stripe api		
		echo "<p>You are buying number ".$buying.".</p>";
		echo "<p>Click on the button to continue to the Stripe payments page</p>";
		echo "<button type=\"button\" id=\"checkout-button\">Checkout</button>";		
		echo "<script type=\"application/javascript\" >";
		echo "var rest_url = \"$rest_url\";";
		echo "var nonce = \"$wp_nonce\";";
		echo "var purchase_data={ ticket_id:\"$buying\"};";
		echo "var stripe_public_key=\"$stripe_public_key\";";
		include dirname(__FILE__).'/checkout.js';
		echo "</script>";
	}
	else
	{
		echo "<h3>An error has occured</h3>";
		echo "<a href=\"members-only\" >Return to grid page</a>";
	}
}

//////////////////////////////////////////////////////////////////////
function lionslotto_handle_success(){
	
	$user_id = get_current_user_id();
	$ticket_id = $_GET['ticket_id'];
	$token = $_GET['token'];
	
	$rest_url = get_site_url()."/wp-json/"."lionslotto/v1"; 
	$wp_nonce = wp_create_nonce( 'wp_rest' );
//	if( isset( $token) && isset($ticket_id) )
	{
		
	?>			
			<p id="message" />
			<script type="application/javascript" >			
	<?php
			echo "var rest_url = \"$rest_url\";";
			echo "var nonce = \"$wp_nonce\";";
			echo "var purchase_data = { ticket_id:\"$ticket_id\", token:\"$token\"};";
	?>
				 fetch(
					rest_url + "/complete_purchase", 
					{
						method: 'POST',	
				
						headers: {
							'Content-Type': 'application/json',
							'X-WP-Nonce': nonce,							
						},
						body: JSON.stringify(purchase_data) 	  
					}
				)
				.then( function(response) {						
					return response.json();
				})
				.then(function(result) {
	
					if( result.success ){
						document.getElementById("message").innerHTML = "purchase complete!";
					}
					else{
						document.getElementById("message").innerHTML = "something went wrong! " + result.session_id;
					}
				
				})
				.catch( function(error) {
					//document.getElementById("message").innerHTML = "Hmm something went wrong";
					// console.error('Error:', error);
				});
			</script>
	<?php
			
		
	}	
}
////////////////////////////////////////////////////////////////////////
function lionslotto_handle_cancel()
{	
	$rest_url = get_site_url()."/wp-json/"."lionslotto/v1"; 	
	$wp_nonce = wp_create_nonce( 'wp_rest' );
	?>		
		
			<script type="application/javascript" >
	<?php
			echo "var rest_url = \"$rest_url\";";
			echo "var nonce = \"$wp_nonce\";";			
	?>
				 fetch(
					rest_url + "/cancel_purchase",				
					{
						method: 'POST',					
						headers: {						
							'X-WP-Nonce': nonce,					
						},
						 	 
					}
				)
				.then( function(response) {	
					//document.getElementById("message2").innerHTML = "got here " + response.status;
					return response.json();
				})
				.then(function(result) {
	
					if( result.success ){
						//document.getElementById("message").innerHTML = "cancel complete!";
					}
					else{
						//document.getElementById("message").innerHTML = "something went wrong!";
					}
				
				})
				.catch( function(error) {
					//document.getElementById("message").innerHTML = "Hmm something went wrong";					
				});
			</script>
	<?php		
}