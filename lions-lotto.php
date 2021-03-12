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
//view my tickets
//view results
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
//settings page
add_action( 'admin_menu', 'lionslotto_add_settings_page' );
add_action( 'admin_init', 'lionslotto_register_settings' );

function lionslotto_add_settings_page() {
	//only allow those with 'manage options' privileges to see
    add_options_page( 'Lions Lotto', 'Lions Lotto', 'manage_options', 'lionslotto_settings_page', 'lionslotto_render_settings_page' );
}

function lionslotto_render_settings_page(){
	    ?>
    <h2>Lions Lotto Settings render</h2>
    <form action="options.php" method="post">
        <?php 
        settings_fields( 'lionslotto_settings_fields' );
        do_settings_sections( 'lionslotto_settings_section' ); 
		?>
        <input name="submit" class="button button-primary" type="submit" value="<?php esc_attr_e( 'Save' ); ?>" />
    </form>
    <?php
}

function lionslotto_register_settings() {
    register_setting( 'lionslotto_settings_fields', 'lionslotto_settings_fields', 'lionslotto_validate_settings' );
    
	add_settings_section( 'stripe_settings', 'Stripe Settings', 'lionslotto_section_stripe_text', 'lionslotto_settings_section' );
	  
	add_settings_field( 'lionslotto_setting_stripe_public_key', 'Stripe Public Key', 'lionslotto_setting_stripe_public_key', 'lionslotto_settings_section', 'stripe_settings' ); 
    add_settings_field( 'lionslotto_setting_stripe_key', 'Stripe Secret Key', 'lionslotto_setting_stripe_key', 'lionslotto_settings_section', 'stripe_settings' );
   
}

function lionslotto_validate_settings( $input ) {
    $newinput['stripe_key'] = trim( $input['stripe_key'] );
    if ( ! preg_match( '/^[_A-Za-z0-9]{32}$/i', $newinput['stripe_key'] ) ) {
        $newinput['stripe_key'] = 'invalid key';
    }
	
	$newinput['stripe_public_key'] = trim( $input['stripe_public_key'] );
    if ( ! preg_match( '/^[_A-Za-z0-9]{32}$/i', $newinput['stripe_public_key'] ) ) {
        $newinput['stripe_public_key'] = 'invalid key';
    }
	
    return $newinput;
}

function lionslotto_section_stripe_text() {
    echo '<p>Here you can set all the options for using the Stripe API</p>';
}

function lionslotto_setting_stripe_key() {
    $options = get_option( 'lionslotto_settings_fields' , array( 'stripe_key' => "please enter stripe key") );
	$key_value = esc_attr( $options['stripe_key'] );	    
    echo "<input size='35' id='lionslotto_setting_stripe_key' name='lionslotto_settings_fields[stripe_key]' type='text' value='$key_value' />";
}

function lionslotto_setting_stripe_public_key() {
    $options = get_option( 'lionslotto_settings_fields' , array( 'stripe_public_key' => "please enter stripe public key") );
	$key_value = esc_attr( $options['stripe_public_key'] );	    
    echo "<input size='35' id='lionslotto_setting_stripe_public_key' name='lionslotto_settings_fields[stripe_public_key]' type='text' value='$key_value' />";
}


//////////////////////////////////////////////////////////////////////
register_activation_hook( __FILE__, 'lionslotto_on_activation' );


function lionslotto_on_activation()
{
	lionslotto_create_db();	
	lionslotto_init_cron_jobs();
}

function lionslotto_create_db() {

	
	global $wpdb;
	$charset_collate = $wpdb->get_charset_collate();
	$table_name = $wpdb->prefix . 'lionslotto_numbers';

	$wpdb->query(
		"CREATE TABLE IF NOT EXISTS $table_name (
			ID bigint(20) unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
			display_value tinyint(3) unsigned NOT NULL,
			state ENUM('UNUSED','LOCKED','BUYING','BOUGHT') DEFAULT 'UNUSED' NOT NULL,
			state_change_time bigint unsigned,
			user_id bigint(20) unsigned,
			token bigint(20) unsigned,
			session_id VARCHAR(128),
			FOREIGN KEY (user_id) REFERENCES wp_users(ID)
		) $charset_collate;"
	);

	//TODO check if the numbers exist or not
	// or have admin create the numbers
	for( $num = 1; $num <= 200; $num++)
	{
		$wpdb->insert( 
		$table_name, 
			array( 
			'display_value' => $num, 
			'state' => 'UNUSED', 		
			)
		);
	}		
}

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

/////////////////////////////////////////////////////////////////////

