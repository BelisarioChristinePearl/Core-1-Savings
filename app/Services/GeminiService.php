<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class GeminiService {
    protected $apiKey;
    protected $baseUrl;
    protected $model;
    protected $systemContext;

    public function __construct()
    {
        // Get these from your .env file
        $this->apiKey = env('GEMINI_API_KEY');
        $this->model = env('GEMINI_MODEL', 'gemini-2.0-flash'); // Default to 2.0-flash, but allow override in .env
        $this->baseUrl = "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent";

        // System context for the microfinance savings management
        $this->systemContext = "
            You are SavingsBot, a helpful assistant for Microfinance's Savings Management System.
            Provide information about our savings accounts and services with these key details:

            - Individual savings accounts offer 5% annual interest rate
            - Corporate savings accounts offer 7.5% annual interest rate
            - Minimum deposit for individual accounts is ₱500
            - Minimum deposit for corporate accounts is ₱5,000
            - Interest is calculated daily and credited monthly
            - We offer secure online access to accounts
            - Withdrawals are available anytime without penalty
            - We provide financial literacy resources
            - Monthly account statements are available electronically or by mail

            Be helpful, friendly, and concise in your responses. If you don't know an answer, direct users to call our customer service at (02)895 29232.
        ";
    }

    public function generateResponse(string $prompt)
    {
        try {
            // Check if API key is available
            if (empty($this->apiKey)) {
                Log::error('Gemini API key is missing');
                return 'Error: API key is not configured. Please check your .env file.';
            }

            // Prepare request data with system context
            $requestData = [
                "contents" => [
                    [
                        "role" => "user",
                        "parts" => [
                            ["text" => $this->systemContext]
                        ]
                    ],
                    [
                        "role" => "model",
                        "parts" => [
                            ["text" => "I understand. I'm SavingsBot for Bestlink Microfinance. I'll provide helpful information about your savings products and services."]
                        ]
                    ],
                    [
                        "role" => "user",
                        "parts" => [
                            ["text" => trim($prompt)]
                        ]
                    ]
                ],
                "generationConfig" => [
                    "temperature" => 0.2,
                    "topP" => 0.8,
                    "topK" => 40
                ]
            ];

            // Log the request (without sensitive data)
            Log::debug('Sending request to Gemini API', [
                'model' => $this->model,
                'prompt_length' => strlen($prompt)
            ]);

            // Make the request using Laravel's HTTP client
            $response = Http::withHeaders([
                'Content-Type' => 'application/json'
            ])->post("{$this->baseUrl}?key={$this->apiKey}", $requestData);

            // Log the response status
            Log::debug('Gemini API response received', [
                'status_code' => $response->status(),
                'successful' => $response->successful()
            ]);

            // Handle non-successful responses
            if (!$response->successful()) {
                Log::error('Gemini API returned error', [
                    'status_code' => $response->status(),
                    'response' => $response->body()
                ]);

                return 'Error from Gemini API (Status: ' . $response->status() . '). Please try again later.';
            }

            // Parse the response
            $responseData = $response->json();

            // Check for expected response structure
            if (isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
                return trim($responseData['candidates'][0]['content']['parts'][0]['text']);
            } else {
                Log::warning('Unexpected Gemini API response structure', [
                    'response' => $responseData
                ]);

                // Check for specific error messages
                if (isset($responseData['error'])) {
                    return 'Error from Gemini API: ' . ($responseData['error']['message'] ?? 'Unknown error');
                }

                return 'No response generated. Please try again.';
            }
        } catch (Exception $e) {
            Log::error('Gemini API error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return 'Error connecting to AI service: ' . $e->getMessage();
        }
    }
}
