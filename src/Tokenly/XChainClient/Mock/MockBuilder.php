<?php

namespace Tokenly\XChainClient\Mock;

use Exception;
use Illuminate\Foundation\Application;
use Tokenly\CurrencyLib\CurrencyUtil;
use Tokenly\XChainClient\Exception\XChainException;
use Tokenly\XChainClient\Mock\MockTestCase;
use \PHPUnit_Framework_MockObject_MockBuilder;
use \PHPUnit_Framework_TestCase;

/**
* XChain Mock Builder
* for Laravel apps
*/
class MockBuilder
{

    const DEFAULT_REGULAR_DUST_SIZE = 0.00005430;
    const DEFAULT_FEE_SIZE          = 0.0001;

    protected $requests_remainning_before_throwing_exception = null;
    protected $test_exception_ignore_xchain_call_prefixes = [];
    protected $output_transaction_id = '0000000000000000000000000000001111';

    function __construct(Application $app) {
        $this->app = $app;
    }
    

    ////////////////////////////////////////////////////////////////////////

    public function beginThrowingExceptionsAfterCount($count=0, $test_exception_ignore_xchain_call_prefixes=null) {
        $this->requests_remainning_before_throwing_exception = $count;
        $this->test_exception_ignore_xchain_call_prefixes = $test_exception_ignore_xchain_call_prefixes;
    }
    public function stopThrowingExceptions() {
        $this->requests_remainning_before_throwing_exception = null;
        $this->test_exception_ignore_xchain_call_prefixes = null;
    }

    public function setBalances($balances, $payment_address_id='default') {
        if (!isset($this->balances)) { $this->balances = []; }
        $this->balances[$payment_address_id] = $balances;
    }
    public function clearBalances() {
        $this->balances = null;
        $this->balances_by_txid = [];
        $this->received_by_txid_map = [];
    }

    public function importBalances($data) {
        if (isset($data['balances'])) { $this->balances = $data['balances']; }
        if (isset($data['balances_by_txid'])) { $this->balances_by_txid = $data['balances_by_txid']; }
        if (isset($data['received_by_txid_map'])) { $this->received_by_txid_map = $data['received_by_txid_map']; }
    }
    public function exportBalances() {
        return [
            'balances'             => $this->balances,
            'balances_by_txid'     => $this->balances_by_txid,
            'received_by_txid_map' => $this->received_by_txid_map,
        ];
    }

    public function setOutputTransactionID($output_transaction_id) {
        $this->output_transaction_id = $output_transaction_id;
    }

