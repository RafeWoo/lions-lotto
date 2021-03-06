<?php 
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
    add_settings_field( 'lionslotto_setting_stripe_secret_key', 'Stripe Secret Key', 'lionslotto_setting_stripe_secret_key', 'lionslotto_settings_section', 'stripe_settings' );
   
}

function lionslotto_validate_settings( $input ) {
    $newinput['stripe_secret_key'] = trim( $input['stripe_secret_key'] );
    if ( ! preg_match( '/^[A-Za-z0-9_]*$/i', $newinput['stripe_secret_key'] ) ) {
       $newinput['stripe_secret_key'] = 'invalid key';
    }
	else
	{
		//encrypt the key somehow maybe?
	}
	
	$newinput['stripe_public_key'] = trim( $input['stripe_public_key'] );
    if ( ! preg_match( '/^[A-Za-z0-9_]*$/i', $newinput['stripe_public_key'] ) ) {
        $newinput['stripe_public_key'] = 'invalid key';
    }
	
    return $newinput;
}

function lionslotto_section_stripe_text() {
    echo '<p>Here you can set all the options for using the Stripe API</p>';
}

function lionslotto_setting_stripe_secret_key() {
    $options = get_option( 'lionslotto_settings_fields' , array( 'stripe_secret_key' => "please enter stripe secret key") );	
	$key_value = esc_attr( $options['stripe_secret_key'] );	    
	if( $key_value != 'invalid key')
	{
		$key_value = '***********************************';
	}
	
    echo "<input size='140' id='lionslotto_setting_stripe_secret_key' name='lionslotto_settings_fields[stripe_secret_key]' type='text' value='$key_value' />";
}

function lionslotto_setting_stripe_public_key() {
    $options = get_option( 'lionslotto_settings_fields' , array( 'stripe_public_key' => "please enter stripe public key") );
	$key_value = esc_attr( $options['stripe_public_key'] );	    
    echo "<input size='140' id='lionslotto_setting_stripe_public_key' name='lionslotto_settings_fields[stripe_public_key]' type='text' value='$key_value' />";
}