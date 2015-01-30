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
        // Path to the default config
        $defaultConfigPath = __DIR__.'/../../config/xchain.php';

        // Load the default config
        $config = $this->app['files']->getRequire($defaultConfigPath);

        // Check the user config file
        $userConfigPath = app()->configPath().'/packages/xchainclient/xchain.php';
        if (file_exists($userConfigPath)) 
        {       
            // User has their own config, let's merge them properly
            $userConfig = $this->app['files']->getRequire($userConfigPath);
            $config     = array_replace_recursive($config, $userConfig);
        }

        // just return the config
        return $config;
    }

}

