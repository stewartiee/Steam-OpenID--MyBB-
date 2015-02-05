<?php
/**
 * Steam Login
 * - - - - - -
 * Enables the ability to login through Steam to your forum.
 * Coded by Ryan Stewart
 * Find the source on GitHub
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB")) die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");

// Add all the hooks required by the plugin.
$plugins->add_hook('misc_start', 'steam_link');
$plugins->add_hook('member_do_register_end', 'complete_steam_link_register');
$plugins->add_hook('postbit', 'add_to_postbit');
$plugins->add_hook('usercp_start', 'steam_link_page');

// Create our template edits required.
$usercp_nav_link_edit = '<tr><td class="trow1 smalltext"><a href="usercp.php?action=steam_link" class="usercp_nav_item" style="background-image: url(inc/plugins/steamlogin/img/steam_icon.png);background-position:0;background-repeat:no-repeat;">Link to Steam Account</a></td></tr>';
$postbit_steam_id_edit = '<br />Steam ID: {steam_linked}';
$welcomeblock_edit = '<a href="{$mybb->settings[\'bburl\']}/misc.php?action=steam_link"><img border="0" src="inc/plugins/steamlogin/img/steam_wide.png" alt="Login with Steam"></a>';


function steamlogin_info()
{
    return array(
        "name"			=> "Steam Login",
        "description"	=> "Enables the ability to login through Steam to your forum.",
        "website"		=> "http://www.mybb.com",
        "author"		=> "Ryan Stewart",
        "authorsite"	=> "https://github.com/stewartiee/Steam-OpenID--MyBB-",
        "version"		=> "2.0",
        "guid" 			=> "",
        "compatibility" => "*"
    );
} // close function steamlogin_info


function steamlogin_activate()
{

    global $db, $lang, $usercp_nav_link_edit, $postbit_steam_id_edit, $welcomeblock_edit;

    // Create a Settings group for the plugin.
    $settings_group = array(
        'name' => 'steamlogin',
        'title' => 'Steam Login',
        'description' => 'Manage the settings for Steam Login.',
        'disporder' => 0,
        'isdefault' => 'no'
    );
    $db->insert_query('settinggroups', $settings_group);

    $gid = intval($db->insert_id());

    // Create a new setting for API key.
    $steamlogin_api_key_setting = array(
        array(
            'name' => 'steamlogin_api_key',
            'title' => 'Steam API Key',
            'description' => 'You can get an API key by going to the following website: http://steamcommunity.com/dev/apikey',
            'optionscode' => 'text',
            'value' => '',
            'disporder' => 1,
            'gid' => $gid
        )
    );
    $db->insert_query_multiple('settings', $steamlogin_api_key_setting);

    rebuild_settings();

    // Create query to add ID column for Steam ID.
    $db->add_column('users', 'steam_id', 'BIGINT(17) NOT NULL DEFAULT 0');

    // The template to show the user that's linked.
    $steam_profile_link_template = '<img border="0" src="{$steam_info[\'avatar\']}" alt="{$steam_info[\'personaname\']}\'s Avatar" style="width:16px;height:16px;vertical-align:middle"> <a href="http://www.steamcommunity.com/profiles/{$post[\'steam_id\']}" target="_blank" title="Click this link to view their Steam Profile">{$steam_info[\'personaname\']}</a>';

    $steam_profile_link_insert_array = array(
        'title' => 'steam_profile_link',
        'template' => $db->escape_string($steam_profile_link_template),
        'sid' => '-1',
        'version' => '',
        'dateline' => time()
    );
    $db->insert_query('templates', $steam_profile_link_insert_array);

    // The template for our User CP page.
    $steam_link_usercp_template = '<html>
<head>
<title>{$mybb->settings[\'bbname\']} - {$lang->steam_usercp_heading}</title>
{$headerinclude}
</head>
<body>
{$header}
<table width="100%" border="0" align="center">
<tr>
	{$usercpnav}
	<td valign="top">
		<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
			<tr>
				<td class="thead"><strong>{$lang->steam_usercp_heading}</strong></td>
			</tr>
          	<tr>
            	<td width="100%" class="tcat"><span class="smalltext"><strong>Overview</strong></span></td>
          	</tr>
          	<tr>
            	<td class="trow1" valign="top">
              		<p>{$lang->steam_overview_message}</p>
                  {$link_status}
            	</td>
          	</tr>
		</table>
	</td>
</tr>
</table>
</form>
{$footer}
</body>
</html>';

    $steam_link_usercp_insert_array = array(
        'title' => 'steam_link_usercp',
        'template' => $db->escape_string($steam_link_usercp_template),
        'sid' => '-1',
        'version' => '',
        'dateline' => time()
    );
    $db->insert_query('templates', $steam_link_usercp_insert_array);

    require_once MYBB_ROOT.'/inc/adminfunctions_templates.php';

    // Add a link to our new User CP page.
    find_replace_templatesets(
        'usercp_nav_misc',
        '#' . preg_quote('id="usercpmisc_e">') . '#i',
        'id="usercpmisc_e">' . $usercp_nav_link_edit
    );

    // Add some postbit information for the linked account.
    find_replace_templatesets(
        'postbit_author_user',
        '#' . preg_quote('{$post[\'userregdate\']}') . '#i',
        '{$post[\'userregdate\']}' . $postbit_steam_id_edit
    );

    // Add a "Login with Steam" button to the welcomeblock.
    find_replace_templatesets(
        'header_welcomeblock_guest',
        '#' . preg_quote('{$lang->welcome_register}</a>') . '#i',
        '{$lang->welcome_register}</a>' . $welcomeblock_edit
    );

} // close function steamlogin_activate


function steamlogin_deactivate()
{

    global $db, $templates, $usercp_nav_link_edit, $postbit_steam_id_edit, $welcomeblock_edit;

    $gid = $db->fetch_array($db->simple_select('settinggroups', 'gid', 'name = \'steamlogin\''));

    if(!empty($gid))
    {

        $gid = $gid['gid'];

        // Delete the settings for our plugin.
        $db->delete_query('settinggroups', 'gid = \'' . $gid . '\'');
        $db->delete_query('settings', 'gid = \'' . $gid . '\'');

        rebuild_settings();

        // Remove steam_id from users table.
        $db->drop_column('users', 'steam_id');

        // Delete the templates.
        $db->delete_query('templates', 'title = \'steam_link_usercp\' OR title = \'steam_profile_link\'');

        require_once MYBB_ROOT.'/inc/adminfunctions_templates.php';

        // Remove link from User CP nav.
        find_replace_templatesets(
            'usercp_nav_misc',
            '#' . preg_quote('<tr><td class="trow1 smalltext"><a href="usercp.php?action=steam_link" class="usercp_nav_item" style="background-image: url(inc/plugins/steamlogin/img/steam_icon.png);background-position:0;background-repeat:no-repeat;">Link to Steam Account</a></td></tr>') . '#i',
            ''
        );

        // Remove postbit edit.
        find_replace_templatesets(
            'postbit_author_user',
            '#' . preg_quote('<br />Steam ID: {steam_linked}') . '#i',
            ''
        );

        // Remove "Steam Login" from welcome block.
        find_replace_templatesets(
            'header_welcomeblock_guest',
            '#' . preg_quote('<a href="{$mybb->settings[\'bburl\']}/misc.php?action=steam_link"><img border="0" src="inc/plugins/steamlogin/img/steam_wide.png" alt="Login with Steam"></a>') . '#i',
            ''
        );

    } // close if(!empty($gid))

} // close function steamlogin_deactivate


function steam_link()
{

    global $mybb, $lang;

    if($mybb->input['action'] == 'steam_link')
    {

        require_once('steamlogin/steam.php');
        $steam = new Steam();

        // Load the plugin language file.
        $lang->load('steamlogin');

        redirect($steam->login(), $lang->steam_redirect_login);

    } // close if($mybb->input['action'] == 'steam_link')

    if($mybb->input['action'] == 'steam_openid')
    {

        require_once('steamlogin/openid.php');
        $openid = new LightOpenID($mybb->settings['bburl']);
        $openid->validate();

        // Load the plugin language file.
        $lang->load('steamlogin');

        // Get the identity of the logged in user.
        $identity = $openid->identity;

        $identity_array = explode('/', $identity);
        $id = end($identity_array);

        require_once('steamlogin/steam.php');
        $steam = new Steam();

        // Check if this ID has already been linked to an account.
        $check_linked = $steam->check_id($id);
        $check_linked = $check_linked['linked'];

        if(!$check_linked)
        {

            if ($mybb->user['uid'] == 0) {

                // There is no user with this Steam ID, redirect them to the register screen.
                my_setcookie('steam_id', $id);

                // Redirect to the register screen.
                redirect(sprintf('%s/member.php?action=register', $mybb->settings['bburl']), $lang->steam_register_message);

            } else { // close if ($mybb->user['uid'] == 0)

                global $db;

                // Update the logged in user with the Steam ID.
                $db->update_query('users', array('steam_id' => $id), 'uid = \'' . $mybb->user['uid'] . '\'');

                // Redirect back to Steam Link UserCP page.
                redirect($mybb->settings['bburl'] . '/usercp.php?action=steam_link', $lang->steam_link_success);

            } // close else

        } else {

            if($mybb->user['uid'] == 0)
            {

                global $cache, $session;

                // Set login cookies for the user.
                my_setcookie('mybbuser', sprintf('%s_%s', $check_linked['uid'], $check_linked['loginkey']), true, true);
                my_setcookie('sid', $session->sid, -1, true);

                // Delete the cache.
                $cache->update(sprintf('steam_data_%s', $id), '');

                // Then recreate it.
                $steam->get_steam_information($id);

                // Redirect back to the forum index.
                redirect($mybb->settings['bburl'], $lang->steam_login_successful);

            } else {

                redirect($mybb->settings['bburl'], $lang->steam_link_taken);

            }

        } // close else

    } // close if($mybb->input['action'] == 'steam_openid')

} // close function steam_link


function complete_steam_link_register()
{

    global $db, $mybb, $lang;

    $username = $mybb->input['username'];
    $id = $mybb->cookies['steam_id'];

    $uid_query = $db->fetch_array($db->simple_select('users', 'uid', 'username = \'' . $username . '\''));

    if(!empty($uid_query))
    {
        $uid = $uid_query['uid'];

        // Update the user record with the Steam ID.
        $db->update_query('users', array('steam_id' => $id), 'uid = \'' . $uid . '\'');

        // Load the plugin language file.
        $lang->load('steamlogin');

        redirect($mybb->settings['bburl'], $lang->steam_register_complete);

        // Unlink the session variable.
        my_unsetcookie('steam_id');

    } // close if(!empty($uid_query))

} // close function complete_steam_link_register


function steam_link_page()
{

    global $mybb, $templates, $lang, $link_status, $header, $headerinclude, $footer, $usercpnav, $theme;

    if($mybb->user['uid'] != 0 && $mybb->input['action'] == 'steam_link')
    {

        // Include the Steam library.
        require_once('steamlogin/steam.php');
        $steam = new Steam();

        // First thing to do is check if they are linked or not.
        $user_linked = $steam->check_user_linked($mybb->user['uid']);

        // Load the plugin language file.
        $lang->load('steamlogin');

        if($user_linked)
        {

            global $steam_profile_link;

            // If the user is linked, get their information from Steam API.
            $steam_info = $steam->get_steam_information($user_linked);

            eval("\$steam_profile_link = \"".$templates->get("steam_profile_link")."\";");
            $link_status = sprintf('<p>' . $lang->steam_linked_message . ' %s</p>', $steam_profile_link);

        } else { // close if($user_linked)

            $link_status = '<p><a href="misc.php?action=steam_link"><img border="0" src="inc/plugins/steamlogin/img/steam_large.png" alt="Login with Steam"></a></p>';

        } // close else

        add_breadcrumb($lang->nav_usercp, "usercp.php");
        add_breadcrumb($lang->ucp_steam_link);

        eval("\$usercp_steam_link = \"".$templates->get("steam_link_usercp")."\";");
        output_page($usercp_steam_link);

    } // close if($mybb->input['action'] == 'steam_link')

} // close function steam_link_page


function add_to_postbit(&$post)
{

    $linked = 'N/A';
    if($post['steam_id'] != 0)
    {
        require_once('steamlogin/steam.php');
        $steam = new Steam();

        global $templates, $steam_info, $steam_profile_link;
        $steam_info = $steam->get_steam_information($post['steam_id']);

        eval("\$steam_profile_link = \"".$templates->get("steam_profile_link")."\";");
        $linked = $steam_profile_link;
    }

    $post['user_details'] = str_replace('{steam_linked}', $linked, $post['user_details']);

} // close function add_to_postbit
