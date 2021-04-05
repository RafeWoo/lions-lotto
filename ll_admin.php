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
	global $wpdb;
	
	$tickets_sold = $wpdb->get_var(
			"        
			SELECT COUNT(*)
			FROM wp_lotto_numbers       
			WHERE (state='BOUGHT' OR state='BOUGHT_MANUALLY')
			"
		);
	
	$wp_nonce = wp_create_nonce( 'wp_rest' );
	$rest_url = get_site_url()."/wp-json/"."lionslotto/v1/set-result"; 	
	?>

	<h2>admin logged in display</h2>
	<h4><?php echo "$tickets_sold";?> Tickets Sold</h4>
	<h4>Generate result button</h4>
	<p>
	Not as much fun as drawing manually but easier.
	<button id="gen-result-button" onclick="generateResult()">Generate a result</button>
	<p>
	<h4>Upload a result</h4>
	<p>Set winning numbers for the month and click submit</p>
	<form name="lf-set-result-form" class="lf-set-result-form" onsubmit="return uploadResult();">		
		<label for="result-month">Month: </label>
		<select id="result-month" name="result-month">
			<option value="JANUARY">Jan</option>
			<option value="FEBRUARY">Feb</option>
			<option value="MARCH">Mar</option>
			<option value="APRIL">Apr</option>
			<option value="MAY">May</option>
			<option value="JUNE">Jun</option>
			<option value="JULY">Jul</option>
			<option value="AUGUST">Aug</option>
			<option value="SEPTEMBER">Sep</option>
			<option value="OCTOBER">Oct</option>
			<option value="NOVEMBER">Nov</option>
			<option value="DECEMBER">Dec</option>
		</select><br>
		<label for="result1">1st Prize: </label><input type="number" min="1" max="500" id="result1" name="result1" value="1" /><br>
		<label for="result2">2nd Prize: </label><input type="number" min="1" max="500" id="result2" name="result2" value="1" /><br>			
		<label for="result3">3rd Prize: </label><input type="number" min="1" max="500" id="result3" name="result3" value="1" /><br>
		<input type="submit" id="set-result" name="set-result" /><br>
	</form>
	<p id="upload_result_info"></p>
	<h4>TODO Broadcast result form</h4>
	<p>Mail subscribers with this months result</p>
	<p><button disabled="true">Broadcast results</button></p>
	<h4>TODO Mail Winners form</h4>
	<p>Let winners know they have won</p>
	<p><button disabled="true">Mail Winners</button></p>	
	<h4><a href="lotto-view-tickets">View Tickets Data</a></h4>
	<h4><a href="lotto-payments">Download Payments Data</a></h4>	
	<h4><a href="lotto-assign-number">Assign Ticket Manually</a></h4>
	<h4><a href="../lotto-results">View Results</a></h4>
	<script type="application/javascript">
	
		/* Randomize array in-place using Durstenfeld shuffle algorithm */
		function shuffleArray(array) {
			for (var i = array.length - 1; i > 0; i--) {
			var j = Math.floor(Math.random() * (i + 1));
			var temp = array[i];
			array[i] = array[j];
			array[j] = temp;
			}
		}
	
		function generateResult()
		{
			let ticket_count = <?php echo $tickets_sold; ?>;

			if( ticket_count > 2 )
			{
			
			var d = new Date();
			var month_n = d.getMonth();
			
			let formdata = document.forms["lf-set-result-form"];
			var month_names = new Array();
month_names[0] = "JANUARY";
month_names[1] = "FEBRUARY";
month_names[2] = "MARCH";
month_names[3] = "APRIL";
month_names[4] = "MAY";
month_names[5] = "JUNE";
month_names[6] = "JULY";
month_names[7] = "AUGUST";
month_names[8] = "SEPTEMBER";
month_names[9] = "OCTOBER";
month_names[10] = "NOVEMBER";
month_names[11] = "DECEMBER";
			formdata["result-month"].value = month_names[month_n];
			
			var ticket_array = [];
			for( i = 0; i < ticket_count; ++i)
			{
				ticket_array[i] = i;
			}
			shuffleArray(ticket_array);
			
			formdata["result1"].value = ticket_array[0] + 1;
			formdata["result2"].value = ticket_array[1] + 1;
			formdata["result3"].value = ticket_array[2] + 1;
			}
			else{
				alert("not enough tickets sold");
			}
			//let month_element = 
			//let output_element = document.getElementById("upload_result_info");
			//output_element.innerHTML = "gen clicked";
		}
		//document.getElementById("gen-result-button").addEventListener("click", generateResult);
		
		
		function uploadResult() {
			//validate no repeats
			let is_form_valid = false;
			
			let output_element = document.getElementById("upload_result_info");
			let formdata = document.forms["lf-set-result-form"];
			
			let month = formdata["result-month"].value;
			let r1 = formdata["result1"].value;
			let r2 = formdata["result2"].value;
			let r3 = formdata["result3"].value;
			if( r1 != "" &&
			    r2 != "" &&
				r3 != "" &&			
				r1 != r2 &&
			    r2 != r3 &&
				r1 != r3
				) 
			{
				is_form_valid = true;
			}
			
			if( !is_form_valid )
			{
				output_element.innerHTML = "Form is not valid";
			}
			else
			{
							
				//confirm pop up			
				let message = "Winners for " + month + " are First: " + r1 + ", Second: " + r2 + ", Third: " + r3 + "\nIs that correct?";
				
				if( confirm(message) )
				{
					//send uploadResultRequest
					//display upload result in upload_result_info element
														
					const url = <?php echo "\"$rest_url\""; ?>;					
					const nonce = <?php	echo "\"$wp_nonce\""; ?>;
										
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
								output_element.innerHTML = "result successfully uploaded";
							}
							else{													
								output_element.innerHTML = body.error;
							}
						}						
					)				
					.catch( (error) => {					
						output_element.innerHTML = "unknown exception";
									
						}
					);
								
				}
			}
			return false;
		}
	</script>
	<?php
}

