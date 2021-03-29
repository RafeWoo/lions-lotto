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
	$table_name = $wpdb->prefix . 'lotto_numbers';
	$wpdb->query(
		
		"	
		IF ( 
			NOT EXISTS (
				SELECT * 
					FROM INFORMATION_SCHEMA.TABLES
					WHERE TABLE_NAME = '$table_name'					
				)			
			)		
		THEN
			CREATE TABLE $table_name (
				ID bigint(20) unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
				display_value smallint(3) unsigned NOT NULL,				
				state ENUM('UNUSED','LOCKED','BUYING','BOUGHT','BOUGHT_MANUALLY') DEFAULT 'UNUSED' NOT NULL,
				state_change_time bigint unsigned,
				user_id bigint(20) unsigned,
				FOREIGN KEY (user_id) REFERENCES wp_users(ID)				
			)$charset_collate;
			
			SET @counter =1;
							
			WHILE @counter <= 500 DO				
				INSERT INTO $table_name (display_value) VALUES (@counter);		
				SET @counter = @counter + 1;
			END WHILE ;
			
		END	IF
		"	
	);	
	
	/*
//
	AND TABLE_SCHEMA = 'testdb1'	not necessary to specify schema

*/
	
}

function lionslotto_create_results_table()
{
	global $wpdb;
	$charset_collate = $wpdb->get_charset_collate();
	$table_name = $wpdb->prefix . 'lotto_results';
	$wpdb->query(		
		"	
		CREATE TABLE IF NOT EXISTS $table_name (
			ID bigint(20) unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
			number_id bigint(20) unsigned,			
			creation_time bigint unsigned,
			month ENUM('JANUARY','FEBRUARY','MARCH','APRIL', 'MAY', 'JUNE', 'JULY', 'AUGUST', 'SEPTEMBER', 'OCTOBER', 'NOVEMBER', 'DECEMBER') DEFAULT 'JANUARY' NOT NULL,
			prize ENUM('FIRST','SECOND','THIRD') DEFAULT 'FIRST' NOT NULL,
			amount smallint unsigned,			
			FOREIGN KEY (number_id) REFERENCES wp_lotto_numbers(ID)
		)$charset_collate;
		"	
	);	
}

function lionslotto_create_stripe_purchases_table()
{
	global $wpdb;
	$charset_collate = $wpdb->get_charset_collate();
	$table_name = $wpdb->prefix . 'lotto_stripe_purchases';
	$wpdb->query(		
		"	
		CREATE TABLE IF NOT EXISTS $table_name (
			ID bigint(20) unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
			number_id bigint(20) unsigned,
		    user_id bigint(20) unsigned NOT NULL,
			purchase_time bigint unsigned NOT NULL,						
			session_id VARCHAR(128),
			state ENUM('STARTED','COMPLETE','CANCELLED','UNASSIGNED') DEFAULT 'STARTED' NOT NULL,
			FOREIGN KEY (number_id) REFERENCES wp_lotto_numbers(ID),
			FOREIGN KEY (user_id) REFERENCES wp_users(ID)
		)$charset_collate;
		"	
	);	
}


function lionslotto_create_manual_purchases_table()
{
	global $wpdb;
	$charset_collate = $wpdb->get_charset_collate();
			
	$table_name = $wpdb->prefix . 'lotto_manual_purchases';
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
			FOREIGN KEY (number_id) REFERENCES wp_lotto_numbers(ID),
			FOREIGN KEY (admin_id) REFERENCES wp_users(ID)
			)$charset_collate;
		"	
	);	
}


