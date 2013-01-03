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
$plugins->add_hook("member_register_coppa", "steam_redirect");
$plugins->add_hook("member_register_start", "steam_redirect");
$plugins->add_hook("member_login", "steam_redirect");
$plugins->add_hook("misc_start", "steam_return");

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
	global $db;
	if(!$db->field_exists('steam_id', 'users')) {

		// Create a column for the Steam 64bit ID.
		$db->query("ALTER TABLE mybb_users ADD steam_id VARCHAR(17) NOT NULL DEFAULT 0");

	} // close if(!$db->field_exists('steam_id', 'users'))
} // close function steamlogin_install


// The redirect to Steam authentication.
function steam_redirect()
{

	global $mybb;

    require_once MYBB_ROOT.'inc/class_lightopenid.php';

	$SteamOpenID = new LightOpenID();
	$SteamOpenID->returnUrl = $mybb->settings['bburl'].'/misc.php?action=steam_login';
    $SteamOpenID->__set('realm', $mybb->settings['bburl'].'/misc.php?action=steam_login');

    $SteamOpenID->identity = 'http://steamcommunity.com/openid';

    // Redirect directly to Steam.
    redirect($SteamOpenID->authUrl(), 'You are being redirect to Steam to authenticate your account for use on our website.', 'Login via Steam');
} // close function steam_redirect


// This is the function which will run upon return to your MyBB website.
function steam_return() {

    global $mybb, $db;
        
    if($mybb->input['action'] == 'steam_login')
    {

    	require_once MYBB_ROOT.'inc/class_steam.php';
        require_once MYBB_ROOT.'inc/class_lightopenid.php';
    	require_once MYBB_ROOT.'inc/functions.php';
    	require_once MYBB_ROOT.'inc/class_session.php';

    	$Steam = new Steam;
     
     	$SteamOpenID = new LightOpenID();   
        $SteamOpenID->validate();

        $ReturnExplode = explode('/', $SteamOpenID->identity);
        $SteamID = end($ReturnExplode);
        $SteamInfo = $Steam->GetUserInfo($SteamID);


        $personaname = $SteamInfo['response']['players'][0]['personaname'];
        $avatarfull = $SteamInfo['response']['players'][0]['avatarfull'];

        // Perform a check to see if the user already exists in the database.
        $user_check = $db->num_rows($db->simple_select("users", "*", "steam_id = '$SteamID'"));

        if($user_check == 0) 
        {

        	// User doesn't exist, so create a new record for them.
			$md5password = md5($SteamID.rand(124748237487, 324748237487));

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
	        	'email' => $SteamID.'@manncotrading.com',
	        	'avatar' => $avatarfull, 
	        	'usergroup' => 2,
	        	'regdate' => time(),
	        	'steam_id' => $SteamID
	    	);

	        $db->insert_query('users', $insert);
	        update_stats(array('numusers' => '+1'));

	    } else { // close if($user_check == 0)

    		// Keep the persona of the user up to date.
	    	$update = array('username' => $personaname);
    		$db->update_query('users', $update, "steam_id = '$SteamID'");

	    } // close else

	    $user = $db->fetch_array($db->simple_select("users", "*", "steam_id = '$SteamID'"));

	    // Login the user.
		my_setcookie("mybbuser", $user['uid']."_".$user['loginkey'], $remember, true);
		my_setcookie("sid", $session->sid, -1, true);

		redirect("index.php", 'Your account has been autheticated and you have been logged in.', 'Login via Steam');

	} // close if($mybb->input['action'] == 'steam_login')

} // close function steam_return

?>