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
	?>
	<h2>Manual Assign Form</h2>
	<p>Name and Contact info</p>
	<p></p>
	<p>Button to assign</p>
	
	<p>Assignment Result Here</p>
	<?php
	
	}
}

