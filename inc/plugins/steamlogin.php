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


/**
 *
 * Plugin Info - steamlogin_info
 * - - - - - - - - - - - - - - -
 * @desc The information to show in the MyBB Administration Dashboard.
 * @since 1.0
 * @version 1.3
 *
 */
function steamlogin_info()
{

	return array(
		"name"			=> "Steam Login",
		"description"	=> "Allows the registration of accounts through Steam. (For support/issues please visit https://github.com/stewartiee/Steam-OpenID--MyBB-)",
		"website"		=> "http://www.calculator.tf",
		"author"		=> "Ryan Stewart",
		"authorsite"	=> "http://www.calculator.tf",
		"version"		=> "1.3",
		"guid" 			=> "",
		"compatibility" => "*"
	);

} // close function steamlogin_info


/**
 *
 * Plugin Activate - steamlogin_activate
 * - - - - - - - - - - - - - - -
 * @since 1.0
 * @version 1.3
 *
 */
function steamlogin_activate()
{
	global $db, $mybb, $templates;

    $steamlogin_settings = array(
        "name" => "steamlogin",
        "title" => "Steam Login - Settings",
        "description" => "Modify the settings of the Steam Login plugin.",
        "disporder" => "0",
        "isdefault" => "no",
    );
    
    // Create our Setting group in the database.
    $db->insert_query("settinggroups", $steamlogin_settings);
    
    // Our new Setting group ID.
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

    $steamlogin_update_username_setting = array(
        "name" => "steamlogin_update_username",
        "title" => "Update Username",
        "description" => "Should the plugin be allowed to update the username of the user on each login? (If a user changes their name on Steam, this will update here too.)",
        "optionscode" => "yesno",
        "value" => "no",
        "disporder" => 2,
        "gid" => $gid
    );

    $steamlogin_update_avatar_setting = array(
        "name" => "steamlogin_update_avatar",
        "title" => "Update Avatar",
        "description" => "Should the plugin be allowed to update the avatar of the user to that of their Steam account?",
        "optionscode" => "yesno",
        "value" => "yes",
        "disporder" => 3,
        "gid" => $gid
    );

    // Insert our Settings.
    $db->insert_query("settings", $steamlogin_api_key_setting);
    $db->insert_query("settings", $steamlogin_update_username_setting);
    $db->insert_query("settings", $steamlogin_update_avatar_setting);

    // Rebuild our settings to show our new category.
    rebuildsettings();


    /**
     * Template Edits
     * - - - - - - - - - - - - - - -
     * Template edits required by the plugin.
     */
    require_once MYBB_ROOT . 'inc/adminfunctions_templates.php';

    // Add a Login button to the "Welcome Block"/
	find_replace_templatesets('header_welcomeblock_guest', '#' . preg_quote('{$lang->welcome_register}</a>') . '#i', '{$lang->welcome_register}</a> &mdash; <a href="{$mybb->settings[\'bburl\']}/misc.php?action=steam_login"><img border="0" src="inc/plugins/steamlogin/steam_login_btn.png" alt="Login through Steam" style="vertical-align:middle"></a>');

    // This is released as Open Source. Although this notice isn't required to be kept, i'd appreciate if you could show your support by keeping it here.
    find_replace_templatesets('footer', '#' . preg_quote('<!-- End powered by -->') . '#i', 'Steam Login provided by <a href="http://www.calculator.tf">www.calculator.tf</a><br>Powered by <a href="http://www.steampowered.com">Steam</a>.<!-- End powered by -->');

} // close function steamlogin_activate


/**
 *
 * Plugin Deactivate - steamlogin_deactivate
 * - - - - - - - - - - - - - - -
 * @since 1.0
 * @version 1.2
 *
 */
function steamlogin_deactivate()
{

	global $db;

    // Delete our Setting groups.
    $db->delete_query("settings","name LIKE 'steamlogin_%'");
    $db->delete_query("settinggroups","name = 'steamlogin'");

    /**
     * Template Edits
     * - - - - - - - - - - - - - - -
     * Revert any template edits made during install.
     */
    require_once MYBB_ROOT . 'inc/adminfunctions_templates.php';

    find_replace_templatesets('header_welcomeblock_guest', '#' . preg_quote('&mdash; <a href="{$mybb->settings[\'bburl\']}/misc.php?action=steam_login"><img border="0" src="inc/plugins/steamlogin/steam_login_btn.png" alt="Login through Steam" style="vertical-align:middle"></a>') . '#i', '');
    find_replace_templatesets('footer', '#' . preg_quote('Steam Login provided by <a href="http://www.calculator.tf">www.calculator.tf</a><br>Powered by <a href="http://www.steampowered.com">Steam</a>.') . '#i', '');

} // close function steamlogin_deactivate



/**
 *
 * Steam Redirect - steam_redirect
 * - - - - - - - - - - - - - - -
 * @desc Redirects the browser to Steam OpenID website for login.
 * @since 1.0
 * @version 1.0
 *
 */
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


/**
 *
 * Redirect Output - steam_output_to_misc
 * - - - - - - - - - - - - - - -
 * @desc This function is holds the actions issued by the Steam Login plugin.
 * @since 1.0
 * @version 1.3
 *
 */
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
        $check_update_username = $db->fetch_array($db->simple_select("settings", "name, value", "name = 'steamlogin_update_username'"));
        $check_update_avatar = $db->fetch_array($db->simple_select("settings", "name, value", "name = 'steamlogin_update_avatar'"));

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
	        	
			$personaname = strip_tags($personaname);//This is so people can not use tags that display.
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

                    $update = array(); // Init our update array.

                    // Do our checks for both username and avatar.
                    if($check_update_username['value'] == 1) $update['username'] = $personaname;
                    if($check_update_avatar['value'] == 1) $update['avatar'] = $avatar;

                    // Run our update query if the array isn't empty.
                    if(!empty($update)) $db->update_query('users', $update, "loginname = '$steamid'");

			    } // close else

			    $user = $db->fetch_array($db->simple_select("users", "*", "loginname = '$steamid'"));

			    // Login the user.
				my_setcookie("mybbuser", $user['uid']."_".$user['loginkey'], $remember, true);
				my_setcookie("sid", $session->sid, -1, true);

				redirect("index.php", 'Your account has been authenticated and you have been logged in.', 'Login via Steam');

			} // close if($steam_info['status'] == 'success')

		} // close else

	} // close if($mybb->input['action'] == 'steam_login')

} // close function steam_return

?>
