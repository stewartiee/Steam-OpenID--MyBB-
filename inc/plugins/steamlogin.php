<?php
if(!defined("IN_MYBB")) die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");

function steamlogin_info()
{
    return array(
        "name"  => "Steam Login",
        "description" => "Allows users to one-click sign in with their Steam accounts.",
        "website" => "",
        "author" => "Ryan Stewart",
        "authorsite" => "",
        "version" => "0.0.1",
        "guid" => "",
        "compatibility" => "16*"
    );
}

$plugins->add_hook('member_register_start', 'CreateLoginBtn');
$plugins->add_hook('misc_start', 'OpenIDRtrn');

function CreateLoginBtn() {
    $SteamLogin = new SteamLogin();
    $SteamLogin->CreateLogin();
}

function OpenIDRtrn() {
    global $mybb;
        
    if($mybb->input['action'] == 'steam_login')
    {
    
        $SteamLogin = new SteamLogin();
        $SteamLogin->ReturnData();
        
    }
}

class SteamLogin {
    
    public $SteamOpenID = array();
    
    public function __construct() 
    {
        global $mybb;
        
        require_once MYBB_ROOT.'inc/plugins/scadoodio/openid/openid.php';

        /* Create a connection to the Steam OpenID service. */
        $this->SteamOpenID = new LightOpenID('http://steamcommunity.com/openid');
        $this->SteamOpenID->returnUrl = $mybb->settings['bburl'].'/misc.php?action=steam_login';
        $this->SteamOpenID->__set('realm', $mybb->settings['bburl'].'/misc.php?action=steam_login');

        $this->SteamOpenID->identity = 'http://steamcommunity.com/openid';
    }
    
    public function ReturnData()
    {
        
        print_r($this->SteamOpenID->validate());
        print_r($this->SteamOpenID->identity);
        
        if($this->SteamOpenID->validate() == 0)
        {
        	define("API_KEY", ); // Your Steam API key.
        	
            $ReturnExplode = explode('/', $this->SteamOpenID->identity);
            $SteamID = end($ReturnExplode);
            
            $SteamUser = file_get_contents('http://api.steampowered.com/ISteamUser/GetPlayerSummaries/v0002/?key='.API_KEY.'&steamids='.$SteamID);
            $SteamUser = json_decode($SteamUser, true);
            
            echo "Hello, ".$SteamUser['response']['players'][0]['personaname'];
            
            /**
             *
             * HERE IS WHERE YOU SHOULD DO THE REGULAR REGISTRATION FOR THE STEAM USER.
             *
             */            
        }

    }

    public function CreateLogin()
    {
        //print_r($this->SteamOpenID);
        echo "<a href='".$this->SteamOpenID->authUrl()."'>Login</a>";
    }
    
}