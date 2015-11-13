<?php 

namespace Tokenly\XChainClient;

use Exception;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\RequestException;
use Tokenly\HmacAuth\Generator;
use Tokenly\XChainClient\Exception\XChainException;

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
     * switches a monitor between active and inactive states
     * @param string $id 				the uuid of the address monitor
     * @param boolean $active 			active
     * @return array					monitor object
     * */
    public function updateAddressMonitorActiveState($id, $active=true) {
        $body = [
            'active'          => $active,
        ];
        $result = $this->newAPIRequest('PATCH', '/monitors/'.$id, $body);
        return $result;
	}
	
    /**
     * get details about an address monitor
     * @param string $id 				the uuid of the address monitor
     * @return array					monitor object
     * */	
	public function getAddressMonitor($id)
	{
        $result = $this->newAPIRequest('GET', '/monitors/'.$id, array());
        return $result;	
	}
	
    /**
     * destroys an address monitor from the DB
     * @param string $id 				the uuid of the address monitor
     * @return null
     * */	
	public function destroyAddressMonitor($id)
	{
        $result = $this->newAPIRequest('DELETE', '/monitors/'.$id, array());
        return $result;	
	}

    /**
     * sends confirmed and unconfirmed funds from the given payment address
     * confirmed funds are sent first if they are available
     * @param  string $payment_address_id address uuid
     * @param  string $destination        destination bitcoin address
     * @param  float  $quantity           quantity to send
     * @param  string $asset              asset name to send
     * @param  float  $fee                bitcoin fee
     * @param  float  $dust_size          bitcoin transaction dust size for counterparty transactions
     * @param  string $request_id         a unique id for this request
     * @return array                      An array with the send information, including `txid`
     */
    public function send($payment_address_id, $destination, $quantity, $asset, $fee=null, $dust_size=null, $request_id=null) {
        return $this->sendFromAccount($payment_address_id, $destination, $quantity, $asset, 'default', true, $fee, $dust_size, $request_id);
    }

    /**
     * sends only confirmed funds from the given payment address
     * @param  string $payment_address_id address uuid
     * @param  string $destination        destination bitcoin address
     * @param  float  $quantity           quantity to send
     * @param  string $asset              asset name to send
     * @param  float  $fee                bitcoin fee
     * @param  float  $dust_size          bitcoin transaction dust size for counterparty transactions
     * @param  string $request_id         a unique id for this request
     * @return array An array with the send information, including `txid`
     */
    public function sendConfirmed($payment_address_id, $destination, $quantity, $asset, $fee=null, $dust_size=null, $request_id=null) {
        return $this->sendFromAccount($payment_address_id, $destination, $quantity, $asset, 'default', false, $fee, $dust_size, $request_id);
    }

    /**
     * sends confirmed and unconfirmed funds from the given payment address
     * confirmed funds are sent first if they are available
     * @param  string $payment_address_id address uuid
     * @param  string $destination        destination bitcoin address
     * @param  float  $quantity           quantity to send
     * @param  string $asset              asset name to send
     * @param  string $account            an account name to send from
     * @param  bool   $unconfirmed        allow unconfirmed funds to be sent
     * @param  float  $fee                bitcoin fee
     * @param  float  $dust_size          bitcoin transaction dust size for counterparty transactions
     * @param  string $request_id         a unique id for this request
     * @return array                      An array with the send information, including `txid`
     */
    public function sendFromAccount($payment_address_id, $destination, $quantity, $asset, $account='default', $unconfirmed=false, $fee=null, $dust_size=null, $request_id=null) {
        $body = [
            'destination' => $destination,
            'quantity'    => $quantity,
            'asset'       => $asset,
            'sweep'       => false,
            'unconfirmed' => $unconfirmed,
            'account'     => $account,
        ];
        if ($fee !== null)        { $body['fee']       = $fee; }
        if ($dust_size !== null)  { $body['dust_size'] = $dust_size; }
        if ($request_id !== null) { $body['requestId'] = $request_id; }

        $result = $this->newAPIRequest('POST', '/sends/'.$payment_address_id, $body);
        return $result;
    }

    /**
     * sends confirmed and unconfirmed BTC from the given payment address to multiple destinations
     *
     * @param  string $payment_address_id address uuid
     * @param  array  $destinations       destination bitcoin addresses with values. In the form of [['address' => '1XXXXXXX1111', 'amount' => 0.001], ['address' => '1XXXXXXX2222', 'amount' => 0.005]]
     * @param  string $account            an account name to send from
     * @param  bool   $unconfirmed        allow unconfirmed funds to be sent
     * @param  float  $fee                bitcoin fee
     * @param  string $request_id         a unique id for this request
     * @return array                      An array with the send information, including `txid`
     */
    public function sendBTCToMultipleDestinations($payment_address_id, $destinations, $account='default', $unconfirmed=false, $fee=null, $request_id=null) {
        $body = [
            'destinations' => $destinations,
            'sweep'        => false,
            'unconfirmed'  => $unconfirmed,
            'account'      => $account,
        ];
        if ($fee !== null)        { $body['fee']       = $fee; }
        if ($request_id !== null) { $body['requestId'] = $request_id; }

        $result = $this->newAPIRequest('POST', '/multisends/'.$payment_address_id, $body);
        return $result;
    }

    /**
     * sends all assets and all BTC to a destination address
     * @return array the send details
     */
    public function sweepAllAssets($payment_address_id, $destination, $fee=null, $dust_size=null, $request_id=null) {
        $body = [
            'destination' => $destination,
            'quantity'    => null,
            'asset'       => 'ALLASSETS',
            'sweep'       => true,
        ];
        if ($fee !== null)        { $body['fee']       = $fee; }
        if ($dust_size !== null)  { $body['dust_size'] = $dust_size; }
        if ($request_id !== null) { $body['requestId'] = $request_id; }

        $result = $this->newAPIRequest('POST', '/sends/'.$payment_address_id, $body);
        return $result;
    }

    /**
     * Gets the current asset balances for a bitcoin address
     * For balances of payment addresses, please see the getAccountBalances method.
     * @param  string $address bitcoin address
     * @param  boolean $as_satoshis if true, return balances insatoshis
     * @return array an array like ['ASSET' => value, 'ASSET2' => value]
     */
    public function getBalances($address, $as_satoshis=false) {
        $result = $this->newAPIRequest('GET', '/balances/'.$address);
        $key = ($as_satoshis ? 'balancesSat' : 'balances');
        return $result[$key];
    }
    
    /**
     * gets info for a particular asset
     * @param string $asset counterparty asset
     * @return array
     * */
    public function getAsset($asset)
    {
		$result = $this->newAPIRequest('GET', '/assets/'.$asset);
		return $result;
	}

    ////////////////////////////////////////////////////////////////////////
    // Acounts

    /**
     * Creates a new account for the payment address
     * @param  string $payment_address_uuid payment address id
     * @param  string $account_name         a name of the account
     * @param  array $meta_data             optional meta data stored along with this account
     * @return array                        the new account
     */
    public function createAccount($payment_address_uuid, $account_name, $meta_data=null) {
        $body = [
            'addressId' => $payment_address_uuid,
            'name'      => $account_name,
        ];
        if ($meta_data !== null) { $body['meta'] = $meta_data; }

        $result = $this->newAPIRequest('POST', '/accounts', $body);
        return $result;
    }

    /**
     * Updates an existing account
     * @param  string $account_uuid account id
     * @param  string $account_name a name of the account
     * @param  array $meta_data     optional meta data stored along with this account
     * @return array                the updated account
     */
    public function updateAccount($account_uuid, $account_name=null, $meta_data=null) {
        $body = [];

        if ($account_name !== null) { $body['name'] = $account_name; }
        if ($meta_data !== null) { $body['meta'] = $meta_data; }

        $result = $this->newAPIRequest('PATCH', '/accounts', $body);
        return $result;
    }



    /**
     * Fetch existing accounts
     * @param  string $payment_address_uuid the address id
     * @param  boolean $active              Set to false to get the inactive accounts
     * @return array                        a numbered array of all accounts
     */
    public function getAccounts($payment_address_uuid, $active=true) {
        $result = $this->newAPIRequest('GET', '/accounts/'.$payment_address_uuid.'?active='.($active ? '1' : '0'));
        return $result;
    }

    /**
     * Fetch an existing account by ID
     * @param  string $account_uuid account id
     * @param  boolean $active              Set to false to get the inactive accounts
     * @return array                        the account data
     */
    public function getAccount($account_uuid) {
        $result = $this->newAPIRequest('GET', '/account/'.$account_uuid);
        return $result;
    }


    /**
     * Fetch existing accounts with balances.
     * This is the fastest and preferred way of obtaining balances for payment addresses managed by XChain.
     * If type is not specified, the result looks like this
     * {
     *     "unconfirmed": {
     *         "BTC": 4
     *     }
     *     "confirmed": {
     *         "BTC": 10,
     *         "TOKENLY": 4
     *     },
     *     "sending": [],
     * }
     * If type is specified, the result looks like this
     * {
     *     "BTC": 10,
     *     "TOKENLY": 4
     * }
     * @param  string $payment_address_uuid the address id
     * @param  string $account_name         An account name
     * @param  string $type                 Only show balances of a specific type (unconfirmed, confirmed, sending)
     * @return array                        An array of all active accounts with balances
     */
    public function getAccountBalances($payment_address_uuid, $account_name, $type=null) {
        $params = ['name' => $account_name];
        if ($type !== null) { $params['type'] = $type; }

        $result = $this->newAPIRequest('GET', '/accounts/balances/'.$payment_address_uuid, $params);
        if ($result) { return $result[0]['balances']; }

        return $result;
    }


    /**
     * Fetch existing accounts with balances
     * An example result might look like this
     * [
     *     {
     *         "id": "3c411819-ffb8-40a9-82f9-6805c95567c9",
     *         "name": "myNewCarSavings",
     *         "active": true,
     *         "meta": {
     *             "foo": "bar"
     *         },
     *         "balances": {
     *             "confirmed": {
     *                 "BTC": 10,
     *                 "TOKENLY": 4
     *             },
     *             "unconfirmed": {
     *                 "BTC": 4
     *             },
     *             "sending": []
     *         }
     *     },
     * ]
     * @param  string $payment_address_uuid the address id
     * @return array                        An array of all active accounts with balances
     */
    public function getAllAccountsWithBalances($payment_address_uuid) {
        $params = [];

        $result = $this->newAPIRequest('GET', '/accounts/balances/'.$payment_address_uuid, $params);
        return $result;
    }


    /**
     * @return array                        An array of all active accounts with balances
     */


    /**
     * Transfer funds from one account to another
     * @param  string $payment_address_uuid the address id
     * @param  string $from                 The name of the account to transfer from
     * @param  string $to                   Account name to transfer to.  This account will be created if it does not exist.
     * @param  float  $quantity             Quantity of the asset to transfer
     * @param  string $asset                Asset name to transfer
     * @param  string $txid                 To transfer unconfirmed funds, specify a transaction id
     * @return boolean                      true on success, false if funds are not available
     */
    public function transfer($payment_address_uuid, $from, $to, $quantity, $asset, $txid=null) {
        $body = [
            'from'     => $from,
            'to'       => $to,
            'quantity' => $quantity,
            'asset'    => $asset,
        ];

        if ($txid !== null) { $body['txid'] = $txid; }

        try {
            $result = $this->newAPIRequest('POST', '/accounts/transfer/'.$payment_address_uuid, $body);
        } catch (XChainException $e) {
            // handle an INSUFFICIENT_FUNDS error
            if ($e->getErrorName() == 'ERR_INSUFFICIENT_FUNDS') {
                return false;
            }
        }
        return true;
    }

    /**
     * Transfers all funds from one account to another that are tagged with a transaction ID
     * @param  string $payment_address_uuid the address id
     * @param  string $from                 The name of the account to transfer from
     * @param  string $to                   Account name to transfer to.  This account will be created if it does not exist.
     * @param  string $txid                 A transaction id
     * @return array                        An empty array
     */
    public function transferAllByTransactionID($payment_address_uuid, $from, $to, $txid) {
        $body = [
            'from'     => $from,
            'to'       => $to,
            'txid'     => $txid,
        ];

        $result = $this->newAPIRequest('POST', '/accounts/transfer/'.$payment_address_uuid, $body);
        return $result;
    }

    /**
     * Transfer all funds from one account to another
     * @param  string $payment_address_uuid the address id
     * @param  string $from                 The name of the account to transfer from
     * @param  string $to                   Account name to transfer to.  This account will be created if it does not exist.
     * @return boolean                      true on success
     */
    public function closeAccount($payment_address_uuid, $from, $to) {
        $body = [
            'from'     => $from,
            'to'       => $to,
            'close'    => true,
        ];

        $result = $this->newAPIRequest('POST', '/accounts/transfer/'.$payment_address_uuid, $body);
        return true;
    }

    ////////////////////////////////////////////////////////////////////////

    protected function newAPIRequest($method, $path, $data=[]) {
        $api_path = '/api/v1'.$path;

        $client = new GuzzleClient(['base_url' => $this->xchain_url,]);

        $request = $client->createRequest($method, $api_path);
        if ($data AND ($method == 'POST' OR $method == 'PATCH')) {
            $request = $client->createRequest($method, $api_path, ['json' => $data]);
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
                try {
                    $json = $response->json();
                } catch (Exception $parse_json_exception) {
                    // could not parse json
                    $json = null;
                }
                if ($json and isset($json['message'])) {
                    // throw an XChainException with the errorName
                    if (isset($json['errorName'])) {
                        $xchain_exception = new XChainException($json['message'], $code);
                        $xchain_exception->setErrorName($json['errorName']);
                        throw $xchain_exception;
                    }

                    // generic exception
                    throw new Exception($json['message'], $code);
                }
            }

            // if no response, then just throw the original exception
            throw $e;
        }

        $code = $response->getStatusCode();
        if ($code == 204) {
            // empty content
            return [];
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
