<?php 

namespace Tokenly\XChainClient;

use Exception;
use Tokenly\XChainClient\Exception\AuthorizationException;

/**
* XChain WebHookReceiver
*/
class WebHookReceiver
{
    
    public function __construct($api_token, $api_secret)
    {
        if (!strlen($api_secret)) { throw new AuthorizationException("API secret must exist"); }

        $this->api_token  = $api_token;
        $this->api_secret = $api_secret;
    }

    public function validateAndParseWebhookNotificationFromRequest(\Symfony\Component\HttpFoundation\Request $request) {
        $json_data = json_decode($request->getContent(), true);
        if (!is_array($json_data)) { throw new AuthorizationException("Invalid webhook data received"); }

        // throws an exception if invalid
        $this->validateWebhookNotification($json_data);

        return $this->parseWebhookNotificationData($json_data);
    }

    public function validateWebhookNotification($json_data) {
        $is_valid = false;

        // validate vars
        if (!strlen($json_data['apiToken'])) { throw new AuthorizationException("API token not found"); }
        if ($json_data['apiToken'] != $this->api_token) { throw new AuthorizationException("Invalid API token"); }
        if (!strlen($json_data['signature'])) { throw new AuthorizationException("signature not found"); }
        $notification_json_string = $json_data['payload'];
        if (!strlen($notification_json_string)) { throw new AuthorizationException("payload not found"); }

        // check signature
        $expected_signature = hash_hmac('sha256', $notification_json_string, $this->api_secret, false);
        $is_valid = ($expected_signature === $json_data['signature']);
        if (!$is_valid) { throw new AuthorizationException("Invalid signature"); }

        return $is_valid;
    }

    public function parseWebhookNotificationData($json_data) {
        $json_data['rawPayload'] = $json_data['payload'];
        $json_data['payload'] = json_decode($json_data['rawPayload'], true);
        return $json_data;
    }


}