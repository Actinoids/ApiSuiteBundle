<?php
namespace Actinoids\ApiSuiteBundle\RemoteKernel\Curl;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Actinoids\ApiSuiteBundle\RemoteKernel\RemoteKernelInterface;
use Actinoids\ApiSuiteBundle\RemoteKernel\BaseRemoteKernel;

class RemoteKernel extends BaseRemoteKernel implements RemoteKernelInterface
{
    private $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }
}
