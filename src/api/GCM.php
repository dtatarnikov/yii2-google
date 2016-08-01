<?php
namespace strong2much\google\api;

use Yii;
use yii\base\Exception;
use yii\helpers\Json;
use yii\httpclient\Client;
use yii\web\HttpException;

/**
 * GCMApi class to work with GCM (Google Cloud Messages) server
 *
 * @package  Ext.google
 * @author   Denis Tatarnikov <tatarnikovda@gmail.com>
 */
class GCM
{
    const BASE_URL = 'https://android.googleapis.com/gcm/send';
    const MAX_DEVICES_COUNT = 1000; //max devices that can be connected

    /**
     * @var string API key
     */
    private $_apiKey = "";
    /**
     * @var array list of devices
     */
    private $_devices = [];

    /**
     * Constructor
     * @param string $apiKey the server API key
     * @throws Exception
     */
    public function __construct($apiKey)
	{
        if(strlen($apiKey) < 8){
            throw new Exception('Invalid API key');
        }
        $this->_apiKey = $apiKey;
    }

    /**
     * @param array $deviceIds array of devices to send to
     */
    public function setDevices($deviceIds)
    {
        if(is_array($deviceIds)){
            $this->_devices = $deviceIds;
        } else {
            $this->_devices = [$deviceIds];
        }
    }

    /**
     * Adds device to the list
     * @param string $deviceId device id to send to
     */
    public function addDevice($deviceId)
    {
        if(is_array($deviceId)){
            $this->_devices = array_merge($this->_devices, $deviceId);
        } else {
            $this->_devices[] = $deviceId;
        }
    }

    /**
     * Clears list of devices
     */
    public function clearDevices()
    {
        $this->_devices = [];
    }

    /**
     * Send the message to the device
     * @param string $message the message to send
     * @param string $title title for message
     * @param array $data additional data associated with message
     * @return array|mixed the response
     * @throws Exception
     */
    public function send($message, $title = '', $data = [])
    {
        if(!is_array($this->_devices) || count($this->_devices) == 0){
            throw new Exception('No devices specified');
        }

        $fields = [
            'registration_ids' => $this->_devices,
            'data' => [
                "message" => $message
            ],
        ];

        if(!empty($title)) {
            $fields['data']['title'] = $title;
        }

        if(is_array($data)){
            foreach ($data as $key => $value) {
                $fields['data'][$key] = $value;
            }
        }

        $iterations = ceil(count($this->_devices)/self::MAX_DEVICES_COUNT);
        $response = [];

        for($i=0;$i<$iterations;$i++) {
            $devices = array_slice($this->_devices, $i*self::MAX_DEVICES_COUNT, self::MAX_DEVICES_COUNT);
            $fields['registration_ids'] = $devices;

            $result = $this->makeRequest($fields);
            if(empty($response)) {
                $response = $result;
            } else {
                $response['success'] += $result['success'];
                $response['failure'] += $result['failure'];
                $response['canonical_ids'] += $result['canonical_ids'];
                $response['results'] = array_merge($response['results'], $result['results']);
            }
        }

        return $response;
    }

    /**
     * Makes the curl request to the url.
     * @param array $options HTTP post data
     * @return array the response.
     * @throws HttpException
     */
    protected function makeRequest($options = [])
    {
        $request = $this->initRequest($options);
        $response = $request->send();

        $data = $response->getData();

        if(!$response->isOk) {
            Yii::error(
                'Invalid response http code: ' . $response->getStatusCode() . '.' . PHP_EOL .
                'Headers: ' . Json::encode($response->getHeaders()->toArray()) . '.' . PHP_EOL .
                'Options: ' . Json::encode($options) . PHP_EOL .
                'Result: ' . (is_array($data) ? Json::encode($data) : var_export($data, true)),
                __METHOD__
            );
            throw new HttpException($response->getStatusCode());
        }

        return $data;
    }

    /**
     * Initializes a new request.
     * @param array $options HTTP post data
     * @return \yii\httpclient\Request
     */
    protected function initRequest($options = [])
    {
        $client = new Client([
            'requestConfig' => [
                'format' => Client::FORMAT_JSON
            ],
        ]);

        $request = $client->createRequest()
            ->setMethod('post')
            ->setUrl(self::BASE_URL)
            ->setData($options)
            ->setHeaders([
                'Authorization' => 'key=' . $this->_apiKey,
            ]);

        return $request;
    }
}