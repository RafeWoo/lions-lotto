<?php 


add_shortcode( 'lionslotto-admin-display', 'lionslotto_display_admin_info');
add_shortcode( 'lionslotto-admin-tickets', 'lionslotto_display_tickets');
add_shortcode( 'lionslotto-admin-payments', 'lionslotto_display_payments');
add_shortcode( 'lionslotto-admin-assign-ticket', 'lionslotto_display_assign_ticket_form');


function lionslotto_display_admin_info()
{
	if ( current_user_can('edit_lotto') ) { 
		lionslotto_display_logged_in_admin_info();
	}
	else {
		lionslotto_display_logged_out_admin_info();
	}
}

function lionslotto_display_logged_in_admin_info()
{
	?>
	<h2>admin logged in display</h2>
	<h4>TODO generate result button</h4>
	<p>
	Not as much fun as drawing manually but easier.
	<button disabled="true">Generate a result</button>
	<p>
	<h4>TODO upload a result form</h4>
	<p>Result consists of Month, 1st Winning number , 2nd Winning number, 3rd Winning Number</p>	
	<h4>TODO Broadcast result form</h4>
	<p>Mail subscribers with this months result</p>
	<p><button disabled="true">Broadcast results</button></p>
	<h4>TODO Mail Winners form</h4>
	<p>Let winners know they have won</p>
	<p><button disabled="true">Mail Winners</button></p>	
	<h4><a href="lotto-view-tickets">View Tickets Data</a></h4>
	<h4><a href="lotto-payments">Download Payments Data</a></h4>
	<p>TODO What data do we want and in what format?</p>	
	<h4><a href="lotto-assign-number">Assign Ticket Manually</a></h4>
	<h4><a href="../lotto-results">View Results</a></h4>
	<?php
}

function lionslotto_display_logged_out_admin_info()
{
	?>
	<h2>user logged out display</h2>
	<?php
}



function lionslotto_display_tickets()
{
	if ( current_user_can('edit_lotto') ) { 
	?>
	<h2>TODO tickets info</h2>
	<p> NUmber of tickets sold</p>
	<p>Table of Ticket number - date sold- user name - manual assignment?</p>
	<?php
	
	}
}
function lionslotto_display_payments()
{
	if ( current_user_can('edit_lotto') ) { 
	?>
	<h2>Display payments data</h2>
	<p> NUmber of tickets sold</p>
	<p> Form to filter which years payments</p>
	<p>Button to download payments</p>
	<?php
	
	}
}
function lionslotto_display_assign_ticket_form()
{
	if ( current_user_can('edit_lotto') ) { 
	
		$wp_nonce = wp_create_nonce( 'wp_rest' );
		$rest_url = get_site_url()."/wp-json/"."lionslotto/v1/assign-ticket"; 	
		
	?>
	
	<script type="application/javascript">
	function validateForm() {
				
		document.getElementById("name-error").innerHTML ="";
		document.getElementById("email-error").innerHTML ="";
					
		
		let formdata = document.forms["assignment-form"];
			
		let form_is_valid = true;
			
		if (formdata["name"].value == "") {
						
			document.getElementById("name-error").innerHTML ="!Name must be filled out!";
			form_is_valid = false;
		}
		
		
		const email_string = formdata["email"].value;
		if( email_string != "") {
		
			const email_lower = String(email_string).toLowerCase();
			const regex = /^(([^<>()[\]\\.,;:\s@"]+(\.[^<>()[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
			 
			if( !regex.test( email_lower ) )
			{
				document.getElementById("email-error").innerHTML = "!invalid email!";
				form_is_valid = false;
			}		
		}			
		
		if( form_is_valid )
		{
			//1 of email, phone , address should be set
			const addr_string = formdata["address1"].value;
			const phone_string = formdata["phone"].value;
			
			if( email_string == "" &&
				addr_string == "" && 
				phone_string == "" )
				{
					document.getElementById("email-error").innerHTML = "Some contact info needed!";
					form_is_valid = false;
				}
			
		}
			
						
		if( form_is_valid )
		{
			let result_element = document.getElementById("result");
			//result_element.innerHTML = "form valid";
			
			
			const url = <?php echo "\"$rest_url\""; ?>;					
			const nonce = <?php	echo "\"$wp_nonce\""; ?>;							
			
					
			//result_element.innerHTML = "sent data";
			
			fetch(	
				url, 
				{
					method: 'POST',
					body: new URLSearchParams(new FormData(formdata)), 
					headers: {
						'X-WP-Nonce': nonce			
					},
				}
			).then(
				(resp) => {
					return resp.json(); // or resp.text() or whatever the server sends
				}
			).then(
				(body) => {
					
					if( body.success )
					{						
						document.getElementById("submit").disabled = true;
						//result_element.innerHTML = "success " 
						//location.reload();
						//const redirect_url = <?php echo "\"".site_url()."/options\"";?>;
						//result_element.innerHTML = redirect_url;
						//window.location.href = redirect_url;  
						let successString = "Success - ";
						successString += formdata["name"].value;
						successString += " has been allocated ticket ";
						successString += body.ticket;
						
						result_element.innerHTML = successString;
					}
					else{									
						result_element.innerHTML = body.error;
					}
				}
			).catch(
				(error) => {					
					result_element.innerHTML = error.message;								
				}
			);
		
		}
				
		return false;
	}
	</script>
	<h2>Manual Assign Form</h2>
	
	<form name="assignment-form" onsubmit="return validateForm();" class="assignment-form" >
		<label for="name">Name:</label><br>	
		<input type="text" id="name" name="name" value=""><br><p id="name-error"></p>
		
		<label for="email">E-mail:</label><br>
		<input type="email" id="email" name="email" value="" /><br><p id="email-error"></p>
		
		<label for="address1">Address1:</label><br>
		<input type="text" id="address1" name="address1" value="" /><br>
		
		<label for="address2">Address2:</label><br>
		<input type="text" id="address2" name="address2" value="" /><br>
		
		<label for="address3">Address3:</label><br>
		<input type="text" id="address3" name="address3" value="" /><br>
		
		<label for="postcode">PostCode:</label><br>
		<input type="text" id="postcode" name="postcode" value="" /><br>
		
		<label for="phone">Phone:</label><br>
		<input type="tel" id="phone" name="phone" value="" /><br>
		
		<br>
		<input type="submit" id="submit" value="Submit">	
	</form>
	<p id="result"></p>
	<p><button onClick="window.location.reload();">Reset</button></p>	
	
	<?php
	
	}
}

