<?php
namespace Actinoids\ApiSuiteBundle\RemoteKernel\OAuth1a;

class Config {

    /**
     * Signature method for signing the base string (of request parameters) and key of requests
     *
     * @var string Acceptable values 'HMAC-SHA1', 'RSA-SHA1', 'PLAINTEXT'
     */
    protected $signatureMethod = 'HMAC-SHA1';

    /**
     * Per http://oauth.net/core/1.0a 5.2, three methods are provided for requests:
     * HTTP Authorization header (preferred), HTTP POST Request Body, and Query String
     *
     * @var string Acceptable values 'HEADER', 'POSTBODY', 'QUERY'
     * @todo Add support for POST and Query
     */
    protected $requestScheme = 'HEADER';

    /**
     * Per http://oauth.net/core/1.0a 6.0 The request method, either GET or POST
     * The preferred method is POST
     *
     * @var string Acceptable values 'POST', 'GET'
     */
    protected $requestMethod = 'POST';

    /**
     * Oauth Version. This defaults, and must be set, to 1.0, per http://oauth.net/core/1.0a 6.1.1
     *
     * @var string
     */
    protected $version = '1.0';
   
    /**
     * Oauth Callback URL. And absolute URL to redirect the user once Authorization is complete.
     * Per http://oauth.net/core/1.0a 6.1.1. If null, this will be set as out-of-band (oob)
     *
     * @var string
     */
    protected $callbackUrl;
   
    /**
     * The Request Token URL for retrieving Request Tokens
     * A Request Token is a value used by the Consumer to obtain authorization from the User, and exchanged for an Access Token.
     *
     * @var string
     */
    protected $requestTokenUrl;
   
    /**
     * The Access Token URL for retrieving Access Tokens
     * An Access Token is the value used by the Consumer to gain access to the Protected Resources on behalf of the User, instead of using the User's Service Provider credentials.
     *
     * @var string
     */
    protected $accessTokenUrl;
   
    /**
     * The Authorize URL that a user is redirected to in order to authorize a Request Token
     *
     * @var string
     */
    protected $authorizeUrl;
   
    /**
     * The Consumer Key. A value used by the Consumer to identify itself to the Service Provider.
     *
     * @var string
     */
    protected $consumerKey;
   
    /**
     * The Consumer Secret. A secret used by the Consumer to establish ownership of the Consumer Key.
     *
     * @var string
     */
    protected $consumerSecret;
   
    /**
     * The Oauth Realm. Per http://oauth.net/core/1.0 5.4.1 as interpreted per RFC2617
     *
     * @var string
     */
    protected $realm;

    /**
     * An array of required configuration options
     *
     * @var array
     */
    protected $requiredConfigOptions = ['requestTokenUrl', 'accessTokenUrl', 'authorizeUrl', 'consumerKey', 'consumerSecret', 'realm'];

    /**
     * Constructor; create a new Oauth_Config object with optional configuration settings
     *
     * @param  array $options The options for the Oauth object
     * @return void
     */
    public function __construct(array $options = array())
    {
        if (!empty($options)) {
           $this->setOptionsFromArray($options);
        }
    }
    
    /**
     * Iterate through options array and set the properties using the corresponding method
     *
     * @param  array $options The options for the Oauth object
     * @return self
     */
    public function setOptionsFromArray(array $options)
    {
        foreach ($options as $option => $value) {
           $method = 'set' . ucwords($option);
            if (method_exists($this, $method)) {
                call_user_func(array($this, $method), $value);
            }
        }
       
        // Fire request scheme option again (if set) to ensure that the request method property is already set
        if (array_key_exists('requestScheme', $options)) {
            $this->setRequestScheme($options['requestScheme']);
        }
        return $this;
    }

    /**
     * Determines if this is a valid configuration
     *
     * @return bool
     */
    public function isValid()
    {
        foreach ($this->requiredConfigOptions as $option) {
            $method = 'get' . ucwords($option);
            if (method_exists($this, $method)) {
                $value = $this->$method();
                if (is_null($value)) return false;
            } else {
                return false;
            }
        }
        return true;
    }

    public function getRequiredConfigOptions()
    {
        return $this->requiredConfigOptions;
    }

    /**
     * Set the Oauth Signature Method. Supported methods 'HMAC-SHA1', 'RSA-SHA1', 'PLAINTEXT'
     *
     * @param  string $method The signature method
     * @return self
     * @throws Exception if unsupported signature method passed
     */
    public function setSignatureMethod($method) 
    {
        $method = strtoupper($method);
        $supportedMethods = array(
            'HMAC-SHA1',
            'RSA-SHA1',
            'PLAINTEXT',
        );
        if (!in_array($method, $supportedMethods)) {
            throw new Exception(sprintf('Unsupported signature method. Only %s are supported.', implode(', ', $supportedMethods)));
        }
      
        $this->signatureMethod = $method;
        return $this;
    }
   
    /**
     * Get the Oauth Signature Method.
     *
     * @return string The Oauth Signature Method
     */
    public function getSignatureMethod()
    {
        return $this->signatureMethod;
    }

