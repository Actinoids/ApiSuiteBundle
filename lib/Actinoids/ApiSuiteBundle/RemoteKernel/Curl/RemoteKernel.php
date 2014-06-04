<?php
namespace Actinoids\ApiSuiteBundle\RemoteKernel\Curl;

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
