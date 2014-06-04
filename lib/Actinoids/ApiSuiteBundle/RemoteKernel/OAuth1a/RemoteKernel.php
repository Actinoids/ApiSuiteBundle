<?php
namespace Actinoids\ApiSuiteBundle\RemoteKernel\OAuth1a;

use Symfony\Component\HttpFoundation\Request;
use Actinoids\ApiSuiteBundle\RemoteKernel\Curl\Client;
use Symfony\Component\HttpFoundation\Response;
use Actinoids\ApiSuiteBundle\RemoteKernel\RemoteKernelInterface;
use Actinoids\ApiSuiteBundle\RemoteKernel\BaseRemoteKernel;

class RemoteKernel extends BaseRemoteKernel implements RemoteKernelInterface
{
    private $client;

    /**
     * The configuration options
     *
     * @var Actinoids\ApiSuiteBundle\RemoteKernel\OAuth1a\Config
     */
    protected $config;

    /**
     * Request Token from Oauth Service Provider
     *
     * @var Actinoids\ApiSuiteBundle\RemoteKernel\OAuth1a\Token
     */
    protected $requestToken;

    /**
     * Access Token from Oauth Service Provider
     *
     * @var Actinoids\ApiSuiteBundle\RemoteKernel\OAuth1a\Token
     */
    protected $accessToken;


    public function __construct(Client $client, array $config = array())
    {
        $this->client = $client;
        $this->setConfig($config);
    }

    /**
     * Retrieve an Oauth Request Token and set it, per http://oauth.net/core/1.0a 6.1
     *
     * @param  array $nonStandardParams Additional, non-standard parameters to be sent with the request
     * @return self
     */
    public function getRequestToken(array $nonStandardParams = array())
    {
        if (is_null($this->requestToken)) {
            $token = new RequestToken($this, $nonStandardParams);
            $this->requestToken = $token->retrieve();
        }
        return $this->requestToken;
    }

    /**
     * Retrieve an Oauth Access Token and set it, per http://oauth.net/core/1.0a 6.3
     *
     * @param  array $authData The authorization data
     * @return self
     */
    public function getAccessToken($authData)
    {
        if (is_null($this->accessToken)) {
            $token = new AccessToken($this, $authData);
            $this->accessToken = $token->retrieve();
        }
        return $this->accessToken;
    }

    /**
     * Retrieve the authorization URL so the User can authorize the Request Token, per http://oauth.net/core/1.0a 6.2
     *
     * @return string The authorization URL
     * @throws Exception If Auth URL is not found
     */
    public function getAuthRedirectUrl()
    {
        if ($this->requestToken instanceof Token) {

            $oauthParams = array(
                'oauth_token' => $this->requestToken->getToken(),
            );
            $additionalParams = $this->requestToken->getAdditionalParams()->all();
            $params = array_merge($oauthParams, $additionalParams);
         
            $url = $this->config->getAuthorizeUrl() . '?' . $this->normalizeRequestParamaters($params);
            return $url;
        } else {
            throw new \Exception('A valid request token was not found.');
        }
    }

    /**
     * Handles a request by creating a Request object and sending it to the Kernel
     *
     * @param  string $uri   
     * @param  array  $headers    
     * @param  string $method     The request method
     * @return Symfony\Component\HttpFoundation\Response
     */
    public function sendRequest($uri, $headers = array(), $method = 'GET')
    {
        if ($this->hasValidConfig()) {
            $request = $this->createSimpleRequest($uri, $method);
            $request->headers->add($headers);
            return $this->handle($request);
        } else {
            throw new \Exception(sprintf('The OAuth Consumer configuration is not valid. The following options must be set: %s', implode(', ', $this->getRequiredConfigOptions())));
        }
    }

    /**
     * Create the Authorization header, per http://oauth.net/core/1.0a 5.4.1
     *
     * @param  array $params The parameters of the oAuth request
     * @return array The authorization request header.
     */
    public function createAuthHeader(array $params, $includeAddlParams = true)
    {
        $header = array('Authorization' => 'OAuth ');

        if ($this->config->getRealm()) {
            $header['Authorization'] .= 'realm="' . $this->config->getRealm() . '",';
        }

        $formattedParams = array();
        foreach ($params as $key => $value) {
            $formattedParams[] = urlencode($key) . '="' . urlencode($value) . '"';
        }
        $header['Authorization'] .= implode(",", $formattedParams);

        return $header;
    }

