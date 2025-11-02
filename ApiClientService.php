<?php

namespace App\Services;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
class ApiClientService
{
    /**
     * Single API Call (GET, POST, PUT, etc.)
     *
     * @param string $method (get, post, put, patch, delete)
     * @param string $url
     * @param array $payload (POST, PUT, PATCH)
     * @param array $headers (Custom Headers)
     * @return \Illuminate\Http\Client\Response|null
     */
    public function callApi(string $method, string $url, array $payload = [], array $headers = [])
    {
        $method = strtolower($method);

        try {
            //set 5 second timeout
            $response = Http::timeout(5)
                ->withHeaders($headers)
                ->$method($url, $payload);

            if ($response->failed()) {
                Log::warning("API call failed with status: {$response->status()}", [
                    'url' => $url,
                    'method' => $method,
                    'response_body' => $response->body(),
                ]);
            }

            return $response;

        } catch (ConnectionException $e) {
            // This will happen when there is no response within 5 seconds (Timeout).
            Log::error("API call timed out (5 seconds limit) or connection failed.", [
                'url' => $url,
                'method' => $method,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return null;

        } catch (\Exception $e) {
            Log::error("An unexpected error occurred during the API call.", [
                'url' => $url,
                'method' => $method,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return null;
        }
    }


    /**
     * Multiple API Call
     * @param array $requests  ['method', 'url', 'payload', 'headers']
     * @return array|null
     */
    public function callMultipleApis(array $requests)
    {
        try {
            $responses = Http::pool(function (Pool $pool) use ($requests) {
                foreach ($requests as $key => $request) {
                    $method = strtolower($request['method'] ?? 'get');
                    $url = $request['url'] ?? '';
                    $payload = $request['payload'] ?? [];
                    $headers = $request['headers'] ?? [];

                    //set 5 second timeout
                    $pool->as($key)
                        ->timeout(5)
                        ->withHeaders($headers)
                        ->$method($url, $payload);
                }
            });

            foreach ($responses as $key => $response) {
                if ($response instanceof ConnectionException) {
                    Log::error("API call in pool timed out (5 seconds limit) or connection failed.", [
                        'request_key' => $key,
                        'url' => $requests[$key]['url'] ?? 'N/A',
                        'error_message' => $response->getMessage(),
                        'file' => $response->getFile(),
                        'line' => $response->getLine(),
                    ]);
                }
                elseif ($response instanceof Response && $response->failed()) {
                    Log::warning("API call in pool failed with status: {$response->status()}", [
                        'request_key' => $key,
                        'url' => $requests[$key]['url'] ?? 'N/A',
                        'response_body' => $response->body(),
                    ]);
                }
            }
            return $responses;

        } catch (\Exception $e) {
            Log::error("A critical error occurred while setting up the API pool.", [
                'error_message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }
}
