<?php

namespace Tokenly\XChainClient;

use Exception;
use Illuminate\Support\Facades\Config;
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
        $this->bindConfig();

        $this->app->bind('Tokenly\XChainClient\Client', function($app) {
            $xchain_client = new \Tokenly\XChainClient\Client(Config::get('xchain.connection_url'), Config::get('xchain.api_token'), Config::get('xchain.api_key'));
            return $xchain_client;
        });

        $this->app->bind('Tokenly\XChainClient\WebHookReceiver', function($app) {
            $webhook_receiver = new \Tokenly\XChainClient\WebHookReceiver(Config::get('xchain.api_token'), Config::get('xchain.api_key'));
            return $webhook_receiver;
        });
    }

    protected function bindConfig()
    {
        // simple config
        $config = [
            'xchain.connection_url' => env('XCHAIN_CONNECTION_URL', 'http://xchain.tokenly.co'),
            'xchain.api_token'      => env('XCHAIN_API_TOKEN'     , null),
            'xchain.api_key'        => env('XCHAIN_API_KEY'       , null),
        ];

        // set the laravel config
        Config::set($config);

        return $config;
    }

}

