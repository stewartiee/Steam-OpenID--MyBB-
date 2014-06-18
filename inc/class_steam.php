<?php 
/**
 * Steam Login
 * ----------------------------------
 * Provided with no warranties by Ryan Stewart (www.calculator.tf)
 * This has been tested on MyBB 1.6
 */

class steam {

    // You can get an API key by going to http://steamcommunity.com/dev/apikey
    public $API_KEY = "";

    function __construct()
    {
        global $db;

        $get_key = $db->fetch_array($db->simple_select("settings", "name, value", "name = 'steamlogin_api_key'"));
        $this->API_KEY = $get_key['value'];

        // Check CURL is installed, if not KILL!
        if(!function_exists('curl_version')) die("You don't have CURL installed on your server. This is a requirement. Without it, nothing would work...");

    } // close function __construct
    
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

            if (function_exists('fopen') && ini_get('allow_url_fopen'))
            {
                $context = stream_context_create( array(
                    'http'=>array(
                      'timeout' => 10.0
                    )
                  ));
                $handle = @fopen($url, 'r', false, $context);
                $file = @stream_get_contents($handle);
                @fclose($handle);
                return $file;

            } else {
            	if(!function_exists('fopen') && ini_get('allow_url_fopen')){
                	die("cURL and Fopen are both disabled. Please enable one or the other. cURL is prefered.");
            	} elseif(function_exists('fopen') && !ini_get('allow_url_fopen')){
            		die("cURL is disabled and Fopen is enabled but 'allow_url_fopen' is disabled(means you can not open external urls). Please enabled one or the other.");
            	} else {
            		die("cURL and Fopen are both disabled. Please enable one or the other. cURL is prefered.");
            	}
            }

        } // close else
    } // close function curl

    // With thanks to https://github.com/damianb/tf2stats for the convert64to32 function.
    function convert64to32($steam_cid)
    {
        $id = array('STEAM_0');
        $id[1] = substr($steam_cid, -1, 1) % 2 == 0 ? 0 : 1;
        $id[2] = bcsub($steam_cid, '76561197960265728');
        if(bccomp($id[2], '0') != 1)
        {
            return false;
        }
        $id[2] = bcsub($id[2], $id[1]);
        list($id[2], ) = explode('.', bcdiv($id[2], 2), 2);
        return implode(':', $id);
    } // close function convert64to32


    /**
     * get_user_info
     *-------------------------------------
     * This will return information about the Steam user
     * including their avatar, persona and online status.
     */
	function get_user_info($id = '') {

        // Resolve our ID.
		$id = $this->_resolve_vanity($id);

        if($id['status'] == 'success')
        {

    		$info_array = $this->curl('http://api.steampowered.com/ISteamUser/GetPlayerSummaries/v0002/?key='.$this->API_KEY.'&steamids='.$id['steamid']);
    		$info_array = json_decode($info_array, true);

            if(isset($info_array['response']['players'][0])) 
            {

                $player_info = $info_array['response']['players'][0];

                $personaname = $player_info['personaname'];
                $profileurl = $player_info['profileurl'];
                $avatar_s = $player_info['avatar'];
                $avatar_m = $player_info['avatarmedium'];
                $avatar_l = $player_info['avatarfull'];
                $personastate = $player_info['personastate'];

                $steamid32 = $this->convert64to32($id['steamid']);

                $return_array = array(
                    'status' => 'success',
                    'steamid' => $id['steamid'],
                    'steamid32' => $steamid32,
                    'personaname' => $personaname,
                    'profileurl' => $profileurl,
                    'avatars' => array(
                        'small' => $avatar_s,
                        'medium' => $avatar_m,
                        'large' => $avatar_l
                    ),
                    'personastate' => $personastate
                );

            } else {

                $return_array = array(
                    'status' => 'error',
                    'message' => 'An error occurred retrieving user information from the Steam service.'
                );

            } // close else

        } elseif($id['status'] == 'error')
        {

            $return_array = array(
                'status' => 'error',
                'message' => $id['message']
            );

        } // close elseif($id['status'] == 'error')

        return $return_array;

	} // close get_user_info


    function get_steam_level($steamid = 0)
    {

        if($steamid > 0)
        {

            // Set a default level as ?, just incase something goes wrong.
            $level = '?';

            // Do the CURL request to the Steam service.
            $get_response = $this->curl('http://api.steampowered.com/IPlayerService/GetSteamLevel/v1/?key='.$this->API_KEY.'&steamid='.$steamid);
            $get_response = json_decode($get_response);

            // Check if the response is telling us the level.
            if(isset($get_response->response->player_level)) $level = $get_response->response->player_level;

            // Finally, return it.
            return $level;

        } // close if($steamid > 0)

    } // close function get_steam_level


    /**
     * _resolve_vanity
     * -------------------------------------
     * This can be used to get the Steam 64 ID from a ID (Stewartiee) 
     * or Steam Link (www.steamcommunity.com/id/Stewartiee)
     */
	function _resolve_vanity($link = '') 
    {

        // If the passed value is numeric and 17 characters we presume it's a Steam 64 ID.
        if(is_numeric($link) and strlen($link) == 17) 
        {

            $return_array = array(
                'status' => 'success',
                'steamid' => $link
            );

        } else {

            if(strstr($link, '/')) {
            	$link = rtrim($link, '/');
                $explode_link = explode('/',$link);
                $link = end($explode_link);
            }
            
			$vanity_array = $this->curl('http://api.steampowered.com/ISteamUser/ResolveVanityURL/v0001/?key='.$this->API_KEY.'&vanityurl='.$link);
            $vanity_array = json_decode($vanity_array, true);

            if($vanity_array['response']['success'] == 1) 
            {

                $steamid = $vanity_array['response']['steamid'];

                $return_array = array(
                    'status' => 'success',
                    'steamid' => $steamid
                );

            } elseif($vanity_array['response']['success'] == 42) {

                $message = $vanity_array['response']['message'];

                $return_array = array(
                    'status' => 'error',
                    'message' => $message
                );

            } // close elseif($vanity_array['response']['success'] == 42)
            
        } // close else

        return $return_array;

	} // close function _resolve_vanity

} // close class steam
