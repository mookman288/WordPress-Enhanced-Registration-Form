<?php

/*
 * Plugin Name: Enhanced User Registration
 * Description: Adds a Custom Registration Form to your WordPress website with a simple shortcode. Use the shortcode [cr] to add
 * the registration form to a page, post or widget area.
 * Version: 1.0
 * Author: Chad Warford - Fullstack Developer
 * Author URI: http://digitaldesigneronline.com
 */
session_start();
$_SESSION['user_login'] = $_POST['user_login'];
$_SESSION['first_name'] = $_POST['first_name'];
$_SESSION['last_name'] = $_POST['last_name'];
function cr(&$fields, &$errors) {
	
	// Check args and replace if necessary
	if (! is_array($fields)) $fields = array();
	if (! is_wp_error($errors)) $errors = new WP_Error();
	
	// If this is an activation.
	if (isset($_GET['activate'])) {
		//Get the fields.
		$fields	=	(!isset($_GET['fields'])) ? null : $_GET['fields'];
		
		if (!$fields) {
			$errors -> add('field', 'Your activation link is malformed. Please try registering again.');
		} else {
			//Decode the fields.
			$fields	=	unserialize(base64_decode($fields));
			
			//Loop through each field.
			foreach($fields as $key => $field) {
				$fields[$key]	=	filter_var($field, FILTER_SANITIZE_STRING); 
			}
			
			//If there are missing or malformed fields. 
			if (!$fields['user_login'] || !$fields['user_email'] && $fields['timestamp'] && time() < $fields['timestamp']) {
				$errors -> add('field', 'There is missing data in your activation link. Please try registering again.');
			} else {
				//Set the user registered date.
				$fields['user_registered']	=	date('Y-m-d H:i:s', $fields['timestamp']); 
				$fields['user_pass']		=	wp_generate_password();
				
				//Create a new user. 
				$user_id	=	wp_insert_user($fields);
				
				if ($user_id && !is_wp_error($user_id)) {
					//Send the new user the welcome email
					wp_new_user_notification($fields, wp_login_url(), $fields['user_pass']);
					
					echo '<style type="text/css">.step1-form, .step-2-form{display:none !important;visibility:hidden !important;}.step2-form-msg{display:block !important;visibility:visible !important;}</style>';
					echo nl2br(__("Your account has been created. Your login information and a link to login has been sent to your registered email."));
				}
			}
		}
		
	// Check for form submit
	} elseif (isset($_POST['submit'])) {
		
		// Get fields from submitted form
		$fields = cr_get_fields();
		
		// Validate fields and produce errors
		if (cr_validate($fields, $errors)) {
			
			// If successful
			$code = hash('sha256', $fields['user_login'] . $fields['user_email']);
			
			$activation_link = add_query_arg(array(
				'activate' => $code,
				'fields' => base64_encode(serialize(array(
					'user_login' => $fields['user_login'],
					'user_email' => $fields['user_email'],
					'first_name' => $fields['first_name'],
					'last_name' => $fields['last_name'], 
					'timestamp' => time()
				))) 
			), get_permalink());
			
			// Send the new user the welcome email
			wp_new_user_notification($fields, $activation_link);
			
			echo '<style type="text/css">.step1-form, .step-2-form{display:none !important;visibility:hidden !important;}.step2-form-msg{display:block !important;visibility:visible !important;}</style>';
			echo nl2br(__("Your account needs to be activated before you can continue. An activation link has been sent to the email address you entered.\n Note that you must activate the account by clicking on the activation link when you get the email before you can login."));
		}
		
		// Clear field data
		$fields = array();
	}

	// Santitize fields
	cr_sanitize($fields);
	
	// Generate form
	cr_display_form($fields, $errors);
}

function cr_sanitize(&$fields) {
	$fields['user_login']	 =	isset($fields['user_login'])	? sanitize_user($fields['user_login']) : '';
	$fields['first_name']	 =	isset($fields['first_name'])	? sanitize_text_field($fields['first_name']) : '';
	$fields['last_name']		=	isset($fields['last_name'])	 ? sanitize_text_field($fields['last_name']) : '';
	$fields['user_email']	 =	isset($fields['user_email'])	? sanitize_email($fields['user_email']) : '';
	$fields['email_confirm']	 =	isset($fields['email_confirm'])	? sanitize_email($fields['email_confirm']) : '';
}

function cr_display_form($fields = array(), $errors = null) {
	
	// Check for wp error obj and see if it has any errors	
	if (is_wp_error($errors) && count($errors->get_error_messages()) > 0) {
		
		// Display errors
		?>
<div class="step2-form-msg"
	style="display: block; margin: auto; text-align: left; max-width: 1080px;">
	<ul><?php
		foreach ($errors->get_error_messages() as $key => $val) {
			?><li>
				<?php echo $val; ?>
			</li><?php
		}
		?></ul><?php
	}
	
	// Display form
	?>
	</div>
<div class="step2-form"
	style="margin: auto; text-align: left; max-width: 1080px;">
	<h1 class="til-postheader entry-title">User Registration: Step 2 of 2</h1>
	<strong><span class="step2">1 - IBEW Membership Check</span> &gt; 2 -
		User Registration</strong>
	<form action="<?php $_SERVER['REQUEST_URI'] ?>" method="post">
		<div>
			<label for="user_login">Card Number <strong>*</strong></label><br> <input
				type="text" name="user_login"
				value="<?php echo (isset($fields['user_login']) ? $fields['user_login'] : '') ?>"
				>
		</div>
		<br>
		<div>
			<label for="firstname">First Name <strong>*</strong></label><br> <input
				type="text" name="first_name"
				value="<?php echo (isset($fields['first_name']) ? $fields['first_name'] : '') ?>"
				>
		</div>
		<br>
		<div>
			<label for="lastname">Last Name <strong>*</strong></label><br> <input
				type="text" name="last_name"
				value="<?php echo (isset($fields['last_name']) ? $fields['last_name'] : '') ?>"
				>
		</div>
		<br>
		<div>
			<label for="email">Email <strong>*</strong></label><br> <input
				type="text" name="user_email"
				value="<?php echo (isset($fields['user_email']) ? $fields['user_email'] : '') ?>">
		</div>
		<div>
			<label for="confirm-email">Confirm Email <strong>*</strong></label><br>
			<input type="text" name="email_confirm"
				value="<?php echo (isset($fields['email_confirm']) ? $fields['email_confirm'] : '') ?>">
		</div>
		<br> <br> <input type="submit" name="submit" value="Register">
	</form>
</div>
<?php
}

