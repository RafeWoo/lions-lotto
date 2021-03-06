<?php 
/////////////////////////////////////////////////////////////////////////
// Attempt to lock a number before purchasing 
// This prevents anyone else from buying the same number at the same time

function get_next_available_ticket()
{
	global $wpdb;
	$numbers_table = $wpdb->prefix."lotto_numbers";
	$row = $wpdb->get_row(
		"SELECT * 
		FROM $numbers_table 
		WHERE state = 'UNUSED'
		");
	
	if( isset($row) )
	{
		return $row->ID;
	}
}

function get_next_number( WP_REST_Request $request )
{
	global $wpdb;

	$success = false;	
    $user_id = get_current_user_id();
  	
	$ticket_id = get_next_available_ticket();
	
	$numbers_table = $wpdb->prefix."lotto_numbers";
	if( isset($ticket_id) )
	{
		$success = $wpdb->update( 
			$numbers_table, 
			array( 
				'state' => 'LOCKED',   
				'state_change_time' => time(), 
				'user_id' => $user_id,
			), 
			array( 
				'ID' => $ticket_id,
				'state' => 'UNUSED'
			)
		);
		
	}
	 
	if( $success )
	{
		return array( 		
		 'success' => true,
		 'number' => $ticket_id,
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
		'wp_l  otto_numbers', 
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
		$numbers_table = $wpdb->prefix."lotto_numbers";
		$is_buying = $wpdb->update( 
			$numbers_table, 
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
			$stripe_purchases_table = $wpdb->prefix."lotto_stripe_purchases";
			
			$inserted = $wpdb->insert($stripe_purchases_table, 
				array(
					'number_id' => $ticket_id,
					'user_id' => $user_id,
					'purchase_time' => $purchase_start_time,									
				)
			);
				
			if( $inserted )
			{			
			
				$purchase_id = $wpdb->insert_id;
			
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
						'name' => "Southam Lions 500 Club Ticket $ticket_id",
					],
					'unit_amount' => 1200, // TODO make this price an option somewhere
				],
				'quantity' => 1,
				]],
			'mode' => 'payment',
			'success_url' => "$site_url/lotto-purchase-success/?purchase_id=$purchase_id", 
			'cancel_url' => "$site_url/lotto-purchase-cancel/?purchase_id=$purchase_id",	
			]);

//TODO move this above and put purchase id into flow
//And then update with session id here
			$inserted = $wpdb->update($stripe_purchases_table, 
				array(				
					'session_id' => $session->id,					
				),
				array(
					'ID' => $purchase_id
				)
			);

				if( $inserted )
				{
					$result = array(
						'success' => true,
						'id' => $session->id,
					);
				
				}
				else
				{
					$result = array(
						'success' => false,
						'error' => 'database update error',
					);
				}
				
			}
			else{
				$result = array(
					'success' => false,
					'error' => 'insertion error',
				);
			}
			 
		}
		else{
			$result = array(
				'success' => false,
				'error' => "not buying error",
			); 
		}
	}
	
	return $result;	
}