    /**
     * Set the Oauth Request Scheme.
     * Supported schemes are HTTP Authorization Header (preferred), HTTP POST Request Body, and Query String, per http://oauth.net/core/1.0a 5.2
     *
     * @param  string $scheme The scheme; acceptable values are HEADER, POSTBODY, or QUERY
     * @return self
     * @throws \Exception If unsupported scheme passed or POST Body set with Request Method of GET
     */
    public function setRequestScheme($scheme)
    {
        $scheme = strtoupper($scheme);
        
        $supportedSchemes = array(
            'HEADER',
            'POSTBODY',
            'QUERY',
        );

        if (!in_array($scheme, $supportedSchemes)) {
            throw new \Exception(sprintf('Unsupported request scheme. Only %s are supported.', implode(', ', $supportedSchemes)));
        }

        if ($scheme == 'POSTBODY' && $this->getRequestMethod() == 'GET') {
            throw new \Exception('Cannot set POSTBODY request scheme in conjunction with a GET request method.');
        }
        $this->requestScheme = $scheme;
        return $this;
    }

    /**
     * Get the Oauth Request Scheme.
     *
     * @return string The Oauth Request Scheme
     */
    public function getRequestScheme()
    {
       return $this->requestScheme;
    }

    /**
     * Set the Oauth Request Method.
     * POST and GET are supported. POST is preferred, per http://oauth.net/core/1.0a 6.0
     *
     * @param  string $method The request method; acceptable values are POST or GET
     * @return self
     * @throws Exception if unsupported request method passed
     */
    public function setRequestMethod($method)
    {
        $method = strtoupper($method);
        $supportedMethods = array(
            'GET',
            'POST',
        );
        if (!in_array($method, $supportedMethods)) {
            throw new Exception(sprintf('Invalid request method. Only %s are supported.', implode(', ', $supportedMethods)));
        }
        $this->requestMethod = $method;
        return $this;
    }

    /**
     * Get the Oauth Request Method.
     *
     * @return string The Oauth Request Method
     */
    public function getRequestMethod()
    {
        return $this->requestMethod;
    }
   
    /**
     * Get the Oauth Version.
     *
     * @return string The Oauth Version
     */
    public function getVersion()
    {
        return $this->version;
    }

    /**
     * Set the Oauth Callback URL.
     *
     * @param  string $url The Callback URL
     * @return self
     * @todo   Ensure the passed URL is valid
     */
    public function setCallbackUrl($url)
    {
        if ($url !== 'oob') {
            // @todo Ensure the URL is valid
        }
        $this->callbackUrl = $url;
        return $this;
    }
   
    /**
     * Get the Oauth Callback URL.
     *
     * @return string The Oauth Callback URL
     * @todo   Ensure the passed URL is valid
     */
    public function getCallbackUrl()
    {
        if (is_null($this->callbackUrl)) {
            return 'oob';
        } else {
            return $this->callbackUrl;
        }
    }

    /**
     * Set the Oauth Request Token URL.
     *
     * @param  string $url The request token URL
     * @return self
     * @throws Exception if invalid URL (todo)
     * @todo   Esure URL is valid
     */
    public function setRequestTokenUrl($url)
    {
        $this->requestTokenUrl = $url;
        return $this;
    }
   
    /**
     * Get the Oauth Request Token URL
     *
     * @return string The Oauth Request Token URL
     */
    public function getRequestTokenUrl()
    {
      return $this->requestTokenUrl;
    }
   
    /**
     * Set the Oauth Access Token URL.
     *
     * @param  string $url The access token URL
     * @return self
     * @throws Exception if invalid URL (todo)
     * @todo   Esure URL is valid
     */
    public function setAccessTokenUrl($url)
    {
        $this->accessTokenUrl = $url;
        return $this;
    }
   
    /**
     * Get the Oauth Access Token URL
     *
     * @return string The Oauth Access Token URL
     */
    public function getAccessTokenUrl()
    {
        return $this->accessTokenUrl;
    }
    
    /**
     * Set the Oauth User Authorization URL.
     *
     * @param  string $url The User Authorization URL
     * @return self
     * @throws Exception if invalid URL (todo)
     * @todo   Esure URL is valid
     */
    public function setAuthorizeUrl($url)
    {
        $this->authorizeUrl = $url;
        return $this;
    }
   
    /**
     * Get the Oauth User Authorization URL
     *
     * @return string The Oauth User Authorization URL
     */
    public function getAuthorizeUrl()
    {
        return $this->authorizeUrl;
    }
   
    /**
     * Set the Oauth Consumer Key
     *
     * @param  string $key The Consumer key
     * @return self
     */
    public function setConsumerKey($key)
    {
        $this->consumerKey = $key;
        return $this;
    }
   
    /**
     * Get the Oauth Consumer Key
     *
     * @return string The Consumer Key
     */
    public function getConsumerKey()
    {
        return $this->consumerKey;
    }
   
    /**
     * Set the Oauth Consumer Secret
     *
     * @param  string $secret The Consumer Secret
     * @return self
     */
    public function setConsumerSecret($secret)
    {
        $this->consumerSecret = $secret;
        return $this;
    }
   
    /**
     * Get the Oauth Consumer Secret
     *
     * @return string The Consumer Secret
     */
    public function getConsumerSecret()
    {
        return $this->consumerSecret;
    }
   
    /**
     * Set the Oauth Realm
     *
     * @param  string $realm The Oauth Realm
     * @return self
     */
    public function setRealm($realm)
    {
        $this->realm = $realm;
        return $this;
    }
   
    /**
     * Get the Oauth Realm
     *
     * @return string The Oauth Realm
     */
    public function getRealm()
    {
        return $this->realm;
    }
}
