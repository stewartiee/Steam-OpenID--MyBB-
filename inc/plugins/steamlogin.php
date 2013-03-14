<?php 
/**
 * Steam Login
 * ----------------------------------
 * Provided with no warranties by Ryan Stewart (www.calculator.tf)
 * This has been tested on MyBB 1.6
 */
 
// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB")) die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");

// Add to our hooks.
$plugins->add_hook("misc_start", "steam_output_to_misc");

// Information about the plugin.
function steamlogin_info()
{
	return array(
		"name"			=> "Steam Login",
		"description"	=> "Allows the registration of accounts through Steam.",
		"website"		=> "http://www.calculator.tf",
		"author"		=> "Ryan Stewart",
		"authorsite"	=> "http://www.calculator.tf",
		"version"		=> "1.0",
		"guid" 			=> "",
		"compatibility" => "*"
	);
}

// The queries to be run when the plugin is activated.
function steamlogin_activate()
{
	global $db, $mybb, $templates;

	if(!$db->field_exists('steam_id', 'users')) {

		// Create a column for the Steam 64bit ID.
		$db->query("ALTER TABLE ".TABLE_PREFIX."users ADD steam_id VARCHAR(17) NOT NULL DEFAULT 0");
		$db->query("ALTER TABLE ".TABLE_PREFIX."users ADD steam_persona VARCHAR(250) NOT NULL DEFAULT 0");

	} // close if(!$db->field_exists('steam_id', 'users'))

    // create a setting group to house our setting
    $steamlogin_settings = array(
        "name"            => "steamlogin",
        "title"         => "Steam Login - Settings",
        "description"    => "Modify the settings of the Steam Login plugin.",
        "disporder"        => "0",
        "isdefault"        => "no",
    );
    
    // insert the setting group into the database
    $db->insert_query("settinggroups", $steamlogin_settings);
    
    // grab insert ID of the setting group
    $gid = intval($db->insert_id());
    
    $steamlogin_api_key_setting = array(
        "name" => "steamlogin_api_key",
        "title" => "Steam API Key",
        "description" => "You can get an API key by going to the following website: http://steamcommunity.com/dev/apikey",
        "optionscode" => "text",
        "value" => "",
        "disporder" => 1,
        "gid" => $gid
    );
    
    $db->insert_query("settings", $steamlogin_api_key_setting);
    rebuildsettings();

    $steamlogin_button = '<a href="'.$mybb->settings['bburl'].'/misc.php?action=steam_login"><img border="0" src="'.$mybb->settings['bburl'].'/inc/plugins/steamlogin/img/steam_login_btn.png" alt="Login through Steam" /></a>';
   
	require MYBB_ROOT.'/inc/adminfunctions_templates.php';
	find_replace_templatesets(
		"header_welcomeblock_guest",
		'#'.preg_quote('{$lang->welcome_guest}').'#',
		'{$lang->welcome_guest}'.$steamlogin_button
	);		


} // close function steamlogin_activate

function steamlogin_deactivate()
{
	global $db;

    $db->delete_query("settings","name LIKE 'steamlogin_%'");
    $db->delete_query("settinggroups","name = 'steamlogin'");
}

// The outputs the actions used by the plugin for login and return.
function steam_output_to_misc() {

    global $mybb, $db;
        
    if($mybb->input['action'] == 'steam_login')
    {

    	$get_key = $db->fetch_array($db->simple_select("settings", "name, value", "name = 'steamlogin_api_key'"));

    	if($get_key['value'] == null) {

    		echo "The Steam Login plugin hasn't been configured correctly.";

    	} else {

		    require_once MYBB_ROOT.'inc/class_lightopenid.php';

			$SteamOpenID = new LightOpenID();
			$SteamOpenID->returnUrl = $mybb->settings['bburl'].'/misc.php?action=steam_return';
		    $SteamOpenID->__set('realm', $mybb->settings['bburl'].'/misc.php?action=steam_return');

		    $SteamOpenID->identity = 'http://steamcommunity.com/openid';

		    // Redirect directly to Steam.
		    redirect($SteamOpenID->authUrl(), 'You are being redirect to Steam to authenticate your account for use on our website.', 'Login via Steam');

		}
    }

    if($mybb->input['action'] == 'steam_return')
    {
    	$get_key = $db->fetch_array($db->simple_select("settings", "name, value", "name = 'steamlogin_api_key'"));

    	if($get_key['value'] == null) {

    		echo "The Steam Login plugin hasn't been configured correctly.";

    	} else {

	    	require_once MYBB_ROOT.'inc/class_steam.php';
	        require_once MYBB_ROOT.'inc/class_lightopenid.php';
	    	require_once MYBB_ROOT.'inc/functions.php';
	    	require_once MYBB_ROOT.'inc/class_session.php';

	    	$steam = new steam;
	     
	     	$steam_open_id = new LightOpenID();   
	        $steam_open_id->validate();

	        $return_explode = explode('/', $steam_open_id->identity);
	        $steamid = end($return_explode);

	        $steam_info = $steam->get_user_info($steamid);

	        // Check the status.
	        if($steam_info['status'] == 'success')
	        {
	        	$steamid = $steam_info['steamid'];
	        	$personaname = $steam_info['personaname'];
	        	$profileurl = $steam_info['profileurl'];
	        	$avatar = $steam_info['avatar'];

	        	$personaname = $db->escape_string($personaname);


		        // Perform a check to see if the user already exists in the database.
		        $user_check = $db->num_rows($db->simple_select("users", "*", "loginname = '$steamid'"));

		        if($user_check == 0) 
		        {

		        	// User doesn't exist, so create a new record for them.
					$md5password = md5($steamid.rand(124748237487, 324748237487));

					// Generate our salt
					$salt = random_str(8);

					// Combine the password and salt
					$saltedpw = md5(md5($salt).$md5password);

					// Generate the user login key
					$loginkey = random_str(50);

			        $insert = array(
			        	'username' => $personaname, 
			        	'password' => $saltedpw,
			        	'salt' => $salt,
			        	'loginkey' => $loginkey,
			        	'email' => '',
			        	'avatar' => $avatar, 
			        	'usergroup' => 2,
			        	'regdate' => time(),
			        	'website' => $profileurl,
			        	'steam_id' => $steamid,
			        	'loginname' => $steamid
			    	);

			        $db->insert_query('users', $insert);
			        update_stats(array('numusers' => '+1','lastusername' => $personaname));

			    } else { // close if($user_check == 0)

		    		// Keep the persona of the user up to date.
			    	$update = array('username' => $personaname, 'avatar' => $avatar);
		    		$db->update_query('users', $update, "loginname = '$steamid'");

			    } // close else

			    $user = $db->fetch_array($db->simple_select("users", "*", "steam_id = '$steamid'"));

			    // Login the user.
				my_setcookie("mybbuser", $user['uid']."_".$user['loginkey'], $remember, true);
				my_setcookie("sid", $session->sid, -1, true);

				redirect("index.php", 'Your account has been autheticated and you have been logged in.', 'Login via Steam');
			}

		}

	} // close if($mybb->input['action'] == 'steam_login')

} // close function steam_return

?>