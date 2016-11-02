<?php

namespace Tokenly\XChainClient\Mock;

use Exception;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
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


    public function setBalancesByAddress($balances_by_address) {
        $this->balances_by_address = $balances_by_address;
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
        list($xchain_client_mock, $xchain_recorder) = $this->buildXChainMockAndRecorder($test_case);

        // install the xchain client into the DI container
        $this->app->bind('Tokenly\XChainClient\Client', function($app) use ($xchain_client_mock) {
            return $xchain_client_mock;
        });

        // return an object to check the calls
        return $xchain_recorder;
    }
    
    public function sampleData_multisigPaymentAddress()
    {
        return [
             "id"             => "21b4d491-22a9-488a-8d28-b2ff873dbc1a",
             "address"        => "",
             "type"           => "p2sh",
             "status"         => "pending",
             "invitationCode" => "Fenq762M2AHEBYUbnZGUweKxRocmqszNNZwzAWnj3ETR9Up3ThUPJqQ5vBq3f7eA2RL7obxoC6L",
        ];
    }

    public function buildXChainMockAndRecorder(PHPUnit_Framework_TestCase $test_case=null) {
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
            try {
                if ($this->requests_remainning_before_throwing_exception !== null AND !$this->pathIsIgnored($path, $this->test_exception_ignore_xchain_call_prefixes)) {
                    if ($this->requests_remainning_before_throwing_exception <= 0) {
                        throw new Exception("Test Exception Triggered", 1);
                    }
                    --$this->requests_remainning_before_throwing_exception;
                    if ($this->requests_remainning_before_throwing_exception < 0) { $this->requests_remainning_before_throwing_exception = 0; }
                }

                // store the method for test verification
                $call_data = [
                    'method' => $method,
                    'path'   => $path,
                    'data'   => $data,
                ];
                $xchain_recorder->calls[] = $call_data;
                Event::fire('xchainMock.callBegin', [$call_data]);

                // call a method that returns sample data
                $sample_method_name = 'sampleData_'.strtolower($method).'_'.preg_replace('![^a-z0-9]+!i', '_', trim($path, '/'));
                if (method_exists($this, $sample_method_name)) {
                    return $this->returnMockResult(call_user_func([$this, $sample_method_name], $data), $call_data);
                }

                // defaults
                if (substr($path, 0, 7) == '/sends/') {
                    $account = isset($data['account']) ? $data['account'] : 'default';
                    $payment_address_id = substr($path, 7);
                    $this->debitBalance($data['quantity'], $data['asset'], $account, 'confirmed', $payment_address_id);

                    $btc_debit = (isset($data['fee']) ? $data['fee'] : self::DEFAULT_FEE_SIZE);
                    if ($data['asset'] != 'BTC') { $btc_debit += (isset($data['dust_size']) ? $data['dust_size'] : self::DEFAULT_REGULAR_DUST_SIZE); }
                    $this->debitBalance($btc_debit, 'BTC', $account, 'confirmed', $payment_address_id);

                    return $this->returnMockResult($this->sampleData_post_sends_xxxxxxxx_xxxx_4xxx_yxxx_111111111111($data), $call_data);
                }
                if (substr($path, 0, 10) == '/balances/') {
                    return $this->returnMockResult($this->sampleData_get_balances_1xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx($data, substr($call_data['path'], 10)), $call_data);
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
                            $this->closeAccount($payment_address_id, $from);

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
                        return $this->returnMockResult([], $call_data);
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
                        if ($type) { return $this->returnMockResult((isset($balances[$name]) AND isset($balances[$name][$type])) ? [['balances' => $balances[$name][$type]]] : [], $call_data); }
                        return $this->returnMockResult(isset($balances[$name]) ? [['balances' => $balances[$name]]] : [], $call_data);
                    }
                    return $this->returnMockResult($balances, $call_data);
                }

                if (substr($path, 0, 10) == '/accounts/') {
                    $active = isset($data['active']) ? $data['active'] : 1;

                    $payment_address_id = $this->resolvePaymentAddressID(substr($path, 10));
                    $out = [];
                    foreach (array_keys($this->balances[$payment_address_id]) as $account_name) {
                        $out[] = [
                            'id'     => md5($account_name),
                            'name'   => $account_name,
                            'active' => true,
                            'meta'   => [],
                        ];
                    }

                    return $this->returnMockResult($out, $call_data);
                }

                if (substr($path, 0, 13) == '/estimatefee/') {
                    return $this->returnMockResult($this->sampleData_estimatefee($data), $call_data);
                }

                if (substr($path, 0, 20) == '/unmanaged/addresses') {
                    return $this->returnMockResult([
                        'address' => $data['address'],
                        'id'      => 'xxxxxxxx-xxxx-4xxx-yaaa-'.substr(md5($data['address'].time()), 0, 12),
                    ], $call_data);
                }
                
                if (substr($path, 0, 16) == '/message/verify/'){
                    return $this->returnMockResult($this->sampleData_verifyMessage($data), $call_data);
                }            

                if (substr($path, 0, 10) == '/validate/'){
                    return $this->returnMockResult($this->sampleData_validate(substr($path, 10)), $call_data);
                }            


                if (substr($path, 0, 14) == '/message/sign/'){
                    return $this->sampleData_signMessage(substr($path, 14));
                }

                if (substr($path, 0, 8) == '/assets/'){
                    return $this->sampleData_asset(substr($path, 8));
                }

                if (substr($path, 0, 19) == '/multisig/addresses'){
                    return $this->sampleData_multisigPaymentAddress(substr($path, 19));
                }


                // handle delete message with an empty array
                if ($method == 'DELETE') {
                    return $this->returnMockResult([], $call_data);
                }

                Log::debug("No sample method for $method $path ".json_encode($data));
                throw new Exception("No sample method for $method $path", 1);
            } catch (XChainException $e) {
                Log::debug("XChain Mock ".get_class($e)." at ".$e->getFile().":".$e->getLine().": ".$e->getMessage());
                throw $e;
            } catch (Exception $e) {
                Log::error("XChain Mock ".get_class($e)." at ".$e->getFile().":".$e->getLine().": ".$e->getMessage()."\n".$e->getTraceAsString());
                throw $e;
            }
        }));

        return [$xchain_client_mock, $xchain_recorder];
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
        $mock_ending_6 = substr(md5($data['address'].$data['monitorType']), 0, 6);
        return [
            "id"              => "xxxxxxxx-xxxx-4xxx-yxxx-222222{$mock_ending_6}",
            "active"          => true,
            "address"         => $data['address'],
            "monitorType"     => $data['monitorType'],
            "webhookEndpoint" => $data['webhookEndpoint'],
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
    public function sampleData_get_balances_1xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx($data, $address) {
        if (isset($this->balances_by_address) AND isset($this->balances_by_address[$address])) {
            $float_balances = $this->balances_by_address[$address];
        } else {
            $float_balances = [
                'BTC'     => 0.01,
                'LTBCOIN' => 1000,
                'SOUP'    => 5000,
            ];
        }

        $satoshi_balances = [];
        foreach($float_balances as $token => $float_balance) {
            $satoshi_balances[$token] = CurrencyUtil::valueToSatoshis($float_balance);
        }

        return [
            'balances'    => $float_balances,
            'balancesSat' => $satoshi_balances,
        ];
    }

    public function sampleData_estimatefee($data) {
        return [
            'fees' => [
                'low'     => 1.3200000000000001E-5,
                'lowSat'  => 1320,
                'med'     => 0.0001,
                'medSat'  => 10000,
                'high'    => 0.00010823999999999999,
                'highSat' => 10824,
            ],
            'size' => 264,
        ];



    }
    
    public function sampleData_verifyMessage($data)
    {
        if ($data['sig'] == 'bad' OR !strlen($data['sig'])) {
            return ['result' => false];
        }

        return ['result' => true];
    }    

    public function sampleData_signMessage($address)
    {
        return ['result' => '9222deadbeef22299222deadbeef2229'];
    }


    public function sampleData_validate($address)
    {
        Log::debug("\$address=".json_encode($address, 192));
        return ['result' => \LinusU\Bitcoin\AddressValidator::isValid($address), 'is_mine' => false];
    }    

    public function sampleData_asset($asset) {
        if ($asset == 'NOTFOUND') { throw new XChainException("Asset Not found", 404); }

        return [
            'asset'       => 'TOKENLY',
            'divisible'   => true,
            'description' => 'Tokenly.co',
            'locked'      => false,
            'owner'       => '12717MBviQxttaBVhFGRP1LxD8X6CaW452',
            'issuer'      => '12717MBviQxttaBVhFGRP1LxD8X6CaW452',
            'supply'      => 10000000000000,
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
            if ($quantity < 0 AND round($this->balances[$payment_address_id][$account][$type][$asset] + $quantity, 8) < 0) {
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
    }
    protected function resolvePaymentAddressID($payment_address_id) {
        if (isset($this->balances) AND isset($this->balances[$payment_address_id])) { return $payment_address_id; }
        return 'default';
    }
    protected function closeAccount($raw_payment_address_id, $account) {
        $payment_address_id = $this->resolvePaymentAddressID($raw_payment_address_id);
        unset($this->balances[$payment_address_id][$account]);
    }

    protected function returnMockResult($return_value, $call_data) {
        Event::fire('xchainMock.callEnd', [$return_value, $call_data]);
        return $return_value;
    }

}
