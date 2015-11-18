<?php
namespace strong2much\google\api;

use Yii;
use yii\base\Exception;

/**
 * GCMApi class to work with GCM (Google Cloud Messages) server
 *
 * @package  Ext.google
 * @author   Denis Tatarnikov <tatarnikovda@gmail.com>
 */
class GCM
{
    const MAX_DEVICES_COUNT = 1000; //max devices that can be connected

    /**
     * @var array Maps aliases to Facebook domains.
     */
    public static $domainMap = [
        'gcm' => 'https://android.googleapis.com/gcm/send',
    ];

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
            throw new Exception(Yii::t('google', 'Invalid API key'), 500);
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
            throw new Exception(Yii::t('google', 'No devices specified'), 500);
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

            $result = (array)$this->makeRequest(self::$domainMap['gcm'], $fields);
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
     * @param string $url url to request.
     * @param array $options additional data to send.
     * @return array|mixed the response.
     * @throws Exception
     */
    protected function makeRequest($url, $options = [])
    {
        $ch = $this->initRequest($url, $options);

        $result = curl_exec($ch);
        $headers = curl_getinfo($ch);

        if (curl_errno($ch) > 0) {
            throw new Exception(curl_error($ch), curl_errno($ch));
        }

        if ($headers['http_code'] != 200) {
            Yii::error(
                'Invalid response http code: ' . $headers['http_code'] . '.' . PHP_EOL .
                'URL: ' . $url . PHP_EOL .
                'Options: ' . var_export($options, true) . PHP_EOL .
                'Result: ' . $result, 'vendor.strong2much.google'
            );
            throw new Exception(Yii::t('google', 'Invalid response http code: {code}.', ['{code}' => $headers['http_code']]), $headers['http_code']);
        }

        curl_close($ch);

        return $this->parseJson($result);
    }

    /**
     * Initializes a new session and return a cURL handle.
     * @param string $url url to request.
     * @param array $options data to send.
     * @return resource cURL handle.
     */
    protected function initRequest($url, $options = [])
    {
        $headers = [
            'Authorization: key=' . $this->_apiKey,
            'Content-Type: application/json'
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($options));

        curl_setopt($ch, CURLOPT_URL, $url);
        return $ch;
    }

    /**
     * Parse response from {@link makeRequest} in json format and check OAuth errors.
     * @param string $response Json string.
     * @return array result as associative array.
     * @throws Exception
     */
    protected function parseJson($response)
    {
        try {
            $result = json_decode($response, true);
            if (!isset($result)) {
                throw new Exception(Yii::t('google', 'Invalid response format'), 500);
            }
            else {
                return $result;
            }
        } catch (\Exception $e) {
            throw new Exception($e->getMessage(), $e->getCode());
        }
    }
}