<?php
namespace Actinoids\ApiSuiteBundle\ApiClient;

use Actinoids\ApiSuiteBundle\RemoteKernel\RemoteKernelInterface;
use Symfony\Component\HttpFoundation\Request;

interface ApiClientInterface
{
    /**
     * Sets the remote RemoteKernelInterface for sending Request objects and returning Response objects
     *
     * @param  Actinoids\ApiSuiteBundle\RemoteKernel\RemoteKernelInterface $httpKernel
     * @return void
     */
    public function setRemoteHttpKernel(RemoteKernelInterface $httpKernel);

    /**
     * Takes a Request object and performs the request via the RemoteKernelInterface
     * This should return a Response object
     *
     * @param  Symfony\Component\HttpFoundation\Request $request
     * @return Symfony\Component\HttpFoundation\Response
     * @throws \Exception On errors
     */
    public function doRequest(Request $request);

    public function setConfig(array $config);

    public function hasValidConfig();
}
