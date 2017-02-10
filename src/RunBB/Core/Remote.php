<?php
/**
 * Copyright 2017 1f7.wizard@gmail.com
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace RunBB\Core;


class Remote
{
    /**
     * url get repo info
     * @var string
     */
    protected $repoInfoUrl = 'https://api.github.com/repos/runcmf/runbb-languages';

    /**
     * url get repo contents
     * @var string
     */
    protected $contentsUrl = 'https://api.github.com/repos/runcmf/runbb-languages/contents/';

    /**
     * get json languages info
     * @var string
     */
    protected $translationsInfoUrl =
        'https://api.github.com/repos/runcmf/runbb-languages/contents/translationsIInfo.json?ref=master';

    protected $extensionsInfofoUrl =
        'https://api.github.com/repos/runcmf/runbb-languages/contents/extensionsInfo.json?ref=master';

    /**
     * Get language list from repo
     * @return array
     */
    public function getLangRepoList()
    {
        $data = json_decode(
            $this->get_remote_contents($this->translationsInfoUrl)
        );

        $content = $data->encoding === 'base64' ? base64_decode($data->content) : [];
        return json_decode($content);
    }

    /**
     * @param null $code short language code 'en', 'ru' etc.
     * @return mixed base64 encoded content
     */
    public function getLang($code=null)
    {
        $url = $this->contentsUrl . 'runbb_translation_' . $code . '.json?ref=master';
        $data = json_decode(
            $this->get_remote_contents($url)
        );

        return $data->content;
    }

    /**
     * Get Extensions list from repo
     * @return array
     */
    public function getExtensionsInfoList()
    {
        $data = json_decode(
            $this->get_remote_contents($this->extensionsInfofoUrl)
        );

        $content = $data->encoding === 'base64' ? base64_decode($data->content) : [];
        return json_decode($content, true);// true to array
    }

    /**
     * Get remote contents
     *
     * from elFinder Core class. v 2.1
     *
     * @param  string   $url     target url
     * @param  int      $timeout timeout (sec)
     * @param  int      $redirect_max redirect max count
     * @param  string   $ua
     * @param  resource $fp
     * @return string or bool(false)
     * @retval string contents
     * @rettval false  error
     * @author Naoki Sawada
     **/
    public function get_remote_contents(&$url, $timeout= 30, $redirect_max= 5, $ua = 'Mozilla/5.0', $fp = null )
    {
        $method = (function_exists('curl_exec') &&
            !ini_get('safe_mode') &&
            !ini_get('open_basedir')) ?
            'curl_get_contents' : 'fsock_get_contents';
        return $this->$method( $url, $timeout, $redirect_max, $ua, $fp );
    }

    /**
     * Get remote contents with cURL
     *
     * from elFinder Core class. v 2.1
     *
     * @param  string   $url     target url
     * @param  int      $timeout timeout (sec)
     * @param  int      $redirect_max redirect max count
     * @param  string   $ua
     * @param  resource $outfp
     * @return string or bool(false)
     * @retval string contents
     * @retval false  error
     * @author Naoki Sawada
     **/
    protected function curl_get_contents( &$url, $timeout, $redirect_max, $ua, $outfp )
    {
        $ch = curl_init();
        curl_setopt( $ch, CURLOPT_URL, $url );
        curl_setopt( $ch, CURLOPT_HEADER, false );
        if ($outfp) {
            curl_setopt( $ch, CURLOPT_FILE, $outfp );
        } else {
            curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
            curl_setopt( $ch, CURLOPT_BINARYTRANSFER, true );
        }
        curl_setopt( $ch, CURLOPT_LOW_SPEED_LIMIT, 1 );
        curl_setopt( $ch, CURLOPT_LOW_SPEED_TIME, $timeout );
        curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
        curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt( $ch, CURLOPT_MAXREDIRS, $redirect_max);
        curl_setopt( $ch, CURLOPT_USERAGENT, $ua);
        $result = curl_exec( $ch );
        $url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        curl_close( $ch );
        return $outfp? $outfp : $result;
    }

    /**
     * Get remote contents with fsockopen()
     *
     * from elFinder Core class. v 2.1
     *
     * @param  string   $url          url
     * @param  int      $timeout      timeout (sec)
     * @param  int      $redirect_max redirect max count
     * @param  string   $ua
     * @param  resource $outfp
     * @return string or bool(false)
     * @retval string contents
     * @retval false  error
     * @author Naoki Sawada
     */
    protected function fsock_get_contents( &$url, $timeout, $redirect_max, $ua, $outfp ) {

        $connect_timeout = 3;
        $connect_try = 3;
        $method = 'GET';
        $readsize = 4096;
        $ssl = '';

        $getSize = null;
        $headers = '';

        $arr = parse_url($url);
        if (!$arr){
            // Bad request
            return false;
        }
        if ($arr['scheme'] === 'https') {
            $ssl = 'ssl://';
        }

        // query
        $arr['query'] = isset($arr['query']) ? '?'.$arr['query'] : '';
        // port
        $arr['port'] = isset($arr['port']) ? $arr['port'] : ($ssl? 443 : 80);

        $url_base = $arr['scheme'].'://'.$arr['host'].':'.$arr['port'];
        $url_path = isset($arr['path']) ? $arr['path'] : '/';
        $uri = $url_path.$arr['query'];

        $query = $method.' '.$uri." HTTP/1.0\r\n";
        $query .= "Host: ".$arr['host']."\r\n";
        $query .= "Accept: */*\r\n";
        $query .= "Connection: close\r\n";
        if (!empty($ua)) $query .= "User-Agent: ".$ua."\r\n";
        if (!is_null($getSize)) $query .= 'Range: bytes=0-' . ($getSize - 1) . "\r\n";

        $query .= $headers;

        $query .= "\r\n";

        $fp = $connect_try_count = 0;
        while( !$fp && $connect_try_count < $connect_try ) {

            $errno = 0;
            $errstr = "";
            $fp =  fsockopen(
                $ssl.$arr['host'],
                $arr['port'],
                $errno,$errstr,$connect_timeout);
            if ($fp) break;
            $connect_try_count++;
            if (connection_aborted()) {
                exit();
            }
            sleep(1); // wait 1sec
        }

        $fwrite = 0;
        for ($written = 0; $written < strlen($query); $written += $fwrite) {
            $fwrite = fwrite($fp, substr($query, $written));
            if (!$fwrite) {
                break;
            }
        }

        $response = '';

        if ($timeout) {
            socket_set_timeout($fp, $timeout);
        }

        $_response = '';
        $header = '';
        while($_response !== "\r\n"){
            $_response = fgets($fp, $readsize);
            $header .= $_response;
        };

        $rccd = array_pad(explode(' ',$header,2), 2, ''); // array('HTTP/1.1','200')
        $rc = (int)$rccd[1];

        $ret = false;
        // Redirect
        switch ($rc) {
            case 307: // Temporary Redirect
            case 303: // See Other
            case 302: // Moved Temporarily
            case 301: // Moved Permanently
                $matches = array();
                if (preg_match('/^Location: (.+?)(#.+)?$/im',$header,$matches) && --$redirect_max > 0) {
                    $_url = $url;
                    $url = trim($matches[1]);
                    $hash = isset($matches[2])? trim($matches[2]) : '';
                    if (!preg_match('/^https?:\//',$url)) { // no scheme
                        if ($url{0} != '/') { // Relative path
                            // to Absolute path
                            $url = substr($url_path,0,strrpos($url_path,'/')).'/'.$url;
                        }
                        // add sheme,host
                        $url = $url_base.$url;
                    }
                    if ($_url !== $url) {
                        fclose($fp);
                        return $this->fsock_get_contents( $url, $timeout, $redirect_max, $ua, $outfp );
                    }
                }
                break;
            case 200:
                $ret = true;
        }
        if (! $ret) {
            fclose($fp);
            return false;
        }

        $body = '';
        if (!$outfp) {
            $outfp = fopen('php://temp', 'rwb');
            $body = true;
        }
        while(fwrite($outfp, fread($fp, $readsize))) {
            if ($timeout) {
                $_status = socket_get_status($fp);
                if ($_status['timed_out']) {
                    fclose($outfp);
                    fclose($fp);
                    return false; // Request Time-out
                }
            }
        }
        if ($body) {
            rewind($outfp);
            $body = stream_get_contents($outfp);
            fclose($outfp);
            $outfp = null;
        }

        fclose($fp);

        return $outfp? $outfp : $body; // Data
    }
}
