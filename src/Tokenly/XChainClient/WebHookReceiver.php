<?php 

namespace Tokenly\XChainClient;

use Exception;
use Tokenly\XChainClient\Exception\AuthorizationException;

/**
* XChain WebHookReceiver
*/
class WebHookReceiver
{
    
    public function __construct($api_token, $api_scret_key)
    {
        if (!strlen($api_token)) { throw new AuthorizationException("API token must exist"); }
        if (!strlen($api_scret_key)) { throw new AuthorizationException("API secret key must exist"); }

        $this->api_token  = $api_token;
        $this->api_scret_key = $api_scret_key;
    }

    /**
     * Reads the input from the current web request and returns the notification
     * Throws an AuthorizationException if validation fails
     * @return array notification
     */
    public function validateAndParseWebhookNotificationFromCurrentRequest() {
        // read the JSON input
        $json_string = file_get_contents('php://input');

        $json_data = json_decode($json_string, true);

        // make sure the message is signed properly
        //   throws an exception if invalid
        $this->validateWebhookNotification($json_data);

        // return the payload
        return $this->parseWebhookNotificationData($json_data);
    }


    /**
     * Reads the input from a Symfony request and returns the notification
     * Throws an AuthorizationException if validation fails
     * @return array notification
     */
    public function validateAndParseWebhookNotificationFromRequest(\Symfony\Component\HttpFoundation\Request $request) {
        $json_data = json_decode($request->getContent(), true);
        if (!is_array($json_data)) { throw new AuthorizationException("Invalid webhook data received"); }

        // make sure the message is signed properly
        //   throws an exception if invalid
        $this->validateWebhookNotification($json_data);

        return $this->parseWebhookNotificationData($json_data);
    }

    /**
     * Parses and validates a notification
     * Throws an AuthorizationException if validation fails
     * @return boolean true if valid (always returns true)
     */
    public function validateWebhookNotification($json_data) {
        // validate vars
        if (!strlen($json_data['apiToken'])) { throw new AuthorizationException("API token not found"); }
        if ($json_data['apiToken'] != $this->api_token) { throw new AuthorizationException("Invalid API token"); }
        if (!strlen($json_data['signature'])) { throw new AuthorizationException("signature not found"); }
        $notification_json_string = $json_data['payload'];
        if (!strlen($notification_json_string)) { throw new AuthorizationException("payload not found"); }

        // check signature
        $expected_signature = hash_hmac('sha256', $notification_json_string, $this->api_scret_key, false);
        $is_valid = ($expected_signature === $json_data['signature']);
        if (!$is_valid) { throw new AuthorizationException("Invalid signature"); }

        // this will always be true
        //   otherwise an exception will be thrown by now
        return $is_valid;
    }

    /**
     * Parses the notification
     * Does not validate anything
     * @return array notification
     */
    public function parseWebhookNotificationData($json_data) {
        $json_data['rawPayload'] = $json_data['payload'];
        $json_data['payload'] = json_decode($json_data['rawPayload'], true);
        return $json_data;
    }


}