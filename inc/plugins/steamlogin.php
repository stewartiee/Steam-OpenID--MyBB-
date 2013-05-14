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
$plugins->add_hook("member_login", "steam_redirect");
$plugins->add_hook("member_register_start", "steam_redirect");
$plugins->add_hook("no_permission", "steam_redirect", "newreply.php");
$plugins->add_hook("no_permission", "steam_redirect", "newthread.php");

// Information about the Steam Login plugin.
function steamlogin_info()
{
	return array(
		"name"			=> "Steam Login",
		"description"	=> "Allows the registration of accounts through Steam.",
		"website"		=> "http://www.calculator.tf",
		"author"		=> "Ryan Stewart",
		"authorsite"	=> "http://www.calculator.tf",
		"version"		=> "1.2",
		"guid" 			=> "",
		"compatibility" => "*"
	);
}


// The queries to be run when the plugin is activated.
function steamlogin_activate()
{
	global $db, $mybb, $templates;

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

	require_once MYBB_ROOT . 'inc/adminfunctions_templates.php';

	find_replace_templatesets('header_welcomeblock_guest', '#' . preg_quote('{$lang->welcome_register}</a>') . '#i', '{$lang->welcome_register}</a> &mdash; <a href="{$mybb->settings[\'bburl\']}/misc.php?action=steam_login"><img border="0" src="inc/plugins/steamlogin/steam_login_btn.png" alt="Login through Steam" style="vertical-align:middle"></a>');
	find_replace_templatesets('footer', '#' . preg_quote('<!-- End powered by -->') . '#i', 'Steam Login provided by <a href="http://www.calculator.tf">www.calculator.tf</a><br>Powered by <a href="http://www.steampowered.com">Steam</a>.<!-- End powered by -->');

} // close function steamlogin_activate


// Code to run when the plugin is deactivated.
function steamlogin_deactivate()
{

	global $db;

    $db->delete_query("settings","name LIKE 'steamlogin_%'");
    $db->delete_query("settinggroups","name = 'steamlogin'");

	require_once MYBB_ROOT . 'inc/adminfunctions_templates.php';

    find_replace_templatesets('header_welcomeblock_guest', '#' . preg_quote('&mdash; <a href="{$mybb->settings[\'bburl\']}/misc.php?action=steam_login"><img border="0" src="inc/plugins/steamlogin/steam_login_btn.png" alt="Login through Steam" style="vertical-align:middle"></a>') . '#i', '');
    find_replace_templatesets('footer', '#' . preg_quote('Steam Login provided by <a href="http://www.calculator.tf">www.calculator.tf</a><br>Powered by <a href="http://www.steampowered.com">Steam</a>.') . '#i', '');

} // close function steamlogin_deactivate



// The standard redirect function for redirecting the browser to Steam community.
function steam_redirect() 
{

	global $mybb, $db;

	// Check if the user is logged in or not.
	if($mybb->user['uid'] == 0) {

		// Get the Steam API key set in settings.
		$get_key = $db->fetch_array($db->simple_select("settings", "name, value", "name = 'steamlogin_api_key'"));

		if($get_key['value'] == null) {

			// The Steam API key hasn't been set, so stop the script and output error message.
			echo "The Steam Login plugin hasn't been configured correctly.";

		} else { // close if($get_key['value'] == null)

			//Set options for the OpenID library.
		    require_once MYBB_ROOT.'inc/class_lightopenid.php';

			$SteamOpenID = new LightOpenID();
			$SteamOpenID->returnUrl = $mybb->settings['bburl'].'/misc.php?action=steam_return';
		    $SteamOpenID->__set('realm', $mybb->settings['bburl'].'/misc.php?action=steam_return');

		    $SteamOpenID->identity = 'http://steamcommunity.com/openid';

		    // Redirect directly to Steam.
		    redirect($SteamOpenID->authUrl(), 'You are being redirect to Steam to authenticate your account for use on our website.', 'Login via Steam');

		} // close else

	} // close if($mybb->user['uid'] == 0)

} // close function steam_redirect


// The outputs the actions used by the plugin for login and return.
function steam_output_to_misc() {

    global $mybb, $db, $session;
        
    // The standard action to redirect the user to Steam community.
    if($mybb->input['action'] == 'steam_login')
    {

		steam_redirect();

    } // close if($mybb->input['action'] == 'steam_login')

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

		        	$password = random_str(8);
		        	$email = $steamid.'@steamcommunity.com';
		        	$default_usergroup = 2; // On a standard MyBB installation this is the group: Registered

					require_once MYBB_ROOT . "inc/datahandlers/user.php";
					$userhandler = new UserDataHandler("insert");

					$new_user_data = array(
						"username" => $personaname,
						"password" => $password,
						"password2" => $password,
						"email" => $email,
						"email2" => $email,
						"avatar" => $avatar,
						"usergroup" => $default_usergroup,
						"displaygroup" => $default_usergroup,
						"website" => $profileurl,
						"regip" => $session->ipaddress,
						"longregip" => my_ip2long($session->ipaddress),
						"loginname" => $steamid
					);

					$userhandler->set_data($new_user_data);

					if ($userhandler->validate_user()) {

						$user_info = $userhandler->insert_user();

					} // close if ($userhandler->validate_user())


			    } else { // close if($user_check == 0)

		    		// Keep the persona of the user up to date.
			    	$update = array('username' => $personaname, 'avatar' => $avatar);
		    		$db->update_query('users', $update, "loginname = '$steamid'");

			    } // close else

			    $user = $db->fetch_array($db->simple_select("users", "*", "loginname = '$steamid'"));

			    // Login the user.
				my_setcookie("mybbuser", $user['uid']."_".$user['loginkey'], $remember, true);
				my_setcookie("sid", $session->sid, -1, true);

				redirect("index.php", 'Your account has been autheticated and you have been logged in.', 'Login via Steam');

			} // close if($steam_info['status'] == 'success')

		} // close else

	} // close if($mybb->input['action'] == 'steam_login')

} // close function steam_return

?>