    public function installXChainMockClient(PHPUnit_Framework_TestCase $test_case=null) {
        // record the calls
        $xchain_recorder = new \stdClass();
        $xchain_recorder->calls = [];

        if ($test_case === null) { $test_case = new MockTestCase(); }

        if ($test_case) {
            $xchain_client_mock = $test_case->getMockBuilder('\Tokenly\XChainClient\Client')
                ->disableOriginalConstructor()
                ->setMethods(['newAPIRequest'])
                ->getMock();
        }



        // override the newAPIRequest method
        $xchain_client_mock->method('newAPIRequest')->will($test_case->returnCallback(function($method, $path, $data) use ($xchain_recorder) {
            if ($this->requests_remainning_before_throwing_exception !== null AND !$this->pathIsIgnored($path, $this->test_exception_ignore_xchain_call_prefixes)) {
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
                $account = isset($data['account']) ? $data['account'] : 'default';
                $payment_address_id = substr($path, 7);
                $this->debitBalance($data['quantity'], $data['asset'], $account, 'confirmed', $payment_address_id);

                $btc_debit = (isset($data['fee']) ? $data['fee'] : self::DEFAULT_FEE_SIZE);
                if ($data['asset'] != 'BTC') { $btc_debit += (isset($data['dust_size']) ? $data['dust_size'] : self::DEFAULT_REGULAR_DUST_SIZE); }
                $this->debitBalance($btc_debit, 'BTC', $account, 'confirmed', $payment_address_id);

                return $this->sampleData_post_sends_xxxxxxxx_xxxx_4xxx_yxxx_111111111111($data);
            }
            if (substr($path, 0, 10) == '/balances/') {
                return $this->sampleData_get_balances_1xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx($data);
            }
            if (substr($path, 0, 19) == '/accounts/transfer/') {
                try {
                    $from = isset($data['from']) ? $data['from'] : null;
                    $to = isset($data['to']) ? $data['to'] : null;
                    $payment_address_id = substr($path, 19);
                    if (!$from OR !$to) { throw new Exception("from or to account missing", 1); }
                    if (isset($data['close']) AND $data['close']) {
                        // close account
                        $balances = $this->findBalances($payment_address_id);
                        if (!$balances OR !isset($balances[$from]) OR !isset($balances[$from]['confirmed'])) { throw new Exception("No balances for $from", 1); }
                        foreach ($balances[$from]['confirmed'] as $asset => $quantity) {
                            $this->debitBalance($quantity, $asset, $from, 'confirmed', $payment_address_id);
                            $this->creditBalance($quantity, $asset, $to, 'confirmed', $payment_address_id);
                        }
                    } else if (isset($data['quantity'])) {
                        $this->debitBalance($data['quantity'], $data['asset'], $from, 'confirmed', $payment_address_id);
                        $this->creditBalance($data['quantity'], $data['asset'], $to, 'confirmed', $payment_address_id);
                    } else if (isset($data['txid'])) {
                        $txid = $data['txid'];
                        if (isset($this->balances_by_txid[$txid])) {
                            foreach ($this->balances_by_txid[$txid] as $txid_asset => $txid_quantity) {
                                $this->debitBalance($txid_quantity, $txid_asset, $from, 'confirmed', $payment_address_id);
                                $this->creditBalance($txid_quantity, $txid_asset, $to, 'confirmed', $payment_address_id);
                            }
                        }
                    }

                    // success
                    return [];                    
                } catch (XChainException $e) {
                    $decorated_e = new XChainException("Failed transferring: ".json_encode($data, 192)."\n".$e->getMessage(), $e->getCode());
                    $decorated_e->setErrorName($e->getErrorName());
                    throw $decorated_e;
                }

            }
            if (substr($path, 0, 19) == '/accounts/balances/') {
                $name = isset($data['name']) ? $data['name'] : null;
                $type = isset($data['type']) ? $data['type'] : null;
                if ($name) {
                    $payment_address_id = substr($path, 19);
                    $balances = $this->findBalances($payment_address_id);
                    if ($type) { return (isset($balances[$name]) AND isset($balances[$name][$type])) ? [['balances' => $balances[$name][$type]]] : []; }
                    return isset($balances[$name]) ? [['balances' => $balances[$name]]] : [];
                }
                return $balances;

                return [];
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

    public function receive($notification) {
        $fully_confirmed = ($notification['confirmations'] > 1);

        $txid = $notification['txid'];

        if (!isset($this->received_by_txid_map)) { $this->received_by_txid_map = []; }
        if (!isset($this->received_by_txid_map[$txid])) {
            $this->balances_by_txid[$txid] = [];
            $this->balances_by_txid[$txid][$notification['asset']] = $notification['quantity'];

            if ($notification['asset'] !== 'BTC') {
                $dust_size = $notification['counterpartyTx']['dustSize'];
                $this->balances_by_txid[$txid]['BTC'] = $dust_size;
            }

            $this->received_by_txid_map[$txid] = true;
        }


        if ($fully_confirmed) {
            $this->creditBalance($notification['quantity'], $notification['asset'], 'default', 'confirmed');
            if ($notification['asset'] !== 'BTC') {
                $dust_size = $notification['counterpartyTx']['dustSize'];
                $this->creditBalance($dust_size, 'BTC', 'default', 'confirmed');
            }
        }

    }

    protected function pathIsIgnored($path, $test_exception_ignore_xchain_call_prefixes) {
        if ($test_exception_ignore_xchain_call_prefixes === null) { return false; }

        $ignore = false;
        foreach($test_exception_ignore_xchain_call_prefixes as $ignore_prefix) {
            if (substr($path, 0, strlen($ignore_prefix)) == $ignore_prefix) {
                $ignore = true;
                break;
            }
        }

        return $ignore;
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
            "txid"        => $this->output_transaction_id,
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


    protected function creditBalance($quantity, $asset, $account='default', $type='confirmed', $payment_address_id='default') {
        $this->changeBalance($quantity, $asset, $account, $type, $payment_address_id);
    }
    protected function debitBalance($quantity, $asset, $account='default', $type='confirmed', $payment_address_id='default') {
        $this->changeBalance(0-$quantity, $asset, $account, $type, $payment_address_id);
    }
    protected function changeBalance($quantity, $asset, $account='default', $type='confirmed', $raw_payment_address_id='default') {
        $payment_address_id = $this->resolvePaymentAddressID($raw_payment_address_id);
        if (isset($this->balances) AND isset($this->balances[$payment_address_id])) {
            if (!isset($this->balances[$payment_address_id][$account])) { $this->balances[$payment_address_id][$account] = []; }
            if (!isset($this->balances[$payment_address_id][$account][$type])) { $this->balances[$payment_address_id][$account][$type] = []; }
            if (!isset($this->balances[$payment_address_id][$account][$type][$asset])) { $this->balances[$payment_address_id][$account][$type][$asset] = 0; }
            if ($quantity < 0 AND $this->balances[$payment_address_id][$account][$type][$asset] + $quantity < 0) {
                $xchain_exception = new XChainException("Insufficient funds: tried to debit account {$type} {$account} {$quantity} {$asset} - but had only {$this->balances[$payment_address_id][$account][$type][$asset]}", 1);
                $xchain_exception->setErrorName('ERR_INSUFFICIENT_FUNDS');
                throw $xchain_exception;
            }
            $this->balances[$payment_address_id][$account][$type][$asset] += $quantity;
        }
    }
    protected function findBalances($raw_payment_address_id='default') {
        $payment_address_id = $this->resolvePaymentAddressID($raw_payment_address_id);
        if (isset($this->balances[$payment_address_id])) { return $this->balances[$payment_address_id]; }
        throw new Exception("No Balances Defined", 1);
        ;
    }
    protected function resolvePaymentAddressID($payment_address_id) {
        if (isset($this->balances) AND isset($this->balances[$payment_address_id])) { return $payment_address_id; }
        return 'default';
    }

}