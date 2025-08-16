<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaystackService
{
    private string $baseUrl = 'https://api.paystack.co';
    private string $secretKey;

    public function __construct()
    {
        $this->secretKey = config('services.paystack.secret_key') ?? '';
    }

    /**
     * Get list of all banks from Paystack
     */
    public function getBanks(): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->secretKey,
                'Accept' => 'application/json',
            ])->get($this->baseUrl . '/bank');

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'data' => $data['data'] ?? [],
                    'message' => 'Banks retrieved successfully'
                ];
            }

            Log::error('Paystack banks API error: ' . $response->body());
            return [
                'success' => false,
                'message' => 'Failed to retrieve banks from Paystack',
                'error' => $response->json()['message'] ?? 'Unknown error'
            ];

        } catch (\Exception $e) {
            Log::error('Paystack banks API exception: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error connecting to Paystack',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Verify bank account number
     */
    public function verifyAccountNumber(string $accountNumber, string $bankCode): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->secretKey,
                'Accept' => 'application/json',
            ])->get($this->baseUrl . '/bank/resolve', [
                'account_number' => $accountNumber,
                'bank_code' => $bankCode
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'data' => [
                        'account_name' => $data['data']['account_name'] ?? '',
                        'account_number' => $data['data']['account_number'] ?? '',
                        'bank_id' => $data['data']['bank_id'] ?? '',
                    ],
                    'message' => 'Account verified successfully'
                ];
            }

            Log::error('Paystack account verification error: ' . $response->body());
            return [
                'success' => false,
                'message' => 'Failed to verify account number',
                'error' => $response->json()['message'] ?? 'Invalid account number or bank code'
            ];

        } catch (\Exception $e) {
            Log::error('Paystack account verification exception: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error connecting to Paystack',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get bank details by bank code
     */
    public function getBankByCode(string $bankCode): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->secretKey,
                'Accept' => 'application/json',
            ])->get($this->baseUrl . '/bank/' . $bankCode);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'data' => $data['data'] ?? [],
                    'message' => 'Bank details retrieved successfully'
                ];
            }

            Log::error('Paystack bank details API error: ' . $response->body());
            return [
                'success' => false,
                'message' => 'Failed to retrieve bank details',
                'error' => $response->json()['message'] ?? 'Unknown error'
            ];

        } catch (\Exception $e) {
            Log::error('Paystack bank details API exception: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error connecting to Paystack',
                'error' => $e->getMessage()
            ];
        }
    }
}
