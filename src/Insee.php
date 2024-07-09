<?php

namespace NSpehler\LaravelInsee;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request as Psr7Request;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Support\Facades\Cache;

class Insee
{
    const API_URL = "https://api.insee.fr";

    const ENDPOINT_TOKEN = "/token";

    const ENDPOINT_SIRENE = "/entreprises/sirene";

    const CACHE_KEY = "inesee-sirene-token";

    /**
     * @var \GuzzleHttp\Client
     */
    protected $client;

    /**
     * @var array
     */
    protected $headers;

    /**
     * @var array
     */
    public $additionalData = [];

    /**
     * @var int
     */
    public $maxRetries = 2;

    /**
     * @var int
     */
    public $retryDelay = 500;

    /**
     * @param int $guzzleClientTimeout
     */
    public function __construct($guzzleClientTimeout = 0)
    {
        $this->client = new Client([
            'handler' => $this->createGuzzleHandler(),
            'timeout' => $guzzleClientTimeout,
        ]);
        $this->headers = ['headers' => [
            'Content-Type' => 'application/json; charset=utf-8',
        ]];
    }

    /**
     * Get company informations from SIREN number
     *
     * @param string $siren The siren number. Whitespaces are removed prior sending the request to INSEE
     */
    public function siren($siren)
    {
        $this->requiresAuth();

        // Format number
        $siren = str_replace(' ', '', $siren);

        $result = $this->get($this->getEndPoint() . '/siren/' . $siren);

        return json_decode($result->getBody());
    }

    /**
     * Get company informations from SIRET number
     *
     * @param string $siret The siret number. Whitespaces are removed prior sending the request to INSEE
     */
    public function siret(string $siret): object
    {
        $this->requiresAuth();

        // Format number
        $siret = str_replace(' ', '', $siret);

        $result = $this->get($this->getEndPoint() . '/siret/' . $siret);

        return json_decode($result->getBody());
    }

    /**
     * Request access token from Insee and store it in cache
     */
    public function access_token(): string
    {
        // Base64 encode consumer key and secret
        $token = base64_encode(config('insee.consumer_key') . ':' . config('insee.consumer_secret'));

        $this->headers = [
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Authorization' => 'Basic ' . $token,
            ],
            'form_params' => [
                'grant_type' => 'client_credentials',
                'validity_period' => 604800,
            ],
        ];

        $result = $this->post(self::ENDPOINT_TOKEN);
        $result = json_decode($result->getBody());

        Cache::put(self::CACHE_KEY, $result->access_token, now()->addSeconds($result->expires_in));

        return $result->access_token;
    }

    /**
     * Check for existing token in cache else generate a new one
     */
    private function requiresAuth(): void
    {
        // Check for token existance in cache
        $token = Cache::get(self::CACHE_KEY, false);

        if (!$token) {
            // Generate a new token
            $token = $this->access_token();
        }

        $this->headers['headers']['Authorization'] = 'Bearer ' . $token;
    }

    /**
     * Return SIRENE API endpoint with version if defined
     */
    private function getEndPoint(): string
    {/*  */
        $version = config('insee.sirene_api_version', '');
        if ($version !== '') {

            $version = '/V'. str_ireplace('v', '', $version);
        }

        return self::ENDPOINT_SIRENE . $version;
    }

    /**
     * HTTP Client GET request
     */
    private function get(string $endPoint, array $queryParameters = [])
    {
        return $this->client->get(
            self::API_URL . $endPoint . $this->prepareQueryParameters($queryParameters),
            $this->headers
        );
    }

    /**
     * HTTP Client POST request
     */
    private function post($endPoint)
    {
        return $this->client->post(
            self::API_URL . $endPoint . $this->prepareQueryParameters(),
            $this->headers
        );
    }

    /**
     * Add additional data to the query parameters
     */
    private function prepareQueryParameters(array $data = []): string
    {
        return $data || $this->additionalData
        ? '?' . http_build_query(array_merge($data, $this->additionalData))
        : '';
    }

    /**
     * Guzzle Handler
     */
    private function createGuzzleHandler()
    {
        return tap(HandlerStack::create(new CurlHandler()), function (HandlerStack $handlerStack) {
            $handlerStack->push(Middleware::retry(function ($retries, Psr7Request $request, Psr7Response $response = null, RequestException $exception = null) {
                if ($retries >= $this->maxRetries) {
                    return false;
                }

                if ($exception instanceof ConnectException) {
                    return true;
                }

                if ($response && $response->getStatusCode() >= 500) {
                    return true;
                }

                return false;
            }), $this->retryDelay);
        });
    }
}
