<?php
/**
 * RumbleTalk SDK v0.42 [07-DEC-2016]
 * Legacy support for older PHP versions
 */

namespace RumbleTalk;

use \Exception;

class RumbleTalkSDK
{
    const MISSING_TOKEN = 'Access token is not set, first call setAccessToken or fetchAccessToken to set the token';
    const VALIDATION_PASSWORD = '/.{6,12}/';
    const LEVEL_USER = 3;
    const LEVEL_ROOM_ADMIN = 4;
    const LEVEL_GROUP_ADMIN = 5;
    const TOKEN_RENEWAL_INTERVAL = 300;

    /* the API root URL. */
    private $host = 'https://api.rumbletalk.com/';

    /* verify SSL Certificate */
    private $ssl_verifypeer = false;

    /* timeout default. */
    private $timeout = 30;

    /* connect timeout. */
    private $connecttimeout = 30;

    /* the SDK user agent */
    private $useragent = 'rumbletalk-sdk-php-v0.42';

    /* the app key */
    private $key;

    /* the app secret */
    private $secret;

    /* current access token */
    private $accessToken;

    /**
     * @var int last call http code
     */
    private $last_http_code;

    /**
     * @var array last call headers
     */
    private $last_headers = array();

    /**
     * @var string last call cURL error
     */
    private $last_error;

    /**
     * @var int last call cURL error number
     */
    private $last_error_number;

    public function __construct($key = null, $secret = null)
    {
        $this->key = $key;
        $this->secret = $secret;
    }

    /**
     * creates a new RumbleTalk account
     *
     * @param array $data
     *
     * @return array the response from the server
     *      boolean status true on success
     *      integer accountId the id of the account
     *      integer chatId the id of the first chat in the account
     *      string hash the public id of the first chat in the account
     *      array token the account token (array of key and secret)
     *
     * @throws Exception when email or password is invalid
     */
    public function createAccount(array $data)
    {
        if (!$this->validateEmail($data['email'])) {
            throw new Exception('Invalid email address supplied');
        }

        if (!$this->validatePassword($data['password'])) {
            throw new Exception('Invalid password supplied');
        }

        return $this->http_request('POST', 'accounts', $this->accessToken, $data);
    }
    
    /**
     * Extracts the expiration date from a given access token
     *
     * @param string $token a JWT issued by the RumbleTalk server
     *
     * @return string expiration timestamp
     */
    public static function getTokenExpiration($token)
    {
        $expiration = explode('.', $token);
        $expiration = json_decode(base64_decode($expiration[1]), true);
        return $expiration['exp'];
    }
    
    /**
     * Checks whether an expiration date of a token has passed.
     *
     * @param integer $expiration timestamp of the token expiration
     *
     * @return boolean true if the token should be renewed; false otherwise
     */
    public static function renewalNeeded($expiration)
    {
        return $expiration - time() < self::TOKEN_RENEWAL_INTERVAL;
    }

    /**
     * Get an access token to an account
     * This functions is for enterprise accounts and third party connections only
     *
     * @param int $accountId the id of the account to get access to
     * @param string|null &$expiration if supplied, will be set to the token's expiration timestamp
     *
     * @return string access token
     *
     * @throws Exception
     */
    public function fetchAccountAccessToken($accountId = null, &$expiration = null)
    {
        $data = array(
            'key' => $this->key,
            'secret' => $this->secret
        );
        $extendRoute = '';

        if ($accountId) {
            $data['account_id'] = $accountId;
            $extendRoute = 'parent/';
        }

        $response = $this->http_request('POST', "{$extendRoute}token", null, $data);

        if (@$response['status'] != true) {
            throw new Exception("Error receiving access token: {$response['message']}", 400);
        }
        $this->accessToken = $response['token'];

        # set the expiration date
        if ($expiration) {
            $expiration = self::getTokenExpiration($this->accessToken);
        }

        return $this->accessToken;
    }

    /**
     * Get an access token
     * This function also sets the access token for the instance; there's no need to call the 'setAccessToken' function
     *
     * @param string|null &$expiration if supplied, will be set to the token's expiration timestamp
     *
     * @return string access token
     *
     * @throws Exception
     */
    public function fetchAccessToken(&$expiration = null)
    {
        return $this->fetchAccountAccessToken(null, $expiration);
    }

    /**
     * Sets the access token.
     * This functions is used when tokens are stored in your server.
     * It is required to save your tokens until they expire, because there's a limit on the number of tokens you can
     * create within a certain time
     *
     * @param string $token
     */
    public function setAccessToken($token)
    {
        $this->accessToken = $token;
    }

    /**
     * Perform a POST request to the API
     *
     * @param string $url the API route
     * @param array $data the data to pass to the server
     *
     * @return array the response from the server
     *
     * @throws Exception if missing token
     */
    public function post($url, $data)
    {
        if (!$this->accessToken) {
            throw new Exception(self::MISSING_TOKEN);
        }

        return $this->http_request('POST', $url, $this->accessToken, $data);
    }

    /**
     * Perform a GET request to the API
     *
     * @param string $url the API route (including query parameters)
     *
     * @return array the response from the server
     *
     * @throws Exception if missing token
     */
    public function get($url)
    {
        if (!$this->accessToken) {
            throw new Exception(self::MISSING_TOKEN);
        }

        return $this->http_request('GET', $url, $this->accessToken);
    }

