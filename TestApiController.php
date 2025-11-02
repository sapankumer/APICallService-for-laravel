<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\ApiClientService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;

class TestApiController extends Controller
{
    /**
     * Display a listing of the resource.
     */
 protected $apiService;

    // সার্ভিসটিকে স্বয়ংক্রিয়ভাবে ইনজেক্ট (Inject) করা
    public function __construct(ApiClientService $apiService)
    {
        $this->apiService = $apiService;
    }


  public function getTestData()
    {
        $url = 'https://jsonplaceholder.typicode.com/users';
        $headers = [
            'Accept' => 'application/json',
        ];

        $response = $this->apiService->callApi('GET', $url, [], $headers);

        if ($response && $response->successful()) {
            return $response->json(); 
        }

    

        //  ($response = null)
        if (!$response) {
            return response()->json([
                'error' => 'Service connection failed or timed out'
            ], 504); // 504 Gateway Timeout 
        }

     
        // API থেকে আসা এরর মেসেজটি দেখানোর চেষ্টা করি
        $errorMessage = $response->json('message') ?? $response->json('error') ?? 'Failed to fetch data';

        return response()->json([
            'error' => $errorMessage
        ], $response->status()); 
    }


  public function getMultipleData()
{
   $requests = [
            'users' => [
                'method' => 'GET',
                'url' => 'https://jsonplaceholder.typicode.com/users',
                'headers' => ['Accept' => 'application/json',],
            ],
            'create_post' => [
                'method' => 'POST',
                'url' => 'https://jsonplaceholder.typicode.com/posts',
                'headers' => ['Accept' => 'application/json'],
                'payload' => [ // POST রিকোয়েস্টের জন্য ডেটা (Body)
                    'title' => 'Test Post Title',
                    'body' => 'This is a test body.',
                    'userId' => 1,
                ]
            ],

            // --- একটি স্লো GET রিকোয়েস্ট (টাইমআউট পরীক্ষার জন্য) ---
            'slow_request' => [
                'method' => 'GET',
                'url' => 'https://httpbin.org/delay/10', // 10 সেকেন্ড ডিলে
            ],
        ];
        $responses = $this->apiService->callMultipleApis($requests);
        $data = [];

        foreach ($responses as $key => $response) {
            $data[$key] = match(true) {
                $response instanceof Response && $response->successful() => $response->json(),

                $response instanceof Response => [
                    'error'   => "API request failed for '{$key}'",
                    'status'  => $response->status(),
                    'details' => $response->json() ?? $response->body()
                ],
                $response instanceof ConnectionException => [
                    'error'   => "Connection error for '{$key}' (Timeout or failed)",
                    'message' => $response->getMessage()
                ],
                default => ['error' => "An unknown error occurred for '{$key}'"]
            };
        }
        return $data;
}
  
}
