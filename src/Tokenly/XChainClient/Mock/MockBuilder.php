<?php

namespace Tokenly\XChainClient\Mock;

use Exception;
use Illuminate\Foundation\Application;
use Tokenly\CurrencyLib\CurrencyUtil;
use \PHPUnit_Framework_TestCase;

/**
* XChain Mock Builder
* for Laravel apps
*/
class MockBuilder
{

    protected $requests_remainning_before_throwing_exception = null;

    function __construct(Application $app) {
        $this->app = $app;
    }
    

    ////////////////////////////////////////////////////////////////////////

    public function beginThrowingExceptionsAfterCount($count=0) {
        $this->requests_remainning_before_throwing_exception = $count;
    }
    public function stopThrowingExceptions() {
        $this->requests_remainning_before_throwing_exception = null;
    }

    public function installXChainMockClient(PHPUnit_Framework_TestCase $test_case) {
        // record the calls
        $xchain_recorder = new \stdClass();
        $xchain_recorder->calls = [];

        $xchain_client_mock = $test_case->getMockBuilder('\Tokenly\XChainClient\Client')
            ->disableOriginalConstructor()
            ->setMethods(['newAPIRequest'])
            ->getMock();

        // override the newAPIRequest method
        $xchain_client_mock->method('newAPIRequest')->will($test_case->returnCallback(function($method, $path, $data) use ($xchain_recorder) {
            if ($this->requests_remainning_before_throwing_exception !== null) {
                if ($this->requests_remainning_before_throwing_exception <= 0) {
                    throw new Exception("Test Exception Triggered", 1);
                }
                --$this->requests_remainning_before_throwing_exception;
                if ($this->requests_remainning_before_throwing_exception < 0) { $this->requests_remainning_before_throwing_exception = 0; }
            }

            // store the method for test verification
            $xchain_recorder->calls[] = [
                'method' => $method,
                'path'   => $path,
                'data'   => $data,
            ];

            // call a method that returns sample data
            $sample_method_name = 'sampleData_'.strtolower($method).'_'.preg_replace('![^a-z0-9]+!i', '_', trim($path, '/'));
            if (method_exists($this, $sample_method_name)) {
                return call_user_func([$this, $sample_method_name], $data);
            }

            // defaults
            if (substr($path, 0, 7) == '/sends/') {
                return $this->sampleData_post_sends_xxxxxxxx_xxxx_4xxx_yxxx_111111111111($data);
            }
            if (substr($path, 0, 10) == '/balances/') {
                return $this->sampleData_get_balances_1xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx($data);
            }

            throw new Exception("No sample method for $method $path", 1);
        }));

        // install the xchain client into the DI container
        $this->app->bind('Tokenly\XChainClient\Client', function($app) use ($xchain_client_mock) {
            return $xchain_client_mock;
        });


        // return an object to check the calls
        return $xchain_recorder;
    }

    ////////////////////////////////////////////////////////////////////////

    public function sampleData_post_addresses($data) {
        return [
            "id"      => "xxxxxxxx-xxxx-4xxx-yxxx-111111111111",
            "address" => "1oLaf1CoYcVE3aH5n5XeCJcaKPPGTxnxW",
        ];
    }
    public function sampleData_post_monitors($data) {
        return [
            "id"              => "xxxxxxxx-xxxx-4xxx-yxxx-222222222222",
            "active"          => true,
            "address"         => "1oLaf1CoYcVE3aH5n5XeCJcaKPPGTxnxW",
            "monitorType"     => "receive",
            "webhookEndpoint" => "http://mywebsite.co/notifyme"
        ];
    }
    public function sampleData_post_sends_xxxxxxxx_xxxx_4xxx_yxxx_111111111111($data) {
        return [
            "id"          => "xxxxxxxx-xxxx-4xxx-yxxx-333333333333",
            "txid"        => "0000000000000000000000000000001111",
            "destination" => $data['destination'],
            "quantity"    => $data['quantity'],
            "quantitySat" => CurrencyUtil::valueToSatoshis($data['quantity']),
            "asset"       => $data['asset'],
            "is_sweep"    => !!$data['sweep'],
        ];
    }
    public function sampleData_get_balances_1xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx($data) {
        return [
            'balances' => [
                'BTC'     => 0.01,
                'LTBCOIN' => 1000,
                'SOUP'    => 5000,
            ],
            'balancesSat' => [
                'BTC'     => 0.01 * 100000000,
                'LTBCOIN' => 1000 * 100000000,
                'SOUP'    => 5000 * 100000000,
            ],
        ];
    }

}