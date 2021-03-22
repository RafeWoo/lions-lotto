<?php 

add_shortcode( 'lionslotto-user-display', 'lionslotto_display_user_info');

//add_shortcode( 'lionslotto_grid', 'display_200_grid' );
add_shortcode( 'lionslotto-purchase', 'lionslotto_purchase_form' );
add_shortcode( 'lionslotto-success', 'lionslotto_handle_success');
add_shortcode( 'lionslotto-cancel', 'lionslotto_handle_cancel');


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

/*
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
*/

///////////////////////////////////////////////////////////////////////
function lionslotto_purchase_form() {
			
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
		echo "<p>You are buying an entry into the Southam Lions 500 Club</p>";
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
	
	
	$rest_url = get_site_url()."/wp-json/"."lionslotto/v1"; 
	$wp_nonce = wp_create_nonce( 'wp_rest' );
	if( isset($ticket_id) )
	{
		
	?>			
			<p id="message" />
			<script type="application/javascript" >			
	<?php
			echo "var rest_url = \"$rest_url\";";
			echo "var nonce = \"$wp_nonce\";";
			echo "var purchase_data = { ticket_id:\"$ticket_id\"};";
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
						document.getElementById("message").innerHTML = "something went wrong! : " + result.error + " " + result.code;
					}
				
				})
				.catch( function(error) {
					document.getElementById("message").innerHTML = "Hmm something went wrong";
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
	$ticket_id = $_GET['ticket_id'];
	?>		
			<p id="ll_cancel"></p>
			<script type="application/javascript" >
	<?php
			echo "var rest_url = \"$rest_url\";";
			echo "var nonce = \"$wp_nonce\";";	
			echo "var cancel_data = { ticket_id:\"$ticket_id\"};";			
	?>
				 fetch(
					rest_url + "/cancel_purchase",				
					{
						method: 'POST',					
						headers: {
							'Content-Type': 'application/json',							
							'X-WP-Nonce': nonce,					
						},
						body: JSON.stringify(cancel_data) 						 	
					}
				)
				.then( function(response) {	
					document.getElementById("ll_cancel").innerHTML = "got here " + response.status;
					return response.json();
				})
				.then(function(result) {
	
					if( result.success ){
						document.getElementById("ll_cancel").innerHTML = "cancel complete!";
					}
					else{
						document.getElementById("ll_cancel").innerHTML = "something went wrong!";
					}
				
				})
				.catch( function(error) {
					document.getElementById("ll_cancel").innerHTML = "Hmm something went wrong";					
				});
			</script>
	<?php		
}

function lionslotto_handle_update()
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
					rest_url + "/update_purchases",				
					{
						method: 'POST',					
						headers: {
							'Content-Type': 'application/json',							
							'X-WP-Nonce': nonce,					
						}						 						
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

function lionslotto_display_logged_in_user_info()
{
	/*
	<p><a href=<?php echo $buy_url ?> class="lotto-button">Buy A Ticket</a></p>
	// can we load java script file in better way? - wp add inline_script
	*/
	$rest_url = get_site_url()."/wp-json/"."lionslotto/v1"; 	
	$wp_nonce = wp_create_nonce( 'wp_rest' );
	$buy_url = get_site_url()."/lotto-purchase"; //redirecting here
	
	?>
	<h2>user logged in display</h2>
	<?php lionslotto_display_user_ticket_info() ?>	
	
	
		
	<script type="application/javascript" >
	<?php
		echo "var rest_url = \"$rest_url\";\n";
		echo "var nonce = \"$wp_nonce\";\n";
		echo "var redirect_url = \"$buy_url\";\n";
		echo "\n";
		include dirname(__FILE__).'/get_ticket.js';
		echo "\n";
	?>
	</script>
	
	<button class="lotto-button" onclick="get_ticket()">Buy Ticket</button><p id="gt_error"></p>
	
	<?php
	lionslotto_handle_update();
}

function lionslotto_display_user_ticket_info()
{
	?>
	<p>TODO show users tickets</p>
	<?php
}
function lionslotto_display_logged_out_user_info()
{
	$lotto_url = get_site_url()."/lotto";
	$login_url = get_site_url()."/login/?redirect_to=".urlencode($lotto_url);
	?>
	<h2>user logged out display</h2>
	<p>The easiest way to take part in the Southam Lions 500 Club is to register your details with this website</p>
	<p><a href=<?php echo $login_url ?> class="lotto-button">Login or Register</a></p>
	<p>TODO INFO for buying manually</p>
	<p>Alternatively contact this lion</p>
	<?php
}

function lionslotto_display_user_info()
{
	if ( is_user_logged_in() ) { 
		lionslotto_display_logged_in_user_info();
	}
	else {
		lionslotto_display_logged_out_user_info();
	}
}





