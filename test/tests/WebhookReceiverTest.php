<?php

use Tokenly\XChainClient\WebHookReceiver;
use \PHPUnit_Framework_Assert as PHPUnit;

/*
* 
*/
class WebhookReceiverTest extends PHPUnit_Framework_TestCase
{

    public function testValidSignature() {
        $this->validateNotification([]);
    }

    /**
     * @expectedException              Tokenly\XChainClient\Exception\AuthorizationException
     * @expectedExceptionMessageRegExp !API token not found!
     */
    public function testMissingAPIKey() {
        $this->validateNotification(['apiToken' => null]);
    }

    /**
     * @expectedException              Tokenly\XChainClient\Exception\AuthorizationException
     * @expectedExceptionMessageRegExp !Invalid API token!
     */
    public function testInvalidAPIKey() {
        $this->validateNotification(['apiToken' => 'somethingelse']);
    }

    /**
     * @expectedException              Tokenly\XChainClient\Exception\AuthorizationException
     * @expectedExceptionMessageRegExp !signature not found!
     */
    public function testMissingSignature() {
        $this->validateNotification(['signature' => null]);
    }

    /**
     * @expectedException              Tokenly\XChainClient\Exception\AuthorizationException
     * @expectedExceptionMessageRegExp !payload not found!
     */
    public function testMissingPayload() {
        $this->validateNotification(['payload' => null]);
    }

    /**
     * @expectedException              Tokenly\XChainClient\Exception\AuthorizationException
     * @expectedExceptionMessageRegExp !Invalid signature!
     */
    public function testInvalidSignature() {
        $this->validateNotification(['signature' => 'BAD_SIGNATURE']);
    }


    protected function validateNotification($vars=[], $payload=null, $API_TOKEN = 'TEST_API_TOKEN', $API_SECRET = 'TEST_API_SECRET') {

        if ($payload === null) { $payload = ['foo' => 'bar']; }
        $payload_string = json_encode($payload);

        $json_data = array_merge([
            'id'        => 'xxxx',
            'time'      => date("Y-m-d H:i:s"),
            'attempt'   => 1,
            'apiToken'  => $API_TOKEN,
            'signature' => hash_hmac('sha256', $payload_string, $API_SECRET, false),
            'payload'   => $payload_string,
        ], $vars);

        $receiver = new WebHookReceiver($API_TOKEN, $API_SECRET);
        $receiver->validateWebhookNotification($json_data);
    }


}