    /**
     * Perform a PUT request to the API
     *
     * @param string $url the API route
     * @param array $data the data to pass to the server
     *
     * @return array the response from the server
     *
     * @throws Exception if missing token
     */
    public function put($url, $data)
    {
        if (!$this->accessToken) {
            throw new Exception(self::MISSING_TOKEN);
        }

        return $this->http_request('PUT', $url, $this->accessToken, $data);
    }

    /**
     * Perform a DELETE request to the API
     *
     * @param string $url the API route (including query parameters)
     *
     * @return array the response from the server
     *
     * @throws Exception if missing token
     */
    public function delete($url)
    {
        if (!$this->accessToken) {
            throw new Exception(self::MISSING_TOKEN);
        }

        return $this->http_request('DELETE', $url, $this->accessToken);
    }

    /**
     * Returns the last request HTTP status code
     *
     * @return int the last request's HTTP status code
     */
    public function getLastRequestStatusCode()
    {
        return $this->last_http_code;
    }

    /**
     * Returns the last request headers
     *
     * @return array the last request's headers
     */
    public function getLastRequestHeaders()
    {
        return $this->last_headers;
    }

    /**
     * Returns the last request cURL error
     *
     * @return string the last request's cURL error
     */
    public function getLastRequestError()
    {
        return $this->last_error;
    }

    /**
     * Returns the last request cURL error number
     *
     * @return string the last request's cURL error number
     */
    public function getLastRequestErrorNumber()
    {
        return $this->last_error_number;
    }

    /**
     * Inner function that creates the request
     *
     * @param string $method the method of the call
     * @param string $url the API route (including query parameters)
     * @param string|null $token a bearer token for authenticated requests
     * @param array|null $data the data to pass to the server
     *
     * @return array the response from the server
     *
     * @throws Exception
     */
    private function http_request($method, $url, $token = null, $data = null) {
        # make sure the method is in upper case for comparison
        $method = strtoupper($method);

        $this->validateMethod($method);

        # in case of a relative URL, prefix the host and encode the parts
        if (strrpos($url, 'https://') !== 0) {

            if (strpos($url, '?') !== false) {
                # detach the "path" part from the "search" part of the URL
                list($url, $search) = explode('?', $url, 2);

                # if there is a "search" part, encode it's KVPs
                if ($search) {
                    # separate each pair
                    $search = explode('&', $search);
                    foreach ($search as &$pair) {
                        # encode the key, and the value if exists.
                        $pair = explode('=', $pair);
                        $pair[0] = urlencode($pair[0]);
                        if ($pair[1]) {
                            $pair[1] = urlencode($pair[1]);
                        }
                        $pair = implode('=', $pair);
                    }
                    $search = implode('&', $search);
                }
                $url .= '?' . $search;
            }
            $url = "{$this->host}{$url}";
        }

        $headers = array();

        if ($token) {
            $headers[] = "Authorization: Bearer {$token}";
        }

        $ch = curl_init();

        if (!empty($data) && $this->methodWithData($method)) {
            $data = json_encode($data);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

            $headers[] = 'Content-Type: application/json';
            $headers[] = 'Content-Length: ' . strlen($data);
        }

        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        /* Curl settings */
        curl_setopt($ch, CURLOPT_USERAGENT, $this->useragent);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->connecttimeout);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->ssl_verifypeer);

        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, array($this, 'getHeader'));

        switch ($method) {
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, true);
                break;

            case 'PUT':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                break;

            case 'DELETE':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                break;
        }

        curl_setopt($ch, CURLOPT_URL, $url);

        $response = curl_exec($ch);

        # set the last http code; headers are set automatically by cURL
        $this->last_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($response === false) {
            $this->last_error = curl_error($ch);
            $this->last_error_number = curl_errno($ch);
        }

        curl_close($ch);

        $result = json_decode($response, true);

        return $result ?: "{$this->last_http_code}: {$response}";
    }

    /**
     * validates the method
     *
     * @param string $method the method to be validated
     *
     * @throws Exception if the method supplied is invalid
     */
    private function validateMethod($method)
    {
        if (!in_array($method, array('POST', 'GET', 'PUT', 'DELETE'))) {
            throw new Exception("Invalid method supplied: {$method}");
        }
    }

    /**
     * checks if the method can hold data
     *
     * returns true if the method can hold data
     */
    private function methodWithData($method)
    {
        return in_array($method, array('POST', 'PUT'));
    }

    /**
     * used by cURL
     * retrieve the returned headers;
     */
    private function getHeader($ch, $header) {
        $i = strpos($header, ':');
        if (!empty($i)) {
            $key = str_replace('-', '_', strtolower(substr($header, 0, $i)));
            $value = trim(substr($header, $i + 2));
            $this->last_headers[$key] = $value;
        }
        return strlen($header);
    }

    /**
     * Validates an email address
     *
     * @param string $email the email address to validate
     *
     * @return bool true if the email is valid, false otherwise
     */
    private function validateEmail(&$email)
    {
        if (!is_string($email)) {
            return false;
        }
        $email = trim($email);

        return filter_var($email, FILTER_VALIDATE_EMAIL);
    }

    /**
     * Validates that a password meets our demands
     *
     * @param string $password the password to validate
     *
     * @return bool true if the password is valid, false otherwise
     */
    private function validatePassword(&$password)
    {
        return is_string($password) && preg_match(self::VALIDATION_PASSWORD, $password);
    }
}