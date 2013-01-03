<?php 
/**
 * Steam Login
 * ----------------------------------
 * Provided with no warranties by Ryan Stewart (www.calculator.tf)
 * This has been tested on MyBB 1.6
 */

class Steam {

    // You can get an API key by going to http://steamcommunity.com/dev/apikey
    public $API_KEY = "";
    
    function curl($url)
    {
        if(function_exists('curl_version'))
        {
            $ch = curl_init();
            curl_setopt_array($ch, array(CURLOPT_URL => $url, CURLOPT_HEADER => false, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10));
            $data = curl_exec($ch);
            curl_close($ch);
            return $data;

        } else { // if(function_exists('curl_version'))

            die("cURL doesn't appear to be installed.");

        } // close else
    } // close function curl
	
    /**
     * GetUserInfo
     *-------------------------------------
     * This will return information about the Steam user
     * including their avatar, persona and online status.
     */
	function GetUserInfo($SearchString = '') {

		if(strstr($SearchString, 'steamcommunity.com')) {
			$SearchString = $this->_ResolveVanity($SearchString);
		} // close if(strstr($SearchString, 'steamcommunity.com'))

		$InfoArrayJSON = $this->curl('http://api.steampowered.com/ISteamUser/GetPlayerSummaries/v0002/?key='.$this->API_KEY.'&steamids='.$SearchString);
		return json_decode($InfoArrayJSON, true);
	} // close GetUserInfo


    /**
     * _ResolveVanity
     * -------------------------------------
     * This can be used to get the Steam 64 ID from a ID (Stewartiee) 
     * or Steam Link (www.steamcommunity.com/id/Stewartiee)
     */
	function _ResolveVanity($SteamLink = '') 
    {

        // If the passed value is numeric and 17 characters we presume it's a Steam 64 ID.
        if(is_numeric($SteamLink) and strlen($SteamLink) == 17) {
            return $SteamLink;
        } elseif(strstr($SteamLink, 'steamcommunity.com/profiles/')) 
        {
        	$SteamLink = rtrim($SteamLink, '/');
            $explode_link = explode('/',$SteamLink);
            $SteamLink = end($explode_link);

            return $SteamLink;
        } else {
            
            if(strstr($SteamLink, 'steamcommunity.com/id/')) {
            	$SteamLink = rtrim($SteamLink, '/');
                $explode_link = explode('/', $SteamLink);
                $SteamLink = end($explode_link);
            } // close if(strstr($SteamLink, 'steamcommunity.com/id/'))
            
			$VanityArrayJSON = $this->curl('http://api.steampowered.com/ISteamUser/ResolveVanityURL/v0001/?key='.$this->API_KEY.'&vanityurl='.$SteamLink);
			return json_decode($VanityArrayJSON, true);
            
        } // close else

	} // close function _ResolveVanity

} // close class Steam