function update_db_complete_purchase( $user_id, $purchase_id )
{		
	$bought = false;
		
	global $wpdb;
	$stripe_purchases_table = $wpdb->prefix."lotto_stripe_purchases";
	$purchase_info = $wpdb->get_row( "
		SELECT session_id, number_id 
		FROM $stripe_purchases_table 
		WHERE ID=$purchase_id   
		AND user_id=$user_id
		AND state='STARTED'
		");
		
	if( isset($purchase_info) )
	{
		$session_id = $purchase_info->session_id;
		
		if( isset($session_id) )
		{		
			
			$stripe_key = null;
			$options = get_option( 'lionslotto_settings_fields' );
			if( $options )
			{	
				$stripe_key= $options['stripe_secret_key'];
			}	
	
			\Stripe\Stripe::setApiKey($stripe_key); 						
			$session = \Stripe\Checkout\Session::retrieve( $session_id ); 
	
	
	if( isset($session) )
	{		
		//$bought = $session->payment_status;
		if( $session->payment_status == 'paid' )
		{
			$ticket_id = $purchase_info->number_id;
			$purchase_complete_time = time();
			$numbers_table = $wpdb->prefix."lotto_numbers";
			//TODO - TRANSACTIONs
			$updated1 = $wpdb->update( 
				$numbers_table, 
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
				
				
				$updated2 = $wpdb->update( 
					$stripe_purchases_table,
					array( 
						'state' => 'COMPLETE',
						'purchase_time' => $purchase_complete_time,						
					),
					array(
						'number_id' => $ticket_id,
						'user_id' => $user_id,
						'session_id' => $session->id,
						'state' => 'STARTED',
					)
				);	

				if( $updated2 )
				{
					$bought = true;
				}
			}
			else
			{
								
				$updated2 = $wpdb->update( 
					$stripe_purchases_table,
					array( 
						'state' => 'UNASSIGNED',
						'purchase_time' => $purchase_complete_time,						
					),
					array(						
						'number_id' => $ticket_id,
						'user_id' => $user_id,
						'session_id' => $session_id,
						'state' => 'STARTED',					
					)
				);	

				if( $updated2 )
				{
					$bought = true;
				}
			}
		}
	}
	}
	}
	return $bought;
}

function update_db_cancel_purchase($user_id, $purchase_id)
{
	global $wpdb;
	
	$stripe_purchases_table = $wpdb->prefix."lotto_stripe_purchases";
	$ticket_id = $wpdb->get_var( "
		SELECT number_id 
		FROM $stripe_purchases_table 
		WHERE ID=$purchase_id   
		AND user_id=$user_id
		AND state='STARTED'
		");
	
	if( $ticket_id )
	{
	
		$updated2 = $wpdb->update( 
			$stripe_purchases_table,
			array(
				'state' => 'CANCELLED',
			),
			array(
				'ID' => $purchase_id,
				'user_id' => $user_id,
				'state' => 'STARTED'
			)
		);
	
	
		$numbers_table = $wpdb->prefix."lotto_numbers";
		$updated1 = $wpdb->update( 
					$numbers_table, 
					array( 
						'state' => 'UNUSED',   
						'state_change_time' => NULL,
						'user_id' => NULL,
					), 
					array( 
						'ID' => $ticket_id,
						'user_id' => $user_id,						
						'state' => 'BUYING',		
					)
		);
					
	}	
			
	$cancelled = $updated1 and $updated2;
	
	return $cancelled;
}


function complete_purchase( WP_REST_Request $request ) 
{
	
	$user_id = get_current_user_id();
	$purchase_id = $request->get_param( 'purchase_id');

	$error_message = "Unknown Error";
	$bought = false;
	
	try {			
		$bought = update_db_complete_purchase($user_id, $purchase_id);		
	}	
	catch (Exception $e) {
		//echo 'Caught exception: ',  $e->getMessage(), "\n";
		$error_message = $e->getMessage();
	}
	
	
	if( $bought  )
	{
		return array( 		
		 'success' => true,		 	
		);
	}
	else{
		return array( 		
		 'success' => false,
		 'error' => $error_message,
		);
	}	
	
}

/* cancel specific number in progress for user */
function cancel_purchase( WP_REST_Request $request ) 
{
	global $wpdb;
	
	$user_id = get_current_user_id();			
	$purchase_id = $request->get_param( 'purchase_id');	
	
	//TODO - TRANSACTIONS
	
	$cancelled = update_db_cancel_purchase($user_id, $purchase_id);
		
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

/* try and catch any errors in flow by processing completion of any tickets for user */
function update_user_purchases( WP_REST_Request $request ) 
{
	global $wpdb;
	
	$user_id = get_current_user_id();			

	$message = "reached update_user_purchases for user = $user_id";
	//TODO - TRANSACTIONS
	
	//for each locked ticket with user id
		//just put back to unused	
	$numbers_table = $wpdb->prefix."lotto_numbers";	
	$wpdb->update( $numbers_table,
			array( 
				'state' => 'UNUSED',   
				'state_change_time' => NULL,
				'user_id' => NULL,
			),
			array(
				'state' => 'LOCKED',   				
				'user_id' => $user_id,
			)
	);
	
	
	$stripe_purchases_table = $wpdb->prefix."lotto_stripe_purchases";
	$live_purchases = $wpdb->get_results(
						"
						SELECT ID 
						FROM $stripe_purchases_table				
						WHERE user_id=$user_id
						AND state='STARTED'
						");
						
	
	if( $live_purchases )
	{
		$message = "got some live purchases count=".count($live_purchases);
		
		foreach( $live_purchases as $purchase)
		{
			$purchase_id = $purchase->ID;
			$message = $message." , $purchase_id"; //.json_encode($purchase_id).json_encode(gettype($purchase_id));
		
			try
			{
				
				if( !update_db_complete_purchase($user_id, $purchase_id) )
				{
					update_db_cancel_purchase($user_id, $purchase_id);
					$message = $message." cancelled";
				}
				else
				{
					$message = $message." paid";
				}
				
			
			}
			catch(Exception $e)
			{
				$message = $message."error";
			}
			
		}
	}
	//for each in buying state ticket with user id
		//check if purchase complete
		//otherwise set cancelled
	
	//if( $cancelled )
	//{
		return array( 
		 'success' => true,
		 'message' => $message,
		);
	//}
	//else{
	//	return array( 
	//	 'success' => false,
	//	);
//}
}




function assign_ticket(WP_REST_Request $request)
{
	global $wpdb;
	
	$name = sanitize_text_field( $request->get_param( 'name'));
	$email = sanitize_email($request->get_param( 'email' ));
	$address1 = sanitize_text_field( $request->get_param( 'address1'));
	$address2 = sanitize_text_field( $request->get_param( 'address2'));
	$address3 = sanitize_text_field( $request->get_param( 'address3'));
	$postcode = sanitize_text_field( $request->get_param( 'postcode'));
	$phone = sanitize_text_field( $request->get_param( 'phone'));
	
	$valid_input = true;	
	$error_message = "Unknown Error";
	
	if( empty($name) or (empty($email) and empty($address1) and empty($phone) ) )
	{
		$valid_input = false;
		$error_message = "bad inputs";
	}
	
	
	if( $valid_input )
	{
	
		//get next available number 
		$ticket_id = get_next_available_ticket();
		
		//if number exists
		if( isset($ticket_id))
		{
			$purchase_complete_time = time();
			$admin_id = get_current_user_id();
			
			$updated1 = true;
			$updated2 = false;
	
			//mark as bought
			$numbers_table = $wpdb->prefix."lotto_numbers";
			$updated1 = $wpdb->update( 
				$numbers_table, 
				array( 
					'state' => 'BOUGHT_MANUALLY',   
					'state_change_time' => $purchase_complete_time, 													
					'user_id' => $admin_id,
				), 
				array( 
					'ID' => $ticket_id,										
					'state' => 'UNUSED',		
				)
			);
		
			//update the manual purchase array
			if( $updated1 )
			{
				try{
					$man_purchases_table = $wpdb->prefix."lotto_manual_purchases";				
					$updated2 = $wpdb->insert($man_purchases_table,
						array(
						'number_id' => $ticket_id,
						'admin_id' => $admin_id,
						'purchase_time' => $purchase_complete_time,
						'user_name' => $name,
						'user_phone' => $phone,
						'user_email' => $email,
						'user_address_1' => $address1,
						'user_address_2' => $address2,
						'user_address_3' => $address3,
						'user_postcode' => $postcode,						
						)										
					);
					
				
					if( $updated2 )
					{
						//ticket assigned
						$ticket_assigned = $ticket_id;
					}
					else{
						$error_message = "Failed to insert user details";
					}
				}
				catch(Exception $e)
				{
					$error_message = $e.getMessage();					
				}
										
			}
			else{
				$error_message = "Failed to update number table";
			}
				
		}
		else
		{
			$error_message = "No tickets left";
		}
		
	}
	
	if( isset($ticket_assigned) )
	{

		return array( 
			'success' => true,
			'ticket' => $ticket_assigned
		);
	}
	else
	{
		return array( 
		 'success' => false,
		 'error' => $error_message
		);
	}
}


function set_lotto_result(WP_REST_Request $request)
{
	global $wpdb;
	
	//get month
	//r1,r2,r3
	$result_month = $request->get_param( 'result-month');
	$result_1 = $request->get_param( 'result1');
	$result_2 = $request->get_param( 'result2');
	$result_3 = $request->get_param( 'result3');

	$error_message = "unknown_error";	
	$result_valid = false;
	
	//must be for a month not set yet
	$results_table = $wpdb->prefix."lotto_results";
	$results_for_month = $wpdb->get_results(
		"
		SELECT *
		FROM $results_table
		WHERE month='$result_month'
		");
	
	if( isset($results_for_month) && count($results_for_month) > 0 ) //TODO add check for this year comparison
	{
		//$debug = var_export($results_for_month, true);
		
		$error_message = "Result is already set for ".$result_month;//." ".count($results_for_month)." ".$debug;
	}
	else{
	//TODO is it the next expected month?
	
	//results all different
	if( isset($result_1) &&
	    isset($result_2) && 
		isset($result_3) &&
		$result_1 != $result_2 &&
		$result_2 != $result_3 &&
		$result_1 != $result_3 )
	{

		$creation_time = time();
		//insert results into database
		$result_inserted = $wpdb->insert($results_table,
				array(
					
					'creation_time' => $creation_time,
					'month' => $result_month,
					'first_id' => $result_1,
					'second_id' => $result_2,
					'third_id' => $result_3,					
				)
		);
		
	
		
		if( $result_inserted )
		{
			//commit
			$result_valid = true;
		}
		else{
			$error_message = "database error";
		}
	}
	}
	
	if( $result_valid )
	{
		return array(
			'success' => true
		);		
	}
	else{
		return array( 
			'success' => false,
			'error' => $error_message
		);
	}
	
}


function do_debug(WP_REST_Request $request)
{
	global $wpdb;
	
	$user_id = get_current_user_id();
	
	$numbers_table = $wpdb->prefix."lotto_numbers";
	$results = $wpdb->get_results(
		"        
		SELECT *
        FROM $numbers_table
        WHERE user_id=$user_id
		AND state='BOUGHT'
		"
	);
	
	if( isset($results) )
	{
		$success = true;
		$message = "count=".count($results);
	}
	else{
		$success = false;
		$message = "no results";
	}
	return array(
		'success' => $success,
		'msg' => $message,
	);
	
	/*
	//get month
	//r1,r2,r3
	$name = $request->get_param( 'name' );
		
	$charset_collate = $wpdb->get_charset_collate();
	$table_name = $wpdb->prefix.$name;
	$user_table = $wpdb->prefix.'users';
	$success = $wpdb->query(		
		"	
			CREATE TABLE IF NOT EXISTS $table_name (
				ID bigint(20) unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
				display_value smallint(3) unsigned NOT NULL,				
				state ENUM('UNUSED','LOCKED','BUYING','BOUGHT','BOUGHT_MANUALLY') DEFAULT 'UNUSED' NOT NULL,
				state_change_time bigint unsigned,
				user_id bigint(20) unsigned,
				FOREIGN KEY (user_id) REFERENCES $user_table(ID)				
			)$charset_collate;	
		"	
	);	
	
	if( $success )
	{
		$message = "created table";
		$rows = $wpdb->get_results("SELECT * FROM $table_name");
		if( !isset($rows) || count($rows) == 0)
		{
			for( $i = 1; $i <= 500; $i++)
			{
				$inserted = $wpdb->insert($table_name,
					array(
						'display_value' => $i,
						)
					);

				if( !$inserted )
				{
					$success = false;
					$message = "failed to insert";
				}				
			}
		}
	}
	else{
		$message = "table creation failed";
	}
	*/
	
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


function lionslotto_is_lottoadmin() {
    // Restrict endpoint to only users who have the edit_posts capability.
    if ( ! current_user_can( 'edit_lotto' ) ) {
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
	
	register_rest_route( 'lionslotto/v1', '/update_user_purchases', 
		array(
			'methods' => 'POST',
			'callback' => 'update_user_purchases',
			'permission_callback' => 'lionslotto_is_member',
		)
	);
	
	
	register_rest_route( 'lionslotto/v1', '/assign-ticket', 
		array(
			'methods' => 'POST',
			'callback' => 'assign_ticket',
			'permission_callback' => 'lionslotto_is_lottoadmin',
		)
	);
	
	register_rest_route( 'lionslotto/v1', '/set-result', 
		array(
			'methods' => 'POST',
			'callback' => 'set_lotto_result',
			'permission_callback' => 'lionslotto_is_lottoadmin',
		)
	);
	
	register_rest_route( 'lionslotto/v1', '/post-debug', 
		array(
			'methods' => 'POST',
			'callback' => 'do_debug',
			'permission_callback' => 'lionslotto_is_lottoadmin',
		)
	);
	
} 
);