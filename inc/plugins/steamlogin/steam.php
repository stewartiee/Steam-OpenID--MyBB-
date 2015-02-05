<?php

// Load the OpenID Library.
require_once('openid.php');

class Steam extends LightOpenID
{

    private $key;

    private $login_return;

    public function __construct()
    {

        global $db, $mybb;

        $this->login_return = sprintf('%s/%s', $mybb->settings['bburl'], 'misc.php?action=steam_openid');

        $fetch_key_setting = $db->fetch_array($db->simple_select('settings', 'name, value', 'name = \'steamlogin_api_key\''));
        $this->key = $fetch_key_setting['value'];

        if(is_null($this->key) || strlen($this->key) == 0)
        {

            global $templates, $header, $headerinclude, $footer, $error, $title;

            $title = 'Steam Error';
            $error = 'The Steam Login plugin hasn\'t been configured correctly. Please ensure a valid API key is set.';

            add_breadcrumb($title);

            eval("\$error_message = \"".$templates->get("error")."\";");
            output_page($error_message);
            die();
        }

    } // close function __construct


    public function login()
    {

        global $mybb;

        $this->returnUrl = $this->login_return;
        $this->__set('realm', $mybb->settings['bburl']);

        $this->identity = 'http://www.steamcommunity.com/openid';

        return $this->authUrl();

    } // close function login


    public function check_id($id = 0)
    {

        // Check to ensure the ID is passed.
        if($id == 0 || is_null($id)) return false;

        global $db;

        $check_id_query = $db->fetch_array($db->simple_select('users', 'uid, loginkey', 'steam_id = \'' . $id . '\''));

        $already_linked = false;
        if(!empty($check_id_query)) $already_linked = $check_id_query;

        return array('linked' => $already_linked);

    }


    public function check_user_linked($uid = 0)
    {

        // Check that a UID has been passed.
        if($uid == 0) return false;

        global $db;

        // Get data from the database.
        $fetch_record = $db->simple_select('users', 'steam_id', 'uid = \'' . $uid . '\'');
        $steam_id = $db->fetch_field($fetch_record, 'steam_id');

        if($steam_id == 0) return false; else return $steam_id;

    } // close function check_user_linked


    public function get_steam_information($id = null)
    {

        global $cache;

        $cache_name = sprintf('steam_data_%s', $id);

        // Ensure there is an ID passed.
        if(is_null($id)) return false;

        // Build the URL we will be calling.
        $steam_profile_api_url = sprintf('http://api.steampowered.com/ISteamUser/GetPlayerSummaries/v0002/?key=%s&steamids=%s', $this->key, $id);

        // Check to see if we have a cached result.
        $cache_check = $cache->read($cache_name);

        if(empty($cache_check))
        {

            // Fetch the information.
            $fetch_data = $this->curl($steam_profile_api_url);
            $fetch_data = json_decode($fetch_data);

            if (isset($fetch_data->response->players[0])) {

                $account_info = $fetch_data->response->players[0];

                $steam_information = array(
                    'steam_id' => $id,
                    'personaname' => $account_info->personaname,
                    'avatar' => $account_info->avatar
                );

                $cache->update($cache_name, $steam_information);

            }

        } // close if($cache_check == 0)

        return $cache->read($cache_name);

    }


    private function curl($url = null)
    {

        // Ensure there is a URL passed.
        if(is_null($url)) return false;

        if(function_exists('curl_version'))
        {

            $ch = curl_init();
            curl_setopt_array($ch, array(CURLOPT_URL => $url, CURLOPT_HEADER => false, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10));
            $data = curl_exec($ch);
            curl_close($ch);
            return $data;

        } else {

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


} // close class Steam