function lionslotto_display_logged_out_admin_info()
{
	?>
	<h2>user logged out display</h2>
	<p><a href="../login">Log-in</a></p>
	<?php
}



function lionslotto_display_tickets()
{
		
	if ( current_user_can('edit_lotto') ) { 
	
		global $wpdb;
	
		$user_id = get_current_user_id();
	
		$results = $wpdb->get_results(
			"        
			SELECT *
			FROM wp_lotto_numbers       
			WHERE (state='BOUGHT' OR state='BOUGHT_MANUALLY')
			"
		);
	?>
	<h2>Tickets Info</h2>	
	<?php 
	$tickets_sold = count($results);
	echo "<p>$tickets_sold Tickets sold</p>";
	?>
	
	<table style="width:100%">
	<tr>
    <th>Number</th>
    <th>Bought</th>
    <th>User</th>
	<th>Assigned by</th>
	</tr>
	<?php
	foreach( $results as $result)
	{
		echo "<tr>";
		echo "<td>$result->display_value</td>";
		$time_bought = date('d M Y', $result->state_change_time);
		echo "<td>$time_bought</td>";
		
		if( $result->state == 'BOUGHT_MANUALLY' )
		{
			$user_name = $wpdb->get_var("
				SELECT user_name
				FROM wp_lotto_manual_purchases
				WHERE number_id=$result->ID				
				");
			echo "<td>$user_name</td>";
			
			$admin_data = get_userdata( $result->user_id );
			echo "<td>$admin_data->display_name</td>";

		}
		else
		{
			$user_info = get_userdata( $result->user_id );
			echo "<td>$user_info->display_name</td>";
			echo "<td></td>";
		}
		
		echo "</tr>";
	}
	?>
	</table>
	</p>	
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