    /**
     * Use specified signature algorithm to sign the OAuth request, per http://oauth.net/core/1.0a 9.2 - 9.4
     *
     * @param  array  $params      The parameters of the oAuth request (including the signature method)
     * @param  string $method      The HTTP method of the request, such as GET, POST
     * @param  string $url         The Request URL
     * @param  string $secret      The Consumer Secret
     * @param  string $tokenSecret The Token Secret
     * @return string A generated signature based on the signature method
     * @todo   Provide support for RSA-SHA1 and PLAINTEXT
     */
    public function sign(array $params, $method, $url, $secret, $tokenSecret = null)
    {
        switch ($params['oauth_signature_method']) {
            case "HMAC-SHA1":
                $baseString = $this->createBaseString($params, $method, $url);
                $key = $this->assembleKey($secret, $tokenSecret);
                $signature = base64_encode(hash_hmac('sha1', $baseString, $key, 1));
                break;
            case "RSA-SHA1":
                throw new \Exception('RSA-SHA1 currently not a supported signature method');
                break;
            case "PLAINTEXT":
                throw new \Exception('PLAINTEXT currently not a supported signature method');
                break;
            default:
                throw new \Exception('Invalid signature method provided');
                break;
        }
        return $signature;
    }

    /**
     * Concatenate Request Elements to create the Signature Base String, per http://oauth.net/core/1.0a 9.1.3
     *
     * @param  array  $params The parameters of the oAuth request
     * @param  string $method The HTTP method of the request, such as GET, POST
     * @param  string $url    The Request URL
     * @return string The Signature Base String
     */
    public function createBaseString(array $params, $method, $url)
    {
        $normalizedParams = $this->normalizeRequestParamaters($params);
        $requestUrl = $this->constructRequestUrl($url);
        return strtoupper($method) . "&" . urlencode($requestUrl) . "&" . urlencode($normalizedParams);
    }

    /**
     * Generates the key for HMAC-SHA1 signing based off the Consumer Secret and Token Secret, per http://oauth.net/core/1.0a 9.2
     *
     * @param  string $consumerSecret The secret used by the Consumer to establish ownership of the Consumer Key
     * @param  string $tokenSecret    A secret used by the Consumer to establish ownership of a given Token
     * @return string The key
     */
    public function assembleKey($consumerSecret, $tokenSecret = null)
    {
        return urlencode($consumerSecret) . "&" . (($tokenSecret) ? urlencode($tokenSecret) : "");
    }

    /**
     * Normalize Request Parameters for the Signature Base String, per http://oauth.net/core/1.0a 9.1.1
     * oauth_signature and realm parameters are to be excluded.
     *
     * @param  array $params The parameters of the oAuth request
     * @return string A sorted and concatenated string of parameters.
     */
    public function normalizeRequestParamaters(array $params)
    {
        $excludeParams = array('realm', 'oauth_signature');
        foreach ($excludeParams as $param) {
            if (array_key_exists($param, $params)) unset($params[$param]);
        }
        foreach ($params as $key => $value) {
            $params[$key] = urlencode($value);
        }
      
        ksort($params);
        return http_build_query($params); 
    }

    /**
     * Construct Request URL for the Signature Base String, per http://oauth.net/core/1.0a 9.1.2
     *
     * @param  string $url The Request URL
     * @return string Formatted Request URL only including scheme, host, and path. Will include port if not defualt.
     */
    public function constructRequestUrl($url) {
        $parsed = parse_url($url);
      
        $constructedUrl = strtolower($parsed['scheme']) . "://" . $parsed['host'];
      
        if (isset($parsed['port'])) {
            if ($parsed['scheme'] == "https" && $parsed['port'] != 443) {
                $constructedUrl .= ":" . $parsed['port'];
            }
            if ($parsed['scheme'] == "http" && $parsed['port'] != 80) {
                $constructedUrl .= ":" . $parsed['port'];
            }
        }
      
        $constructedUrl .= $parsed['path'];
        return $constructedUrl;
   }

    /**
     * Determines if the API instance has a valid configuration
     *
     * @return bool
     */
    public function hasValidConfig()
    {
        return $this->config->isValid();
    }

    /**
     * Generate nonce for signing purposes, per http://oauth.net/core/1.0a 8
     *
     * @return string
     */
    public function generateNonce()
    {
        return md5(uniqid(rand(), true));
    }

    /**
     * Generate timestamp for signing purposes, per http://oauth.net/core/1.0a 8
     *
     * @return int
     */
    public function generateTimestamp()
    {
        return time();
    }

    /**
     * Proxy to Actinoids\ApiSuiteBundle\ApiClient\OAuth1a\Config methods.
     *
     * @param  string $method
     * @param  array  $args
     * @return mixed
     * @throws Exception If method does not exist in Actinoids\ApiSuiteBundle\ApiClient\OAuth1a\Config
     */
    public function __call($method, array $args)
    {
        if (method_exists($this->config, $method)) {
            return call_user_func_array(array($this->config,$method), $args);
        } else {
            throw new \Exception('Method does not exist in Oauth Config: ' . $method);
        }  
    }

    /**
     * Sets the configuration options for this kernal
     *
     * @param  array $config The config options
     * @return self
     */
    public function setConfig(array $config) 
    {
        $this->config = new Config($config);
        return $this;
    }
}
