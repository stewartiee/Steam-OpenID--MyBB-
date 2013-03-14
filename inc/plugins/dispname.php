<?php

if(!defined('IN_MYBB'))
	die('This file cannot be accessed directly.');


if(!function_exists('control_object')) {
	function control_object(&$obj, $code) {
		static $cnt = 0;
		$newname = '_objcont_'.(++$cnt);
		$objserial = serialize($obj);
		$classname = get_class($obj);
		$checkstr = 'O:'.strlen($classname).':"'.$classname.'":';
		$checkstr_len = strlen($checkstr);
		if(substr($objserial, 0, $checkstr_len) == $checkstr) {
			$vars = array();
			// grab resources/object etc, stripping scope info from keys
			foreach((array)$obj as $k => $v) {
				if($p = strrpos($k, "\0"))
					$k = substr($k, $p+1);
				$vars[$k] = $v;
			}
			if(!empty($vars))
				$code .= '
					function ___setvars(&$a) {
						foreach($a as $k => &$v)
							$this->$k = $v;
					}
				';
			eval('class '.$newname.' extends '.$classname.' {'.$code.'}');
			$obj = unserialize('O:'.strlen($newname).':"'.$newname.'":'.substr($objserial, $checkstr_len));
			if(!empty($vars))
				$obj->___setvars($vars);
		}
		// else not a valid object or PHP serialize has changed
	}
}

$plugins->add_hook('datahandler_user_insert', 'dispname_updateuser');
$plugins->add_hook('datahandler_user_update', 'dispname_updateuser');
$plugins->add_hook('datahandler_user_validate', 'dispname_verifyuser');

$plugins->add_hook('member_do_register_start', 'dispname_member_do_register');
$plugins->add_hook('member_activate_start', 'dispname_member_activate_reset');
$plugins->add_hook('member_resetpassword_start', 'dispname_member_activate_reset');
$plugins->add_hook('member_do_resendactivation_start', 'dispname_member_reactivate');
$plugins->add_hook('member_do_lostpw_start', 'dispname_member_lostpw');

$plugins->add_hook('portal_do_login_start', 'dispname_member_login');
$plugins->add_hook('member_do_login_start', 'dispname_member_login');
$plugins->add_hook('member_register_end', 'dispname_register_langs');
$plugins->add_hook('xmlhttp', 'dispname_register_checkloginname');

if(defined('IN_ADMINCP')) {
	$action =& $GLOBALS['mybb']->input['do'];
	if($action == 'login')
		dispname_admin_login();
	elseif($action == 'unlock')
		dispname_admin_unlock();
}

$plugins->add_hook('admin_user_users_add', 'dispname_admin_add_field');
$plugins->add_hook('admin_user_users_edit', 'dispname_admin_add_field');


function dispname_info()
{
	return array(
		'name'			=> 'Display Usernames / Nicks Plugin',
		'description'	=> 'Allow users to have a different display and login names.',
		'website'		=> 'http://mybbhacks.zingaburga.com/',
		'author'		=> 'ZiNgA BuRgA',
		'authorsite'	=> 'http://zingaburga.com/',
		'version'		=> '1.05',
		'compatibility'	=> '14*,15*,16*',
		'guid'			=> ''
	);
}

