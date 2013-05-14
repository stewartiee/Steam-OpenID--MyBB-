Steam-OpenID--MyBB-
===================
This plugin will replace the base functionality of registering and logging in by default. The code is pretty self explanitory so you can change this to work alongside etc.

To install copy all files to your server and enable both the 'Display Usernames' and 'Steam Login' plugins.
You will need a Steam API key to use the 'Steam Login' plugin. You can configure your key in the Settings menu in administration panel.


2013-05-14 - Update
 - Changed the way a user is registered
 - Fixed redirects on new_reply and new_thread error pages if not logged in.

2013-05-05 - Update
 - Fixed fields when registering
 - Added in redirect for login and register screens
 - Included upgrade script for users with previous data


 IMPORTANT!!!!
 -------------------------------
 If you are upgrading, ensure you remove the steamlogin_upgrade.php file after usage.
 The steamlogin_upgrade.php file should be run directly from [YOUR_BOARD_URL]/inc/plugins/steamlogin_upgrade.php If you are not upgrading this should be deleted as it will affect your MyBB admin panel when trying to activate the plugin.


-------------------------------
The included plugin, dispname.php, was created and owned by ZiNgA BuRgA (http://mybbhacks.zingaburga.com/showthread.php?tid=259).