// This loads the grid css
add_action('wp_enqueue_scripts', 'callback_for_setting_up_scripts');
function callback_for_setting_up_scripts() {
	
	wp_register_style('lionslotto', plugins_url('grid_style.css',__FILE__ ));
    wp_enqueue_style('lionslotto');    
}

/////////////////////////////////////////////////////////////////////////
// Attempt to lock a number before purchasing 
// This prevents anyone else from buying the same number at the same time

function try_lock_number( WP_REST_Request $request ) 
{
	global $wpdb;

	$num_to_lock = $request->get_param( 'number' );
    $user_id = get_current_user_id(); //$request->get_param( 'user_id' );
  
	$locked = $wpdb->update( 
		'wp_lionslotto_numbers', 
		array( 
			'state' => 'LOCKED',   
			'state_change_time' => time(), 
			'user_id' => $user_id,
		), 
		array( 
			'display_value' => $num_to_lock,
			'state' => 'UNUSED'
		)
	);
  
  
  ///////////////
 
	if( $locked )
	{
		return array( 
		 'locked' => $num_to_lock,
		 'success' => true,
		);
	}
	else{
		return array( 
		 'locked' => -1,
		 'success' => false,
		);
	}	
}

function create_checkout_session( WP_REST_Request $request ) {
			
	$result = array();
	
	$options = get_option( 'lionslotto_settings_fields' );
	if( $options )
	{	
		$stripe_key= $options['stripe_key'];
		//$stripe_key= 'sk_test_4eC39HqLyjWDarjtT1zdp7dc';
	
		\Stripe\Stripe::setApiKey($stripe_key); 
					
		$user_id = get_current_user_id();
		$ticket_id = $request->get_param( 'ticket_id');
		
		global $wpdb;
		//check that the user has the lock on the number
		//calculate a verification token for the purchase
		$token = random_int(0, PHP_INT_MAX);
		
		
		$is_buying = $wpdb->update( 
			'wp_lionslotto_numbers', 
			array( 
				'state' => 'BUYING',   
				'state_change_time' => time(),
				'token' => $token,
			), 
			array( 
				'display_value' => $ticket_id,
				'user_id' => $user_id,
				'state' => 'LOCKED',			
			)
		);
				
		
		if( $is_buying )
		{
			$site_url = get_site_url();
			
			$user_data = get_userdata($user_id);
			$user_email = $user_data->user_email;
				
			$session = \Stripe\Checkout\Session::create([
			'customer_email' => $user_email,  
			'payment_method_types' => ['card'],
			'line_items' => [[
				'price_data' => [
					'currency' => 'gbp',
					'product_data' => [
						'name' => "Southam Lions 200 Club Ticket $ticket_id",
					],
					'unit_amount' => 1200, // TODO make this price an option somewhere
				],
				'quantity' => 1,
				]],
			'mode' => 'payment',
			'success_url' => "$site_url/success/?ticket_id=$ticket_id&token=$token", 
			'cancel_url' => "$site_url/cancel/?ticket_id=$ticket_id",	
			]);

			$wpdb->update( 
				'wp_lionslotto_numbers',
				array('session_id' => $session->id,
				),
				array( 
					'display_value' => $ticket_id,
					'user_id' => $user_id,
					'state' => 'BUYING',			
				)
			);

			$result = array(
				'id' => $session->id,
			); 
		}
		else{
			$result = array(
				'id' => "not buying error",
			); 
		}
	}
	
	return $result;	
}

