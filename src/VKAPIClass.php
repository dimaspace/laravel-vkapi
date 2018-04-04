<?php
/**
 * File: VKAPIClass.php
 * Created by bafoed.
 * This file is part of VKTM project.
 * Do not modify if you do not know what to do.
 * 2016.
 */

namespace dimaspace\VKAPI;

use Decaptcha;
use Notification;
use \App\Notifications\ExceptionAlert;
use App\User;

class VKAPIClass
{
    /**
     * VK API server URL.
     *
     * @var string
     */
    protected $url = '';

    /**
     * VK API version.
     *
     * @var string
     */

    protected $version = '';

    /**
     * VK API access_token.
     *
     * @var string
     */
    protected $accessToken = '';

    public function __construct($accessToken, $version, $url)
    {
        $this->setAccessToken($accessToken);
        $this->setVersion($version);
        $this->setURL($url);
    }

    /**
     * Updates access_token
     * @param string $token New access token
     */
    public function setAccessToken($token) {
        $this->accessToken = $token;
    }

    /**
     * Updates version
     * @param string $version New version
     */
    public function setVersion($version) {
        $this->version = $version;
    }

    /**
     * Updates VK API URL
     * @param string $url New URL
     */
    public function setURL($url) {
        $this->url = $url;
    }

    /**
     * Make request to VK API.
     *
     * @param string $method Method name
     * @param array $params Method parameters
     * @return array
     * @throws VkApiException
     */
    public function call($method, $params = NULL) {
        $queryString = array(
            'version' => $this->version
        );

        if(!is_null($this->accessToken) && !empty($this->accessToken)) {
            $queryString['access_token'] = $this->accessToken;
        }

        $url = sprintf($this->url, $method, http_build_query($queryString));
        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        if($params != NULL) {
            curl_setopt($ch, CURLOPT_POST, TRUE);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        }
        $response = curl_exec($ch);
        curl_close($ch);

        if(empty($response)) {
            $exception = new VKAPIException(-1, 'Response is empty.');
            throw $exception;
        }
        $json = json_decode($response, true);
        if(empty($json)) {
            $exception = new VKAPIException(-2, 'Error while parsing JSON.');
            throw $exception;
        }

        if(isset($json['error'])) {
            if($json['error']['error_code'] == 14){
                $error = $json['error'];
                if (Decaptcha::run($error['captcha_img'])) {
                    $solved = Decaptcha::result();

                    $params['captcha_sid'] = $error['captcha_sid'];
                    $params['captcha_key'] = $solved;

                    $result = $this->call($method, $params);

                    return $result;

                } else {
                    throw new \Exception(Decaptcha::error());
                }
            }else{
                $exception = new VKAPIException($json['error']['error_code'], $json['error']['error_msg'], $json['error']);
                $exception_msg = $method . "\n";
                $exception_msg .= json_encode($params) . "\n";
                //Notification::send(User::find(1), new ExceptionAlert('VKAPI', $exception_msg, $exception));
                throw $exception;
            };
        }

        return $json['response'];
    }

}
