<?php 
/////////////////////////////////////////////////////////////////////////
// Attempt to lock a number before purchasing 
// This prevents anyone else from buying the same number at the same time

function get_next_number( WP_REST_Request $request )
{
	global $wpdb;


	$success = false;
	
    $user_id = get_current_user_id();
  
  
	$row = $wpdb->get_row("SELECT * FROM wp_lotto_numbers WHERE state = 'UNUSED'");
	
	
	if( isset($row) )
	{
		$success = $wpdb->update( 
			'wp_lotto_numbers', 
			array( 
				'state' => 'LOCKED',   
				'state_change_time' => time(), 
				'user_id' => $user_id,
			), 
			array( 
				'ID' => $row->ID,
				'state' => 'UNUSED'
			)
		);
		
	}
	 
	if( $success )
	{
		return array( 		
		 'success' => true,
		 'number' => $row->ID,
		);
	}
	else{
		return array( 		
		 'success' => false,
		);
	}	
		
}

/*
function try_lock_number( WP_REST_Request $request ) 
{
	global $wpdb;

	$num_to_lock = $request->get_param( 'number' );
    $user_id = get_current_user_id(); //$request->get_param( 'user_id' );
  
	$locked = $wpdb->update( 
		'wp_lotto_numbers', 
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
*/

function create_checkout_session( WP_REST_Request $request ) {
			
	$result = array();
	
	$options = get_option( 'lionslotto_settings_fields' );
	if( $options )
	{	
		$stripe_key= $options['stripe_secret_key'];
		//$stripe_key= 'sk_test_4eC39HqLyjWDarjtT1zdp7dc';
	
		\Stripe\Stripe::setApiKey($stripe_key); 
					
		$user_id = get_current_user_id();
		$ticket_id = $request->get_param( 'ticket_id');
		
		global $wpdb;
		//check that the user has the lock on the number
		//calculate a verification token for the purchase
		//$token = random_int(0, PHP_INT_MAX);
		
		$purchase_start_time = time();
		
		$is_buying = $wpdb->update( 
			'wp_lotto_numbers', 
			array( 
				'state' => 'BUYING',   
				'state_change_time' => $purchase_start_time,				
			), 
			array( 
				'ID' => $ticket_id,
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
			'success_url' => "$site_url/lotto-purchase-success/?ticket_id=$ticket_id", 
			'cancel_url' => "$site_url/lotto-purchase-cancel/?ticket_id=$ticket_id",	
			]);


			$inserted = $wpdb->insert('wp_lotto_stripe_purchases', 
				array(
					'number_id' => $ticket_id,
					'user_id' => $user_id,
					'purchase_time' => $purchase_start_time,
					'session_id' => $session->id,					
				)
			);

			if( $inserted )
			{
				$result = array(
					'id' => $session->id,
				);
			}
			else{
				$result = array(
					'error' => 'database error',
				);
			}
			 
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

	
	$session_id = $wpdb->get_var( "SELECT session_id FROM wp_lotto_stripe_purchases WHERE user_id=$user_id" );
	
	//want to check token matches
	
	$bought = false;
	
	$stripe_key = null;
	$options = get_option( 'lionslotto_settings_fields' );
	if( $options )
	{	
		$stripe_key= $options['stripe_secret_key'];
	}

	$blah = "yay";
	
	try {
	
		\Stripe\Stripe::setApiKey($stripe_key); 		
		
			
		$session = \Stripe\Checkout\Session::retrieve( $session_id ); 
		
		if( isset($session) )
		{
			if( $session->payment_status == "paid")
			{
				$purchase_complete_time = time();
				
				//TODO - TRANSACTION
				$updated1 = $wpdb->update( 
					'wp_lotto_numbers', 
					array( 
						'state' => 'BOUGHT',   
						'state_change_time' => $purchase_complete_time, 													
					), 
					array( 
						'ID' => $ticket_id,
						'user_id' => $user_id,						
						'state' => 'BUYING',		
					)
				);	
				
				if( $updated1 )
				{
					$bought = $wpdb->update( 
						'wp_lotto_stripe_purchases',
						array( 
							'state' => 'COMPLETE',
							'purchase_time' => $purchase_complete_time,						
						),
						array(
							'number_id' => $ticket_id,
							'user_id' => $user_id,
							'session_id' => $session_id,
							'state' => 'STARTED',
						)
					);					
				}						
			}
		}
	}	
	catch (Exception $e) {
		//echo 'Caught exception: ',  $e->getMessage(), "\n";
		$blah = $e->getMessage();
	}
	
	
	if( $bought )
	{
		return array( 
		 'bought' => $ticket_id,
		 'success' => true,
		
		);
	}
	else{
		return array( 
		 'bought' => -1,
		 'success' => false,
		
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
		UPDATE wp_lotto_numbers 
		SET state = 'UNUSED', state_change_time = NULL, user_id = NULL		
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
	
	/*
  register_rest_route( 'lionslotto/v1', '/lock_number', 
		array(
			'methods' => 'POST',
			'callback' => 'try_lock_number',
			'permission_callback' => 'lionslotto_is_member',		
		)	
  );
  */
  
  register_rest_route( 'lionslotto/v1', '/get_next_number', 
		array(
			'methods' => 'POST',
			'callback' => 'get_next_number',
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