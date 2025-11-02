# APICallService-for-laravel

A robust, reusable API client service for Laravel applications. It simplifies making single and concurrent (pooled) API calls with built-in 5-second timeouts, robust error logging, and standardized response handling.

Features: 

All RESTful Methods: Handles GET, POST, PUT, PATCH, and DELETE requests.

Concurrent Calls: Make multiple API calls simultaneously using Http::pool, drastically reducing load time.

Timeout Protection: All API calls automatically time out after 5 seconds to prevent your application from hanging on slow external services.

Robust Error Logging: Automatically logs connection failures, timeouts, and API server errors (4xx/5xx) directly to your laravel.log file for easy debugging.

Easy Dependency Injection: Designed as a service, it can be injected into any Controller or other Service class.

Standardized Responses: The controller examples demonstrate how to format all successful and failed responses into a consistent JSON structure.

1. Setup
1. Create the Service: Place the service code in app/Services/ApiClientService.php. (You have already done this).

2. Inject the Service: In your controller (e.g., TestApiController), inject the service in the constructor for easy access.


<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\ApiClientService; // <-- Import the service
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;

class TestApiController extends Controller
{
    protected $apiService;

    // The service is automatically injected by Laravel
    public function __construct(ApiClientService $apiService)
    {
        $this->apiService = $apiService;
    }
    
    // ... your methods ...
}

2. Usage Examples
Example 1: Single API Call (callApi)
This example shows how to fetch data from a single endpoint and format the response (both success and error) into a standard structure.

/**
 * Example of a single API call
 */
public function getTestData()
{
    $url = 'https://jsonplaceholder.typicode.com/users';
    $headers = [
        'Accept' => 'application/json',
    ];

    $response = $this->apiService->callApi('GET', $url, [], $headers);

    // Case 1: Successful API Call (Status 200-299)
    if ($response && $response->successful()) {
        return response()->json([
            'status' => true,
            'statusCode' => $response->status(), // e.g., 200
            'data' => $response->json()
        ], 200);
    }

    // Case 2: Connection Failed or Timed Out (Service returned null)
    if (!$response) {
        return response()->json([
            'status' => false,
            'statusCode' => 504, // 504 Gateway Timeout
            'error' => 'Service connection failed or timed out'
        ], 504);
    }

    // Case 3: API Responded with an error (Status 4xx or 5xx)
    $errorMessage = $response->json('message') ?? $response->json('error') ?? 'Failed to fetch data';

    return response()->json([
        'status' => false,
        'statusCode' => $response->status(), // e.g., 404, 501
        'error' => $errorMessage
    ], $response->status());
}


Example 2: Multiple Concurrent Calls (callMultipleApis)
This example runs three API calls (a GET, a POST, and a slow GET) at the same time. It then dynamically processes all responses using a match statement (PHP 8.0+).

/**
 * Example of multiple concurrent API calls
 */
public function getMultipleData()
{
    $requests = [
        'users' => [
            'method' => 'GET',
            'url' => 'https://jsonplaceholder.typicode.com/users',
            'headers' => ['Accept' => 'application/json'],
        ],
        'create_post' => [
            'method' => 'POST',
            'url' => 'https://jsonplaceholder.typicode.com/posts',
            'headers' => ['Accept' => 'application/json'],
            'payload' => [
                'title' => 'Test Post Title',
                'body' => 'This is a test body.',
                'userId' => 1,
            ]
        ],
        // This request will time out (5s limit)
        'slow_request' => [
            'method' => 'GET',
            'url' => 'https://httpbin.org/delay/10', // 10-second delay
        ],
    ];
    
    // All requests are sent at the same time
    $responses = $this->apiService->callMultipleApis($requests);
    $data = [];

    // Process the results
    foreach ($responses as $key => $response) {
        $data[$key] = match(true) {
            // Case 1: Successful
            $response instanceof Response && $response->successful() => $response->json(),

            // Case 2: API Error (e.g., 404, 500)
            $response instanceof Response => [
                'error'   => "API request failed for '{$key}'",
                'status'  => $response->status(),
                'details' => $response->json() ?? $response->body()
            ],
            
            // Case 3: Timeout or Connection Error
            $response instanceof ConnectionException => [
                'error'   => "Connection error for '{$key}' (Timeout or failed)",
                'message' => $response->getMessage()
            ],
            
            // Case 4: Unknown
            default => ['error' => "An unknown error occurred for '{$key}'"]
        };
    }
    
    return $data;
}
