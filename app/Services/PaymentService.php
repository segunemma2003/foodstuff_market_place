<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaymentService
{
    private string $secretKey;
    private string $publicKey;
    private string $baseUrl;

    public function __construct()
    {
        $this->secretKey = config('services.paystack.secret_key') ?? '';
        $this->publicKey = config('services.paystack.public_key') ?? '';
        $this->baseUrl = config('services.paystack.base_url', 'https://api.paystack.co');
    }

    public function initializePayment(array $data): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->secretKey,
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/transaction/initialize", [
                'amount' => $data['amount'],
                'email' => $data['email'],
                'reference' => $data['reference'],
                'callback_url' => $data['callback_url'],
                'metadata' => $data['metadata'] ?? [],
            ]);

            if ($response->successful()) {
                $result = $response->json();

                Log::info('Payment initialized successfully', [
                    'reference' => $data['reference'],
                    'amount' => $data['amount'],
                ]);

                return [
                    'authorization_url' => $result['data']['authorization_url'],
                    'reference' => $result['data']['reference'],
                    'access_code' => $result['data']['access_code'],
                ];
            }

            Log::error('Payment initialization failed', [
                'response' => $response->json(),
                'data' => $data,
            ]);

            throw new \Exception('Payment initialization failed: ' . ($response->json()['message'] ?? 'Unknown error'));
        } catch (\Exception $e) {
            Log::error('Payment service error', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);
            throw $e;
        }
    }

    public function verifyPayment(string $reference): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->secretKey,
            ])->get("{$this->baseUrl}/transaction/verify/{$reference}");

            if ($response->successful()) {
                $result = $response->json();

                Log::info('Payment verification completed', [
                    'reference' => $reference,
                    'status' => $result['data']['status'],
                ]);

                return [
                    'status' => $result['data']['status'],
                    'amount' => $result['data']['amount'] / 100, // Convert from kobo to naira
                    'reference' => $result['data']['reference'],
                    'metadata' => $result['data']['metadata'] ?? [],
                    'channel' => $result['data']['channel'],
                    'paid_at' => $result['data']['paid_at'],
                ];
            }

            Log::error('Payment verification failed', [
                'reference' => $reference,
                'response' => $response->json(),
            ]);

            throw new \Exception('Payment verification failed');
        } catch (\Exception $e) {
            Log::error('Payment verification error', [
                'error' => $e->getMessage(),
                'reference' => $reference,
            ]);
            throw $e;
        }
    }

    public function transferToAgent(string $recipientCode, int $amount, string $reference): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->secretKey,
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/transfer", [
                'source' => 'balance',
                'amount' => $amount * 100, // Convert to kobo
                'recipient' => $recipientCode,
                'reference' => $reference,
                'reason' => 'Agent commission payment',
            ]);

            if ($response->successful()) {
                $result = $response->json();

                Log::info('Agent transfer initiated', [
                    'reference' => $reference,
                    'amount' => $amount,
                    'recipient' => $recipientCode,
                ]);

                return [
                    'transfer_code' => $result['data']['transfer_code'],
                    'reference' => $result['data']['reference'],
                    'status' => $result['data']['status'],
                ];
            }

            Log::error('Agent transfer failed', [
                'response' => $response->json(),
                'reference' => $reference,
            ]);

            throw new \Exception('Agent transfer failed: ' . ($response->json()['message'] ?? 'Unknown error'));
        } catch (\Exception $e) {
            Log::error('Agent transfer error', [
                'error' => $e->getMessage(),
                'reference' => $reference,
            ]);
            throw $e;
        }
    }

    public function createTransferRecipient(string $accountNumber, string $bankCode, string $name): string
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->secretKey,
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/transferrecipient", [
                'type' => 'nuban',
                'name' => $name,
                'account_number' => $accountNumber,
                'bank_code' => $bankCode,
            ]);

            if ($response->successful()) {
                $result = $response->json();

                Log::info('Transfer recipient created', [
                    'name' => $name,
                    'account_number' => $accountNumber,
                ]);

                return $result['data']['recipient_code'];
            }

            Log::error('Transfer recipient creation failed', [
                'response' => $response->json(),
            ]);

            throw new \Exception('Transfer recipient creation failed');
        } catch (\Exception $e) {
            Log::error('Transfer recipient error', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
