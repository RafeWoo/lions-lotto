<?php


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