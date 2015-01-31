<?php

namespace Tokenly\XChainClient;

use Exception;
use Illuminate\Support\ServiceProvider;

/*
* XChainServiceProvider
*/
class XChainServiceProvider extends ServiceProvider
{

    public function boot()
    {
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $config = $this->getConfig();

        $this->app->bind('Tokenly\XChainClient\Client', function($app) use ($config) {
            $xchain_client = new \Tokenly\XChainClient\Client($config['connection_url'], $config['api_token'], $config['api_key']);
            return $xchain_client;
        });

        $this->app->bind('Tokenly\XChainClient\WebHookReceiver', function($app) use ($config) {
            $webhook_receiver = new \Tokenly\XChainClient\WebHookReceiver($config['api_token'], $config['api_key']);
            return $webhook_receiver;
        });
    }

    protected function getConfig()
    {
        // simple config
        $config = [
            'connection_url' => env('XCHAIN_CONNECTION_URL', 'http://xchain.tokenly.co'),
            'api_token'      => env('XCHAIN_API_TOKEN'     , null),
            'api_key'        => env('XCHAIN_API_KEY'       , null),
        ];

        return $config;
    }

}

