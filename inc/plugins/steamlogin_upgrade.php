<?php
/**
 *
 * Steam Login - Upgrade
 * - - - - - - - - - - -
 * For security you should delete this file after usage.
 *
 */


define("IN_MYBB", 1);
require_once "../../global.php";

$db->query("UPDATE ".TABLE_PREFIX."users SET allownotices = 1, receivepms = 1, pmnotice = 1, pmnotify = 1, showsigs = 1, showavatars = 1, showquickreply = 1, showredirect = 1");


?>