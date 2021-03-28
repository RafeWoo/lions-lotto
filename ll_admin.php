<?php 


add_shortcode( 'lionslotto-admin-display', 'lionslotto_display_admin_info');


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
	<?php
}

function lionslotto_display_logged_out_admin_info()
{
	?>
	<h2>user logged out display</h2>
	<?php
}