function cr_get_fields() {
	return array(
		'user_login'	 =>	isset($_POST['user_login'])	 ?	$_POST['user_login']	 :	'',
		'first_name'	 =>	isset($_POST['first_name'])	 ?	$_POST['first_name']				:	'',
		'last_name'		=>	isset($_POST['last_name'])		?	$_POST['last_name']				:	'',
		'user_email'	 =>	isset($_POST['user_email'])	 ?	$_POST['user_email']				:	'',
		'email_confirm'	 =>	isset($_POST['email_confirm'])	 ?	$_POST['email_confirm']				:	'',
	);
}

function cr_validate(&$fields, &$errors) {
	
	// Make sure there is a proper wp error obj
	// If not, make one
	if (!is_wp_error($errors))	$errors = new WP_Error;
	
	// Validate form data
	
	if (empty($fields['user_login']) || empty($fields['first_name']) || empty($fields['last_name']) || empty($fields['user_email']) || empty($fields['email_confirm'])) {
		$errors->add('field', 'Please complete the registration form.');
	}

	if (username_exists($fields['user_login'])) {
		$errors->add('user_name', 'Sorry, that Card Number has already been registered.');
	}

	if (!is_email($fields['user_email'])) {
		$errors->add('email_invalid', 'Please verify the email address prior to submitting.');
	}

	if (!is_email($fields['email_confirm'])) {
		$errors->add('email_confirm_invalid', 'Please confirm the email address prior to submitting.');
	}
	
	if (($fields['user_email'].value) != ($fields['email_confirm'].value)) {
		$errors->add('email_mismatch', 'Those emails don\'t match!');
	}

	if (email_exists($fields['user_email'])) {
		$errors->add('email_taken', 'Please use another email address as it appears that one is already in use.');
	}
	
	// If errors were produced, fail
	if (count($errors->get_error_messages()) > 0) {
		return false;
	}
	
	// Else, success!
	return true;
}

// Redefine user notification function
if ( !function_exists('wp_new_user_notification') ) {
		function wp_new_user_notification($fields, $link, $password = false) {
			if (!$password) {
				$message = sprintf(__('Hello %s,'), $fields['first_name']) . "\n";
				$message .= sprintf(__("Thank you for registering at %s."), get_option('blogname')) . "\n";
				$message .= sprintf(__('You must confirm your email address before your account will be created.')) . "\n";
				$message .= sprintf(__('To activate the account click on the following link or copy-paste it in your browser:')) . "\n";
				$message .= $link . "\n";
				$message .= sprintf(__('After activation you will receive a password and a link to login.')) . "\n";
				$message .= sprintf(__('If you have any problems, please contact me at %s.'), get_option('admin_email')) . "\n";
				$message .= sprintf(__('Best Regards, %s'), get_option('blogname'));
				
				wp_mail($fields['user_email'], sprintf(__('[%s] Member Registration Email Verification'), get_option('blogname')), $message);
			} else {
				$message	= sprintf(__('New user registration on %s:'), get_option('blogname')) . "\n";
				$message .= sprintf(__('Username: %s'), $fields['user_login']) . "\n";
				$message .= sprintf(__('First Name: %s'), $fields['first_name']) . "\n";
				$message .= sprintf(__('Last Name: %s'), $fields['last_name']) . "\n";
				$message .= sprintf(__('E-mail: %s'), $fields['user_email']) . "\n";
				
				@wp_mail(get_option('admin_email'), sprintf(__('[%s] New User Registration'), get_option('blogname')), $message);
				
				$message	= sprintf(__('Hello %s,'), $fields['first_name']) . "\n";
				$message .= sprintf(__("You have successfully registered at %s."), get_option('blogname')) . "\n";
				$message .= sprintf(__('Your account is created and is ready to go. To login, click on the following link or copy-paste it in your browser:')) . "\n";
				$message .= $link . "\n";
				$message .= sprintf(__('Here are your Login Credentials:')) . "\n";
				$message .= sprintf(__('Username: %s'), $fields['user_login']) . "\n";
				$message .= sprintf(__('Password: %s'), $password) . "\n";
				$message .= sprintf(__('If you have any problems, please contact me at %s.'), get_option('admin_email')) . "\n";
				$message .= sprintf(__('Best Regards, %s'), get_option('blogname'));
				
				wp_mail($fields['user_email'], sprintf(__('[%s] Member Registration Complete'), get_option('blogname')), $message);
			}
		}
}
// The callback function for the [cr] shortcode
function cr_cb() {
	$fields = array();
	$errors = new WP_Error();
	
	// Buffer output
	ob_start();
	
	// Custom registration, go!
	cr($fields, $errors);
	
	// Return buffer
	return ob_get_clean();
}
add_shortcode('cr', 'cr_cb');
