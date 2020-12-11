<?php
namespace Cardinity\Http\Guzzle;

use Cardinity\Exception;
use Cardinity\Http\ClientInterface;
use Cardinity\Http\Guzzle\ExceptionMapper;
use Cardinity\Method\MethodInterface;
use GuzzleHttp6;
use GuzzleHttp6\Exception\ClientException;

/**
 * Adapter for GuzzleHttp6 client
 */
class ClientAdapter implements ClientInterface
{
    /** @type GuzzleHttp6\ClientInterface */
    private $client;

    /** @type ExceptionMapper */
    private $mapper;

    /**
     * @param GuzzleHttp6\ClientInterface $client
     * @param ExceptionMapper $mapper
     */
    public function __construct(
        GuzzleHttp6\ClientInterface $client,
        ExceptionMapper $mapper
    ) {
        $this->client = $client;
        $this->mapper = $mapper;
    }

    /**
     * Send HTTP request
     * @param MethodInterface $method
     * @param string $requestMethod POST|GET|PATCH
     * @param string $url http URL
     * @param array $options query options. Query values goes under 'body' key.
     *
     * @return array
     */
    public function sendRequest(
        MethodInterface $method,
        $requestMethod,
        $url,
        array $options = []
    ) {
        try {
            $response = $this->client->request($requestMethod, $url, $options);
            return json_decode($response->getBody(), true);
        } catch (ClientException $e) {
            throw $this->mapper->get($e, $method);
        } catch (\Exception $e) {
            throw new Exception\UnexpectedError('Unexpected error', $e->getCode(), $e);
        }
    }
}
