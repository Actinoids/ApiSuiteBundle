<?php
namespace Actinoids\ApiSuiteBundle\RemoteKernel\OAuth1a;

use Actinoids\ApiSuiteBundle\RemoteKernel\OAuth1a\RemoteKernel as OAuthConsumer;

class RequestToken
{
    /**
     * The Oauth_Consumer object passed to this class
     *
     * @var Actinoids\ApiSuiteBundle\RemoteKernel\OAuth1a\RemoteKernel
     */
    protected $consumer;
   
    /**
     * An array of additional parameters to send with the Oauth request
     * These are considered "non-standard" because they aren't defined in the Oauth spec
     *
     * @var array
     */
    protected $nonStandardParams;
    
    /**
     * Constructor; set the parameters for the request, and retrieve the Oauth Request Token
     *
     * @param  RemoteKernel $consumer The current instance of Ouath Consumer
     * @param  array $nonStandardParams Additional parameters to be sent with the request
     * @return void
     */
    public function __construct(OAuthConsumer $consumer, array $nonStandardParams = array())
    {
        $this->consumer = $consumer;
        $this->nonStandardParams = $nonStandardParams;
    }

    /**
     * Retrieve the unauthorized request token from the Service Provider
     *
     * @return array The service provider response, using Curl_Client
     * @throws Exception If service provider returns a non-200 response
     */
    public function retrieve()
    {
        $params = $this->buildRequestParams();
        $authHeader = $this->consumer->createAuthHeader($params);

        $response = $this->consumer->sendRequest($this->consumer->getRequestTokenUrl(), $authHeader, 'POST');

        if ($response->getStatusCode() != 200) {
            throw new \Exception('Unable to retrieve Request Token from Service Provider. The server responsed: ' . $response->getStatusCode() . ' ' . $response->getContent());
        } else {
            return new Token($response);
        }
    }

    /**
     * Assemble the Oauth Request Token parameters and sign the request
     *
     * @return array The built request parameters
     */
    public function buildRequestParams()
    {
        $params = array(
            'oauth_consumer_key'     => $this->consumer->getConsumerKey(),
            'oauth_signature_method' => $this->consumer->getSignatureMethod(),
            'oauth_timestamp'        => $this->consumer->generateTimestamp(),
            'oauth_nonce'            => $this->consumer->generateNonce(),
            'oauth_version'          => $this->consumer->getVersion(),
            'oauth_callback'         => $this->consumer->getCallbackUrl(),
        );

        if (!empty($this->nonStandardParams)) {
            $params = array_merge($params, $this->nonStandardParams);
        }

        $params['oauth_signature'] = $this->consumer->sign(
            $params,
            $this->consumer->getRequestMethod(),
            $this->consumer->getRequestTokenUrl(),
            $this->consumer->getConsumerSecret()
        );
        return $params;
    }

}
