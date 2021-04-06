<?php

function lionslotto_init_database()
{
	lionslotto_create_numbers_table();
	lionslotto_create_results_table();
	lionslotto_create_stripe_purchases_table();
	lionslotto_create_manual_purchases_table();  //for people who don't want to register
}

function lionslotto_create_numbers_table()
{
	global $wpdb;
	$charset_collate = $wpdb->get_charset_collate();
	$table_name = $wpdb->prefix.'lotto_numbers';
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
		$rows = $wpdb->get_results("SELECT * FROM $table_name");
		if( !isset($rows) || count($rows) == 0)
		{
			for( $i = 1; $i <= 500; $i++)
			{
				$wpdb->insert($table_name,
					array(
						'display_value' => $i,
						)
					);
			}
		}
	}
	
	
}

function lionslotto_create_results_table()
{
	global $wpdb;
	$charset_collate = $wpdb->get_charset_collate();
	$table_name = $wpdb->prefix . 'lotto_results';
	$numbers_table = $wpdb->prefix."lotto_numbers";
	$wpdb->query(		
		"	
		CREATE TABLE IF NOT EXISTS $table_name (
			ID bigint(20) unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,				
			creation_time bigint unsigned,
			month ENUM('JANUARY','FEBRUARY','MARCH','APRIL', 'MAY', 'JUNE', 'JULY', 'AUGUST', 'SEPTEMBER', 'OCTOBER', 'NOVEMBER', 'DECEMBER') DEFAULT 'JANUARY' NOT NULL,			
			first_id bigint(20) unsigned,
			second_id bigint(20) unsigned,
			third_id bigint(20) unsigned,				
			FOREIGN KEY (first_id) REFERENCES $numbers_table(ID),
			FOREIGN KEY (second_id) REFERENCES $numbers_table(ID),
			FOREIGN KEY (third_id) REFERENCES $numbers_table(ID)
		)$charset_collate;
		"	
	);	
}

function lionslotto_create_stripe_purchases_table()
{
	global $wpdb;
	$charset_collate = $wpdb->get_charset_collate();
	$table_name = $wpdb->prefix . 'lotto_stripe_purchases';
	
	$numbers_table = $wpdb->prefix."lotto_numbers";
	$user_table = $wpdb->prefix.'users';
	$wpdb->query(		
		"	
		CREATE TABLE IF NOT EXISTS $table_name (
			ID bigint(20) unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
			number_id bigint(20) unsigned,
		    user_id bigint(20) unsigned NOT NULL,
			purchase_time bigint unsigned NOT NULL,						
			session_id VARCHAR(128),
			state ENUM('STARTED','COMPLETE','CANCELLED','UNASSIGNED') DEFAULT 'STARTED' NOT NULL,
			FOREIGN KEY (number_id) REFERENCES $numbers_table(ID),
			FOREIGN KEY (user_id) REFERENCES $user_table(ID)
		)$charset_collate;
		"	
	);	
}


function lionslotto_create_manual_purchases_table()
{
	global $wpdb;
	$charset_collate = $wpdb->get_charset_collate();
			
	$table_name = $wpdb->prefix . 'lotto_manual_purchases';
	$numbers_table = $wpdb->prefix."lotto_numbers";
	$user_table = $wpdb->prefix.'users';
	
	$wpdb->query(		
		"	
		CREATE TABLE IF NOT EXISTS $table_name (
			ID bigint(20) unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
			number_id bigint(20) unsigned NOT NULL,
		    admin_id bigint(20) unsigned NOT NULL,
			purchase_time bigint unsigned NOT NULL,											
			user_name VARCHAR(128) NOT NULL,
			user_phone VARCHAR(22),
			user_email VARCHAR(255),
			user_address_1 VARCHAR(128),
			user_address_2 VARCHAR(128),
			user_address_3 VARCHAR(128),
			user_postcode VARCHAR (10),
			FOREIGN KEY (number_id) REFERENCES $numbers_table(ID),
			FOREIGN KEY (admin_id) REFERENCES $user_table(ID)
			)$charset_collate;
		"	
	);	
}


