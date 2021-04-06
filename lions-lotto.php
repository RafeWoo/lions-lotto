<?php
/*
Plugin Name: lions-lotto
Plugin URI: https://southamlions.org.uk
Description: Provides Simple Lottery functionality.
Version: 0.1
Author: david woo
Author URI: https://southamlions.org.uk
License: MIT
Text Domain: lions-lotto
*/


//As a user I want to be able to
//buy tickets  		- done
//view my tickets   - done
//view results      - done
//set options
	//email me about lionslotto results
	//email me when time comes to renew

//as an admin I want to 
 //view all ticket->users
 //view all users->tickets
  //generate a result 
  //broadcast result
  //view results

///////////////////////////////////////////////////////////////////////

//results table
// month
// winnings
// winner

//club user settings
//user_id
//

//club numbers
//id - primary key
//display - the string to display
//info about year and month?

//state - available/locked/purchasing/purchased
//user_id
//time of state change

//purchases table?
//store receipts

//available table ?
//number_id

//lock table
//number_id
//user_id
//lock time

//number state can be one of UNUSED, LOCKED, BUYING, BOUGHT

///////////////////////////////////////////////////////////////////////


require_once 'stripe/init.php';   //stripe payments api


use Slim\Http\Request;
use Slim\Http\Response;
use Stripe\Stripe;

/////////////////////////////////////////////////////////////////////

require_once "ll_settings.php";
require_once "ll_rest.php";
require_once "ll_short_codes.php";
require_once "ll_database.php";
require_once "ll_admin.php";


//////////////////////////////////////////////////////////////////////
register_activation_hook( __FILE__, 'lionslotto_on_activation' );


function lionslotto_on_activation()
{
	//create database tables
	lionslotto_init_database();	

	lionslotto_init_roles();
	
	lionslotto_init_cron_jobs();
}

//

//////////////////////////////////////////////////////
//lotto roles and caps

function lionslotto_init_roles()
{
	add_role(
		'lionslotto_admin',
		'Lotto Admin',
		array(
			'read'         => true,  // true allows this capability
			'edit_lotto'   => true,
			'read_private_posts' => true,
			'read_private_pages' => true,
		)
	);
	
	// add $cap capability to this role object
	$admin_role = get_role( 'administrator' );	
	$admin_role->add_cap( 'edit_lotto' );
}



////////////////////////////////////////////////////////////////////


function lionslotto_init_cron_jobs()
{
	if ( ! wp_next_scheduled( 'lionslotto_cron_hook' ) ) {
		wp_schedule_event( time(), 'reset_number_time_interval', 'lionslotto_cron_hook' );
	}
}

add_filter( 'cron_schedules', 'lionslotto_add_cron_interval' );

function lionslotto_add_cron_interval( $schedules ) { 
    $schedules['reset_number_time_interval'] = array(
        'interval' => 300,	//seconds - check for number reset every 5 minutes
        'display'  => esc_html__( 'reset_number_time_interval' ), );
    return $schedules;
}


add_action( 'lionslotto_cron_hook', 'lionslotto_cron_exec' );

function lionslotto_cron_exec()
{
	reset_lapsed_locked_numbers();
}


//unlock all numbers in locked and purchasing state if timeout

function reset_lapsed_locked_numbers()
{
	global $wpdb;
	
	$time_now = time();	
	$seconds_allowed = 900; //15 minutes to lock and purchase
	
	
	
	
	$wpdb->query( 
		"
		UPDATE wp_lotto_numbers 
		SET state = 'UNUSED', state_change_time = NULL, user_id = NULL		
		WHERE (state = 'LOCKED' )
		AND ($time_now - state_change_time) > $seconds_allowed		
		"
	);	
	
	//OR state = 'BUYING' //see if any started transactions have completed
	
	
	$live_purchases = $wpdb->get_results(
						"
						SELECT ID,user_id
						FROM wp_lotto_stripe_purchases				
						WHERE state='STARTED'
						AND ($time_now - purchase_time) > $seconds_allowed
						");
						
	
	if( $live_purchases )
	{
		//$message = "got some live purchases count=".count($live_purchases);
		
		foreach( $live_purchases as $purchase)
		{
			$purchase_id = $purchase->ID;
			$user_id = $purchase->user_id;
			//$message = $message." , $purchase_id"; //.json_encode($purchase_id).json_encode(gettype($purchase_id));
		
			try
			{
				
				if( !update_db_complete_purchase($user_id, $purchase_id) )
				{
					update_db_cancel_purchase($user_id, $purchase_id);
					//$message = $message." cancelled";
				}
									
			}
			catch(Exception $e)
			{
				//$message = $message."error";
			}
			
		}
	}
	
}


/////////////////////////////////////////////////////////////////////

// This loads the grid css
add_action('wp_enqueue_scripts', 'callback_for_setting_up_scripts');
function callback_for_setting_up_scripts() {
	
	wp_register_style('lionslotto', plugins_url('grid_style.css',__FILE__ ));
    wp_enqueue_style('lionslotto');    
}

/////////////////////////////////////////////////////////////////////////////



//Get Number status
//Set Purchased
//Set Purchasing
//Set LOCKED
//Set Available


/////////////////////////////////////////////////////////////////////////////
//TODO move to a separate plugin

function login_default_page($redirect_to, $request, $user) {
	
    //is there a user to check?
    if ( isset( $user->roles ) && is_array( $user->roles ) ) {
        //check for admins
        if ( in_array( 'administrator', $user->roles ) ) {
            // redirect them to the default place
            return $redirect_to;
        } else {
            return 'user'; //home_url();
        }
    } else {
        return $redirect_to;
    }
}

add_filter('login_redirect', 'login_default_page', 10, 3);

/////////////////////////////////////////////////////////////////////////////
//TODO REMOVE THIS
add_shortcode( 'lionslotto_landing', 'display_200_landing'); //TODO move to separate plugin


function display_200_landing() {
	if ( ! is_user_logged_in() ) { // Display WordPress login form:
    $args = array(
        'redirect' => admin_url(), 
        'form_id' => 'loginform-custom',
        'label_username' => __( 'Username custom text' ),
        'label_password' => __( 'Password custom text' ),
        'label_remember' => __( 'Remember Me custom text' ),
        'label_log_in' => __( 'Log In custom text' ),
        'remember' => true
    );
    wp_login_form( $args );
} else { // If logged in:
    wp_loginout( home_url() ); // Display "Log Out" link.
    echo " | ";
    wp_register('', ''); // Display "Site Admin" link.
}
}

/////////////////////////////////////////////////////////////////////////////////////
