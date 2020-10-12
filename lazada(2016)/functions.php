<?php

namespace App\Helpers;
use DateTime;
use Debugbar;
use Storage;


class Helper {
    public static function subsearch( $str, $start, $end, $value=false, $iStart=0 )
    {
        $pos1 = strpos( $str, $start, $iStart );
        //Debugbar::info('pos1:'.$pos1);
        if ( $pos1 !== false)
        {
            if ($value == false) {
                $pos2 = strpos( $str, $end, $pos1);
                return substr( $str, $pos1, $pos2 - $pos1 + strlen($end));
            }
            else {
                //value==true, 값만 뽑아낸다
                $pos2 = strpos( $str, $end, $pos1 + strlen( $start ) );
                return substr( $str, $pos1 + strlen( $start ), $pos2 - ( $pos1 + strlen( $start ) ) );
            }

        }
        return '';
    }

    public static function statusText($count)
    {
        $status='';
        switch ($count) {
            case '0':
                $status='대기중';
                break;
            
            case '1':
                $status='업로드중';
                break;

            default:
                $status='대기중';
                break;
        }
        return $status;
    }

    public static function lazadaCreateParameter($id_lazada, $api_lazada, $action_lazada)
    {
        date_default_timezone_set("UTC");
        $now = new DateTime();
        $parameters = array(
            // The user ID for which we are making the call.
            'UserID' => $id_lazada,
        
            // The API version. Currently must be 1.0
            'Version' => '1.0',
        
            // The API method to call.
            'Action' => $action_lazada,
        
            // The format of the result.
            'Format' => 'json',
        
            // The current time formatted as ISO8601
            'Timestamp' => $now->format(DateTime::ISO8601)
        );
        ksort($parameters);
        Debugbar::info($parameters['Timestamp']);
        
        // URL encode the parameters.
        $encoded = array();
        foreach ($parameters as $name => $value) {
            $encoded[] = rawurlencode($name) . '=' . rawurlencode($value);
        }

        // Concatenate the sorted and URL encoded parameters into a string.
        $concatenated = implode('&', $encoded);

        Debugbar::info($concatenated);

        // The API key for the user as generated in the Seller Center GUI.
        // Must be an API key associated with the UserID parameter.
        //$api_lazada = '5deBTB1PTQwmM7q7FNddySbNPDhG0btZV_6xDCXQFrsnthHtWM-w7JZA';

        // Compute signature and add it to the parameters.
        $parameters['Signature'] = rawurlencode(hash_hmac('sha256', $concatenated, $api_lazada, false));

        return $parameters;
    }

    public static function lazadaAPIexecute($url, $action, $uid)
    {
        $tmpFile = $_SERVER['DOCUMENT_ROOT']."/data/user/$uid/xml/$action.xml";
        ///SENDING DATA TO LAZADA API
        $curl = curl_init();
        //TRUE to HTTP PUT a file. The file to PUT must be set with CURLOPT_INFILE and CURLOPT_INFILESIZE.
        curl_setopt( $curl, CURLOPT_PUT, 1 ); 
        //display headers
        curl_setopt( $curl, CURLOPT_HEADER, true);
        //The name of a file holding one or more certificates to verify the peer with. This only makes sense when used in combination with CURLOPT_SSL_VERIFYPEER.
        curl_setopt( $curl, CURLOPT_SSL_VERIFYPEER, false);
        //  A directory that holds multiple CA certificates. Use this option alongside CURLOPT_SSL_VERIFYPEER.
        curl_setopt( $curl, CURLOPT_SSL_VERIFYHOST, false); 
        //  TRUE to HTTP PUT a file. The file to PUT must be set with CURLOPT_INFILE and CURLOPT_INFILESIZE.
        curl_setopt( $curl, CURLOPT_INFILESIZE, filesize($tmpFile) );
        //The expected size, in bytes, of the file when uploading a file to a remote site.
        curl_setopt( $curl, CURLOPT_INFILE, ($in=fopen($tmpFile, 'r')) );
        //A custom request method to use instead of "GET" or "HEAD" when doing a HTTP request. 
        curl_setopt( $curl, CURLOPT_CUSTOMREQUEST, 'POST' );
        //An array of HTTP header fields to set,
        curl_setopt( $curl, CURLOPT_HTTPHEADER, [ 'Content-Type: application/x-www-form-urlencoded' ] );
        //The URL to fetch. 
        curl_setopt( $curl, CURLOPT_URL, $url );
        //TRUE to return the transfer as a string of the return value of curl_exec() instead of outputting it out directly.
        curl_setopt( $curl, CURLOPT_RETURNTRANSFER, 1 );
        //executing code for connection
        $result = curl_exec($curl);
        //closing connection
        curl_close($curl);
        //closing the open file CURLOPT_INFILE
        fclose($in);

        return $result;
    }

    public static function getLazadaCategoryOtherOption($category_id)
    {
        $ret='';
        if (($category_id <= 10181 && $category_id <= 10192) || ($category_id <= 10216 && $category_id <= 10240)   ) {
            $ret='<baby_recommended_age>Not Specified</baby_recommended_age>';
        }
        else {
            $ret = '';
        }

        return $ret;
    }

    public static function getLazadaOptionColor($option)
    {
        $ret='';

        if (!$option) return '';
        Debugbar::info($option);

        $color = Storage::disk('local')->get('xml/lazada/colors.json');
        $color = json_decode($color, true);
        $option = strtolower($option); 

        $key = array_search($option, array_column($color, 'text'));

        if ($key) {
            $ret = $color[$key]['color'];
        }
        else $ret = '';

        return $ret;
    }

    public static function getLazadaOptionSize($option)
    {
        $ret='';

        if (!$option) return '';

        $size = Storage::disk('local')->get('xml/lazada/sizes.json');
        $size = json_decode($size, true);

        $key = array_search($option, array_column($size, 'text'));

        if ($key) {
            $ret = $size[$key]['size'];
        }
        else $ret = '';

        return $ret;
    }

    public static function searchArray($array, $key, $value)
    {
        $results = array();
    
        if (is_array($array)) {
            if (isset($array[$key]) && $array[$key] == $value) {
                $results[] = $array;
            }
    
            foreach ($array as $subarray) {
                $results = array_merge($results, Helper::searchArray($subarray, $key, $value));
            }
        }
    
        return $results;
    }
}

?>