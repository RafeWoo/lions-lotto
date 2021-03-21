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

function lionslotto_create_numbers_table()
{
	global $wpdb;
	$charset_collate = $wpdb->get_charset_collate();
	$table_name = $wpdb->prefix . 'lotto_numbers';
	$wpdb->query(
		
		"	
		IF ( NOT EXISTS (SELECT * 
            FROM INFORMATION_SCHEMA.TABLES  
			WHERE TABLE_SCHEMA = 'testdb1'
            AND  TABLE_NAME = '$table_name'))		
		THEN
			CREATE TABLE $table_name (
				ID bigint(20) unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
				display_value tinyint(3) unsigned NOT NULL,				
				state ENUM('UNUSED','LOCKED','BUYING','BOUGHT') DEFAULT 'UNUSED' NOT NULL
			)$charset_collate;
			
			SET @counter =1;
							
			WHILE @counter <= 500 DO				
				INSERT INTO $table_name (display_value, state) VALUES (@counter, 'UNUSED');		
				SET @counter = @counter + 1;
			END WHILE ;
			
		END	IF
		"
	
	);
	
}


/*

DECLARE counter INT DEFAULT 1;
			 
			
			
		
	
				SET @num = @num + 1;
			END				
		BEGIN
	DECLARE @num INT=1;
			WHILE @num <= 500
			BEGIN
			  	INSERT INTO $table_name (state)
				VALUES ('UNUSED')
				SET @num = @num + 1;
			END
*/