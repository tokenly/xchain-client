<?php 

namespace Tokenly\XChainClient;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\RequestException;
use Tokenly\HmacAuth\Generator;
use Exception;

/**
* XChain Client
*/
class Client
{
    
    function __construct($xchain_url, $api_token, $api_secret_key)
    {
        $this->xchain_url     = $xchain_url;
        $this->api_token      = $api_token;
        $this->api_secret_key = $api_secret_key;
    }

    /**
     * creates a new payment address
     * @return array An array with an (string) id and (string) address
     */
    public function newPaymentAddress() {
        $result = $this->newAPIRequest('POST', '/addresses', []);
        return $result;
    }

    /**
     * get the payment address details
     * @param  string $uuid id of the paymehnt address
     * @return array  array of payment address details
     */
    public function getPaymentAddress($uuid) {
        $result = $this->newAPIRequest('GET', '/addresses/'.$uuid);
        return $result;
    }

    /**
     * monitor a new address
     * @param  string  $address          bitcoin/counterparty address
     * @param  string  $webhook_endpoint webhook callback URL
     * @param  string  $monitor_type     send or receive
     * @param  boolean $active           active
     * @return array                     The new monitor object
     */
    public function newAddressMonitor($address, $webhook_endpoint, $monitor_type='receive', $active=true) {
        $body = [
            'address'         => $address,
            'webhookEndpoint' => $webhook_endpoint,
            'monitorType'     => $monitor_type,
            'active'          => $active,
        ];
        $result = $this->newAPIRequest('POST', '/monitors', $body);
        return $result;
    }

    /**
     * creates a new payment address
     * @return array An array with an (string) id and (string) address
     */
    public function send($payment_address_id, $destination, $quantity, $asset, $fee=null, $dust_size=null) {
        $body = [
            'destination' => $destination,
            'quantity'    => $quantity,
            'asset'       => $asset,
            'sweep'       => false,
        ];
        if ($fee !== null)       { $body['fee']       = $fee; }
        if ($dust_size !== null) { $body['dust_size'] = $dust_size; }

        $result = $this->newAPIRequest('POST', '/sends/'.$payment_address_id, $body);
        return $result;
    }

    /**
     * sends all assets and all BTC to a destination address
     * @return array the send details
     */
    public function sweepAllAssets($payment_address_id, $destination, $fee=null, $dust_size=null) {
        $body = [
            'destination' => $destination,
            'quantity'    => $quantity,
            'asset'       => 'ALLASSETS',
            'sweep'       => true,
        ];
        if ($fee !== null)       { $body['fee']       = $fee; }
        if ($dust_size !== null) { $body['dust_size'] = $dust_size; }

        $result = $this->newAPIRequest('POST', '/sends/'.$payment_address_id, $body);
        return $result;
    }

    /**
     * gets the current asset balances
     * @param  string $address bitcoin address
     * @param  boolean $as_satoshis if true, return balances insatoshis
     * @return array an array like ['ASSET' => value, 'ASSET2' => value]
     */
    public function getBalances($address, $as_satoshis=false) {
        $result = $this->newAPIRequest('GET', '/balances/'.$address);
        $key = ($as_satoshis ? 'balancesSat' : 'balances');
        return $result[$key];
    }


    protected function newAPIRequest($method, $path, $data=[]) {
        $api_path = '/api/v1'.$path;

        $client = new GuzzleClient(['base_url' => $this->xchain_url,]);

        $request = $client->createRequest($method, $api_path);
        if ($data AND $method == 'POST') {
            $request = $client->createRequest('POST', $api_path, ['json' => $data]);
        } else if ($method == 'GET') {
            $request = $client->createRequest($method, $api_path, ['query' => $data]);
        }

        // add auth
        $this->getAuthenticationGenerator()->addSignatureToGuzzleRequest($request, $this->api_token, $this->api_secret_key);
        
        // send request
        try {
            $response = $client->send($request);
        } catch (RequestException $e) {
            if ($response = $e->getResponse()) {
                // interpret the response and error message
                $code = $response->getStatusCode();
                $json = $response->json();
                if ($json and isset($json['message'])) {
                    throw new Exception($json['message'], $code);
                }
            }

            // if no response, then just throw the original exception
            throw $e;
        }

        $json = $response->json();
        if (!is_array($json)) { throw new Exception("Unexpected response", 1); }

        return $json;
    }

    protected function getAuthenticationGenerator() {
        $generator = new Generator();
        return $generator;
    }

}