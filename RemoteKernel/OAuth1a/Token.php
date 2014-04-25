<?php
namespace Actinoids\ApiSuiteBundle\RemoteKernel\OAuth1a;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ParameterBag;

class Token {

    /**@+
     * Token constants
     */
    const TOKEN_PARAM_KEY                = 'oauth_token';
    const TOKEN_SECRET_PARAM_KEY         = 'oauth_token_secret';
    const TOKEN_PARAM_CALLBACK_CONFIRMED = 'oauth_callback_confirmed';
    /**@-*/
     
    /**
     * The oauth token parameters
     *
     * @var ParameterBag
     */
    protected $params;
    
    /**
     * The additional token parameters (non-ouath specific)
     *
     * @var ParameterBag
     */
    protected $additional;
 
    /**
     * The Oauth Response
     *
     * @var Symfony\Component\HttpFoundation\Response
     */
    protected $response;
   
    /**
     * Constructor; set the token reponse and parse it into parameters
     *
     * @param  Symfony\Component\HttpFoundation\Response $response The reponse from the Service Provider
     * @return void
     */
    public function __construct(Response $response)
    {
        $this->params = new ParameterBag();
        $this->additional = new ParameterBag();

        $this->response = $response;
        $this->parseResponse();
    }

    /**
     * Parses the HTTP response body and sets the token parameters
     *
     * @return Oauth_Token
     * @throws Exception If unable to parse the response body
     */
    private function parseResponse()
    {
        $content = $this->response->getContent();
        parse_str($content, $rArray);

        if (is_array($rArray) && !empty($rArray)) {
            foreach ($rArray as $param => $value) {
                $this->setParam($param, rawurldecode($value));
            }
        } else {
            throw new \Exception('Unable to parse reponse. Tried response: ' . $reponse);
        }
        return $this;
    }

    /**
     * Sets a token parameter
     *
     * @param string $param The parameter name
     * @param mixed $value The parameter value
     * @return Oauth_Token
     */
    public function setParam($key, $value)
    {
        if ($key == self::TOKEN_PARAM_KEY || $key == self::TOKEN_SECRET_PARAM_KEY || $key == self::TOKEN_PARAM_CALLBACK_CONFIRMED) {
            $this->params->set($key, $value);
        } else {
            $this->additional->set($key, $value);
        }
        return $this;
    }
   
    /**
    * Gets a token parameter
    *
    * @param string $param The parameter name
    * @return mixed The parameter value
    */
    public function getParam($key) {
        if ($key == self::TOKEN_PARAM_KEY || $key == self::TOKEN_SECRET_PARAM_KEY || $key == self::TOKEN_PARAM_CALLBACK_CONFIRMED) {
            return $this->params->get($key);
        } else {
            return $this->additional->get($key);
        }
    }

    /**
     * Gets the token parameters
     *
     * @return ParameterBag The token params
     */
    public function getParams()
    {
        return $this->params;
    }
   
    /**
     * Gets all additional token parameters
     *
     * @return ParameterBag The addl token params
     */
    public function getAdditionalParams()
    {
        return $this->additional;
    }
   
    /**
     * Gets the token
     *
     * @return string The Oauth token
     */
    public function getToken() 
    {
        return $this->getParam(self::TOKEN_PARAM_KEY);
    }
   
    /**
     * Gets the token secret
     *
     * @return string The Oauth token secret
     */
    public function getTokenSecret() 
    {
        return $this->getParam(self::TOKEN_SECRET_PARAM_KEY);
    }
}
