<?php 
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