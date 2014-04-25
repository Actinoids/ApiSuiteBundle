<?php
namespace Actinoids\ApiSuiteBundle\RemoteKernel\OAuth1a;

use Actinoids\ApiSuiteBundle\RemoteKernel\OAuth1a\RemoteKernel as OAuthConsumer;

class AccessToken
{
    /**
     * The Oauth RemoteKernel object passed to this class
     *
     * @var Actinoids\ApiSuiteBundle\RemoteKernel\OAuth1a\RemoteKernel
     */
    protected $consumer;
   
    /**
     * An array of the Authorization data
     * This is obtained by the User authorizing a Consumer Request Token
     *
     * @var array
     */
    protected $authData;
    
    /**
     * Constructor; set the parameters for the request, and retrieve the Oauth Access Token
     *
     * @param  RemoteKernel $consumer The current instance of Ouath Consumer
     * @param  array $authData
     * @return void
     */
    public function __construct(OAuthConsumer $consumer, array $authData)
    {
        $this->consumer = $consumer;
        $this->setAuthData($authData);
    }

    /**
     * Set the authorization data
     *
     * @param  array $authData The authorization data
     * @return self
     * @throws Exception If auth data is invalid
     */
    private function setAuthData($authData)
    {
        if (!empty($authData['oauth_token']) && !empty($authData['oauth_verifier'])) {
            $this->authData = $authData;
        } else {
            throw new \Exception('Authorization data invalid. Must contain oauth_token and oauth_verifier values. Unable to retrieve Access Token');
        }  
    }

    /**
     * Get the oauth_verifier value
     *
     * @return string The Oauth Verifier
     */
    private function getVerifier()
    {
        return $this->authData['oauth_verifier'];
    }

    /**
     * Retrieve the access token from the Service Provider
     *
     * @return array The service provider response, using Curl_Client
     * @throws Exception If service provider returns a non-200 response
     */
    public function retrieve()
    {
        $params = $this->buildRequestParams();
        $authHeader = $this->consumer->createAuthHeader($params);

        $response = $this->consumer->sendRequest($this->consumer->getAccessTokenUrl(), $authHeader, 'POST');
      
        if ($response->getStatusCode() != 200) {
            throw new \Exception('Unable to retrieve Access Token from Service Provider. The server responsed: ' . $response->getStatusCode() . ' ' . $response->getContent());
        } else {
            return $token = new Token($response);
        }
    }

    /**
     * Assemble the Oauth Access Token parameters and sign the request
     *
     * @return array The built request parameters
     */
    public function buildRequestParams()
    {
        $requestToken = $this->consumer->getRequestToken();
        $params = array(
            'oauth_consumer_key'     => $this->consumer->getConsumerKey(),
            'oauth_token'            => $requestToken->getToken(),
            'oauth_signature_method' => $this->consumer->getSignatureMethod(),
            'oauth_timestamp'        => $this->consumer->generateTimestamp(),
            'oauth_nonce'            => $this->consumer->generateNonce(),
            'oauth_version'          => $this->consumer->getVersion(),
            'oauth_verifier'         => $this->getVerifier(),
        );

        $params['oauth_signature'] = $this->consumer->sign(
            $params,
            $this->consumer->getRequestMethod(),
            $this->consumer->getAccessTokenUrl(),
            $this->consumer->getConsumerSecret(),
            $requestToken->getTokenSecret()
        );

        return $params;
    }
}
