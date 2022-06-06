<?php

namespace App;

use Exception;
use GuzzleHttp\Client as Guzzle;
use GuzzleHttp\Exception\ClientException;

class Incontact
{
    protected $request;
    protected $env;
    protected $authToken;
    protected $authUrl;
    protected $discoveryUrl;
    protected $urlSuffix = "/inContactAPI/";
    protected $getUrlApi;

    public function __construct($env)
    {
        $this->request = json_decode(file_get_contents('php://input'));
        $this->env = $env;

        if (is_null($this->request)) {
            throw new Exception("Error with request");
            die;
        }
        if (!$this->validateToken()) {
            throw new Exception("Error with params");
            die;
        }
        $this->authUrl = $this->env["AUTH_URL"];
        $this->discoveryUrl = $this->env["DISCOVERY_URL"];
        $this->getUrlApi = isset($this->request->getUrl) ? $this->request->getUrl : false;
    }

    /**
     * Check if the given token is the same than the env configuration
     * @return bool
     */
    protected function validateToken(): bool
    {
        $headers = getallheaders();
        if (!isset($headers['X-Inbenta-Token'])) return false;
        if (!isset($this->env['TOKEN'])) return false;
        if ($headers['X-Inbenta-Token'] === '') return false;
        if ($this->env['TOKEN'] !== $headers['X-Inbenta-Token']) return false;

        return true;
    }

    /**
     * Get the access key and the "api_endpoint"
     * @param Request $request
     * @return object
     */
    public function getAccessKey(): object
    {
        $payload = (object) [
            "accessKeyId" => $this->env["ACCESS_KEY_ID"],
            "accessKeySecret" => $this->env["ACCESS_KEY_SECRET"]
        ];
        $headers = [
            "Content-Type" => "application/json"
        ];

        $response = $this->remoteRequest($this->authUrl, 'post', $payload, $headers);
        if (isset($response->access_token) && $this->getUrlApi) {
            $headers["Authorization"] = $response->token_type . " " . $response->access_token;
            $apiEndpoint = $this->remoteRequest($this->discoveryUrl, 'get', null, $headers);
            if (isset($apiEndpoint->api_endpoint)) {
                $response->resource_server_base_uri = $apiEndpoint->api_endpoint . $this->urlSuffix;
            }
        }
        return $response;
    }

    /**
     * Execute the remote request
     * @param string $url
     * @param string $method
     * @param object $params
     * @param array $headers
     * @return object
     */
    private function remoteRequest(string $url, string $method, object $params = null, array $headers): object
    {
        $response = [];
        try {
            $client = new Guzzle();
            $clientParams = ['headers' => $headers];
            if ($method !== 'get') {
                $clientParams['body'] = json_encode($params);
            }
            $serverOutput = $client->$method($url, $clientParams);

            if (method_exists($serverOutput, 'getBody')) {
                $responseBody = $serverOutput->getBody();
                if (method_exists($responseBody, 'getContents')) {
                    return json_decode($responseBody->getContents());
                }
            }
        } catch (ClientException $e) {
            if (method_exists($e, "getResponse")) {
                $response["error"] = json_decode($e->getResponse()->getBody()->getContents());
            }
        }
        if (!isset($response["error"])) {
            $response["error"] = [
                "details" => "Error on request"
            ];
        }
        return (object) $response;
    }
}