function dispname_close_board($e) {
	$GLOBALS['db']->update_query('settings', array('value' => $e), 'name="boardclosed"');
	rebuild_settings();
}
function dispname_template_mods() {
	return array(
		'<td colspan="2"><span class="smalltext"><label for="username">{$lang->username}</label></span></td>' => '<td><span class="smalltext"><label for="loginname">{$lang->loginname}</label></span></td><td><span class="smalltext"><label for="username">{$lang->username}</label></span></td>',
		'<td colspan="2"><input type="text" class="textbox" name="username" id="username" style="width: 100%" value="{$username}" /></td>' => '<td><input type="text" class="textbox" name="loginname" id="loginname" style="width: 100%" value="{$loginname}" /></td><td><input type="text" class="textbox" name="username" id="username" style="width: 100%" value="{$username}" /></td>',
		'regValidator.register(\'username\', \'ajax\', {url:\'xmlhttp.php?action=username_availability\', loading_message:\'{$lang->js_validator_checking_username}\'});' => 'regValidator.register(\'loginname\', \'ajax\', {url:\'xmlhttp.php?action=loginname_availability\', loading_message:\'{$lang->js_validator_checking_loginname}\'});
	regValidator.register(\'username\', \'ajax\', {url:\'xmlhttp.php?action=username_availability\', loading_message:\'{$lang->js_validator_checking_username}\'});'
	);
}
function dispname_activate() {
	global $db, $mybb;
	
	// close the board for a sec
	if(!$mybb->settings['boardclosed']) {
		dispname_close_board(1);
		$unclose_board = true;
	}
	
	$db->write_query('ALTER TABLE `'.$db->table_prefix.'users` ADD COLUMN `loginname` varchar(120) NOT NULL default ""');
	$db->write_query('UPDATE `'.$db->table_prefix.'users` SET loginname=username');
	$db->write_query('ALTER TABLE `'.$db->table_prefix.'users` ADD UNIQUE KEY `loginname` (`loginname`)');
	
	
	require MYBB_ROOT.'inc/adminfunctions_templates.php';
	foreach(dispname_template_mods() as $src => $dest)
		find_replace_templatesets('member_register', '~'.preg_quote($src).'~', $dest);
	
	
	
	
	if($unclose_board) dispname_close_board(0);
}
function dispname_deactivate() {
	global $db, $cache;
	
	// close the board for a sec
	if(!$mybb->settings['boardclosed']) {
		dispname_close_board(1);
		$unclose_board = true;
	}
	
	// set everyone's usernames back to the loginname
	@ignore_user_abort(true);
	@set_time_limit(0);
	$stats = $cache->read('stats');
	// remove unique key in case we have a duplicate loginname/username
	$db->write_query('ALTER TABLE `'.$db->table_prefix.'users` DROP KEY `username`');
	$query = $db->simple_select('users', 'uid,loginname', 'username!=loginname');
	while($user = $db->fetch_array($query)) {
		// fix usernames in DB
		// most of this stuff copied from update_user() method of the MyBB userhandler
		$escname = $db->escape_string($user['loginname']);
		$username_update = array('username' => &$escname);
		$username_cond = 'uid='.$user['uid'];
		$lastposter_update = array('lastposter' => &$escname);
		$lastposter_cond = 'lastposteruid='.$user['uid'];
		
		$db->update_query('posts', $username_update, $username_cond);
		$db->update_query('threads', $username_update, $username_cond);
		$db->update_query('threads', $lastposter_update, $lastposter_cond);
		$db->update_query('forums', $lastposter_update, $lastposter_cond);
		
		if($stats['lastuid'] == $user['uid'])
			update_stats(array('numusers' => '+0'));
	}
	$db->write_query('UPDATE `'.$db->table_prefix.'users` SET username=loginname');
	$db->write_query('ALTER TABLE `'.$db->table_prefix.'users` DROP COLUMN `loginname`, DROP KEY `loginname`, ADD UNIQUE KEY `username` (`username`)');
	
	
	
	require MYBB_ROOT.'inc/adminfunctions_templates.php';
	foreach(dispname_template_mods() as $src => $dest)
		find_replace_templatesets('member_register', '~'.preg_quote($dest).'~', $src, 0);
	
	
	if($unclose_board) dispname_close_board(0);
}

function dispname_updateuser(&$uh) {
	global $db;
	if(!isset($uh->data['loginname'])) // this should only occur when updating and loginname is not set
		return;
	
	if(isset($uh->user_insert_data)) {
		$user =& $uh->user_insert_data;
	} elseif(isset($uh->user_update_data)) {
		$user =& $uh->user_update_data;
	} else return;
	
	$user['loginname'] = $db->escape_string($uh->data['loginname']);
}
function dispname_verifyuser(&$uh) {
	global $mybb;
	// fix up stuff for new registration page
	if($GLOBALS['dispname_member_do_register']) {
		global $lang, $loginname;
		if($mybb->input['loginname']) {
			$uh->data['loginname'] = $mybb->input['loginname'];
			$loginname = htmlspecialchars_uni($mybb->input['loginname']);
			// !!! relies on language string !!!
			$lang->email_activateaccount = str_replace('Username: {1}', 'Username: '.$loginname, $lang->email_activateaccount);
			
			$lang->email_randompassword = str_replace('{3}', $loginname, $lang->email_randompassword);
		}
	} elseif(defined('IN_ADMINCP') && isset($GLOBALS['user_view_fields']) && ($mybb->input['action'] == 'add' || $mybb->input['action'] == 'edit')) {
		// AdminCP add/edit user
		if($mybb->input['loginname']) {
			$uh->data['loginname'] = $mybb->input['loginname'];
		}
	}
	
	if($uh->method == 'insert' && !isset($uh->data['loginname']))
		$uh->data['loginname'] = $uh->data['username'];
	
	if(isset($uh->data['loginname'])) {
		global $lang;
		$lang->userdata_bad_characters_loginname = 'The login name you entered contains bad characters. Please enter a different login name.';
		$lang->userdata_loginname_exists = 'The login name you entered already exists. Please enter a different login name.';
		if(!dispname_loginname_valid($uh->data['loginname']))
			$uh->set_error('bad_characters_loginname', array($uh->data['loginname']));
		elseif(dispname_loginname_exists($uh->data['loginname'], $uh->data['uid']))
			$uh->set_error('loginname_exists', array($uh->data['loginname']));
	}
}

function dispname_member_do_register() {
	$GLOBALS['dispname_member_do_register'] = true;
}
function dispname_member_activate_reset() {
	global $mybb;
	if($mybb->input['username']) {
		control_object($GLOBALS['db'], '
			function simple_select($table, $fields="*", $conditions="", $options=array()) {
				static $done=false;
				if(!$done && substr($conditions, 0, 16) == "LOWER(username)=") {
					$done = true;
					$conditions = "LOWER(loginname)".substr($conditions, 15);
				}
				return parent::simple_select($table, $fields, $conditions, $options);
			}
		');
	} elseif(!$mybb->input['code'] || !$user['uid']) {
		if($mybb->input['action'] == 'resetpassword')
			$tplname = 'member_resetpassword';
		else
			$tplname = 'member_activate';
		$tpl =& $GLOBALS['templates']->cache[$tplname];
		if(!$tpl) $GLOBALS['templates']->cache($tplname);
		$tpl = str_replace('{$user[\'username\']}', '{$user[\'loginname\']}', $tpl);
	}
}
function dispname_member_reactivate() {
	control_object($GLOBALS['db'], '
		function query($string, $hide_errors=0, $write_query=0) {
			static $done=false;
			if(!$done) {
				$string = str_replace("SELECT u.uid, u.username, ", "SELECT u.uid, u.username, u.loginname, ", $string);
			}
			return parent::query($string, $hide_errors, $write_query);
		}
	');
	control_object($GLOBALS['lang'], '
		function sprintf($string) {
			if($string == $this->email_activateaccount) {
				// !!! relies on language string !!!
				$string = str_replace("Username: {1}", "Username: ".$GLOBALS[\'user\'][\'loginname\'], $string);
			}
			$args = func_get_args();
			return call_user_func_array(array(parent, \'sprintf\'), $args);
		}
	');
}
function dispname_member_lostpw() {
	control_object($GLOBALS['lang'], '
		function sprintf($string) {
			if($string == $this->email_lostpw) {
				// !!! relies on language string !!!
				$string = str_replace("Username: {1}", "Username: ".$GLOBALS[\'user\'][\'loginname\'], $string);
			}
			$args = func_get_args();
			return call_user_func_array(array(parent, \'sprintf\'), $args);
		}
	');
}

function dispname_member_login() {
	control_object($GLOBALS['db'], '
		function simple_select($table, $fields="*", $conditions="", $options=array()) {
			if($table == "users" && $options[\'limit\'] == 1) {
				if(substr($conditions, 0, 10) == "username=\'")
					$conditions = "loginname".substr($conditions, 8);
				elseif(substr($conditions, 0, 17) == "LOWER(username)=\'")
					$conditions = "LOWER(loginname)".substr($conditions, 15);
			}
			return parent::simple_select($table, $fields, $conditions, $options);
		}
		
		function write_query($query, $hide_errors=0) {
			if(($p = strpos($query, "loginattempts=loginattempts+1")) && substr($query, $p-10, 46) == "users SET loginattempts=loginattempts+1 WHERE ") {
				$p = strpos($query, "username", $p);
				$query = substr($query, 0, $p)."loginname".substr($query, $p+8);
			}
			return parent::write_query($query, $hide_errors);
		}
	');
}
function dispname_admin_login() {
	control_object($GLOBALS['db'], '
		function simple_select($table, $fields="*", $conditions="", $options=array()) {
			static $done = false;
			static $done2 = false;
			if(!$done && $table == "users" && $options[\'limit\'] == 1) {
				if(substr($conditions, 0, 10) == "username=\'") {
					$conditions = "loginname".substr($conditions, 8);
					$done = true;
				}
				elseif(substr($conditions, 0, 17) == "LOWER(username)=\'") {
					$conditions = "LOWER(loginname)".substr($conditions, 15);
					$done = true;
				}
			}
			elseif(!$done2 && $table == "users" && $fields == "uid,email" && substr($conditions, 0, 19) == "LOWER(username) = \'") {
				$conditions = "LOWER(loginname)".substr($conditions, 15);
				$done2 = true;
			}
			return parent::simple_select($table, $fields, $conditions, $options);
		}
	');
}
function dispname_admin_unlock() {
	if(!$GLOBALS['mybb']->input['username']) return;
	control_object($GLOBALS['db'], '
		function simple_select($table, $fields="*", $conditions="", $options=array()) {
			static $done = false;
			if(!$done && $table == "users" && $fields == "*") {
				if(substr($conditions, 0, 10) == "username=\'") {
					$conditions = "loginname".substr($conditions, 8);
					$done = true;
				}
				elseif(substr($conditions, 0, 16) == "LOWER(username)=\'") {
					$conditions = "LOWER(loginname)".substr($conditions, 14);
					$done = true;
				}
			}
			return parent::simple_select($table, $fields, $conditions, $options);
		}
	');
}

function dispname_register_langs() {
	global $lang;
	$lang->loginname = 'Login Name';
	$lang->username = 'Display Username';
	//$lang->js_validator_checking_username = 'Checking if display name is available';
	$lang->js_validator_checking_loginname = 'Checking if login name is available';
	
	$GLOBALS['loginname'] = htmlspecialchars_uni($GLOBALS['mybb']->input['loginname']);
}
function dispname_register_checkloginname() {
	global $mybb;
	if($mybb->input['action'] != 'loginname_availability') return;
	global $lang;
	header('Content-type: text/xml; charset='.$GLOBALS['charset']);
	if(!dispname_loginname_valid($mybb->input['value']))
		die("<fail>{$lang->banned_characters_username}</fail>");
	$nameout = htmlspecialchars_uni($mybb->input['value']);
	if(dispname_loginname_exists($mybb->input['value']))
		die('<fail>'.$lang->sprintf($lang->username_taken, $nameout).'</fail>');
	else
		die('<success>'.$lang->sprintf($lang->username_available, $nameout).'</success>');
}


function dispname_admin_add_field() {
	global $mybb, $plugins;
	
	if($mybb->request_method == 'post') {
		if(!trim($mybb->input['loginname'])) {
			global $lang;
			$lang->no_loginname = 'No login name supplied';
			$GLOBALS['errors'][] = $lang->no_loginname;
		} else {
			
			function _dispname_admin_update_field() {
				global $user_info, $mybb, $db;
				if($user_info['uid']) // insert_user
					$uid = $user_info['uid'];
				else // update_user
					$uid = $GLOBALS['user']['uid'];
				
				$db->update_query('users', array('loginname' => $db->escape_string(trim($mybb->input['loginname']))), 'uid='.intval($uid));
			}
			$plugins->add_hook('admin_user_users_add_commit', '_dispname_admin_update_field');
			$plugins->add_hook('admin_user_users_edit_commit', '_dispname_admin_update_field');
		}
	}
	
	function _dispname_admin_add_field(&$a) {
		if($a['label_for'] == 'username') {
			global $lang;
			$lang->loginname = 'Login Name';
			$a['this']->output_row($lang->loginname.' <em>*</em>', '', $GLOBALS['form']->generate_text_box('loginname', $GLOBALS['mybb']->input['loginname'], array('id' => 'loginname')), 'loginname');
		}
	}
	$plugins->add_hook('admin_formcontainer_output_row', '_dispname_admin_add_field');
}


function dispname_loginname_valid(&$name) {
	$name = preg_replace('#\s{2,}#', ' ', trim($name));
	if(!$name) return false;
	if(strpos($name, '<') !== false || strpos($name, '>') !== false || strpos($name, '&') !== false || my_strpos($name, '\\') !== false || strpos($name, ';') !== false || strpos($username, ",") !== false)
		return false;
	
	return true;
}
function dispname_loginname_exists($name, $uid=0) {
	global $db;
	$uid_check = '';
	if($uid)
		$uid_check = ' AND uid!='.intval($uid);
	$query = $db->simple_select('users', 'COUNT(uid) AS count', 'LOWER(loginname)="'.$db->escape_string(strtolower($name)).'"'.$uid_check);
	return ($db->fetch_field($query, 'count') > 0);
}

// TODO: admin search users??
// TODO: langs - when logging in, change "Username" -> "Login name"

?>