function complete_purchase( WP_REST_Request $request ) 
{
	global $wpdb;
	
	$user_id = get_current_user_id();
	$ticket_id = $request->get_param( 'ticket_id');
	$token = $request->get_param( 'token' );
	
	$session_id = $wpdb->get_var( "SELECT session_id FROM wp_lionslotto_numbers WHERE user_id=$user_id" );
	
	//want to check token matches
	
	$bought = false;
	
	
	
	//And check with STRIPE site 	
	$stripe_key= $options['stripe_key'];
	$session = null;
	$blah = "yay";
	
	try {
		/*
		$stripe = new \Stripe\StripeClient( 
			array(
				'api_key' => $stripe_key
				)
			);
			*/

		\Stripe\Stripe::setApiKey($stripe_key); 		
		
		//$session = $stripe->checkout->sessions->retrieve( $session_id, array('api_key' => $stripe_key), array('api_key' => $stripe_key) );
		
		$session = \Stripe\Checkout\Session::retrieve( $session_id,null, array('api_key' => $stripe_key) );
	}
	catch (Exception $e) {
		//echo 'Caught exception: ',  $e->getMessage(), "\n";
		$blah = $e->getMessage();
	}
	//\Stripe\Stripe::setApiKey($stripe_key); 
	
	//$session = \Stripe\Service\Checkout\SessionService::retrieve($session_id);
	//$session = \Stripe\Checkout\Session::retrieve(
//		$session_id,
		//[]
	//);
	/*
	
	
	if( isset($session) )
	{
		if( $session->payment_status == "paid")
		{
		
		$bought = $wpdb->update( 
			'wp_lionslotto_numbers', 
			array( 
				'state' => 'BOUGHT',   
				'state_change_time' => time(), 	
				'token' => NULL,
				'session_id' => "hello",
			), 
			array( 
				'display_value' => $ticket_id,
				'user_id' => $user_id,
				'token' => $token,
				'state' => 'BUYING',		
			)
		);	
		}
	}
	
	*/
	
	
	if( $bought )
	{
		return array( 
		 'bought' => $ticket_id,
		 'success' => true,
		 'session_id' => "blah",
		);
	}
	else{
		return array( 
		 'bought' => -1,
		 'success' => false,
		 'session_id' => $blah,
		);
	}	
	
}

/* cancel any numbers in progress for user */
function cancel_purchase( WP_REST_Request $request ) 
{
	global $wpdb;
	
	$user_id = get_current_user_id();
			
	$cancelled = $wpdb->query( 
		"
		UPDATE wp_lionslotto_numbers 
		SET state = 'UNUSED', state_change_time = NULL, user_id = NULL, token = NULL		
		WHERE (state = 'LOCKED' OR state = 'BUYING')
		AND user_id = $user_id
		"
	);		
		
	if( $cancelled )
	{
		return array( 
		 'success' => true,
		);
	}
	else{
		return array( 
		 'success' => false,
		);
	}
}


////////////////////////////////////////////////////////////////////
//permission callbacks
function lionslotto_is_member() {
    // Restrict endpoint to only users who have the edit_posts capability.
    if ( ! current_user_can( 'read' ) ) {
        return new WP_Error( 'rest_forbidden', esc_html__( 'OMG you can not view private data.', 'my-text-domain' ), array( 'status' => 401 ) );
    }
 
    // This is a black-listing approach. You could alternatively do this via white-listing, by returning false here and changing the permissions check.
    return true;
}

////////////////////////////////////////////////////////////////////
//register server api

add_action( 'rest_api_init', function () {
  register_rest_route( 'lionslotto/v1', '/lock_number', 
		array(
			'methods' => 'POST',
			'callback' => 'try_lock_number',
			'permission_callback' => 'lionslotto_is_member',		
		)	
  );
  
   register_rest_route( 'lionslotto/v1', '/create-checkout-session', 
		array(
			'methods' => 'POST',
			'callback' => 'create_checkout_session',
			'permission_callback' => 'lionslotto_is_member',				
		)
	);	
	
	register_rest_route( 'lionslotto/v1', '/complete_purchase', 
		array(
			'methods' => 'POST',
			'callback' => 'complete_purchase',
			'permission_callback' => 'lionslotto_is_member',		
		)
	);
	
	register_rest_route( 'lionslotto/v1', '/cancel_purchase', 
		array(
			'methods' => 'POST',
			'callback' => 'cancel_purchase',
			'permission_callback' => 'lionslotto_is_member',
		)
	);
} 
);


/////////////////////////////////////////////////////////////////////////////

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


//Get Number status
//Set Purchased
//Set Purchasing
//Set LOCKED
//Set Available


///////////////////////////////////////////////////////////////////////////
//unlock all numbers in locked and purchasing state if timeout

function reset_lapsed_locked_numbers()
{
	global $wpdb;
	
	$time_now = time();	
	$seconds_allowed = 900; //15 minutes to lock and purchase
	
	$wpdb->query( 
		"
		UPDATE wp_lionslotto_numbers 
		SET state = 'UNUSED', state_change_time = NULL, user_id = NULL, token = NULL		
		WHERE (state = 'LOCKED' OR state = 'BUYING')
		AND ($time_now - state_change_time) > $seconds_allowed		
		"
	);	
}

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

add_shortcode( 'lionslotto_grid', 'display_200_grid' );
add_shortcode( 'lionslotto_purchase', 'display_200_purchase_form' );
add_shortcode( 'lionslotto_success', 'lionslotto_handle_success');
add_shortcode( 'lionslotto_cancel', 'lionslotto_handle_cancel');

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