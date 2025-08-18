<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    private string $apiKey;
    private string $channelId;
    private string $senderId;
    private string $baseUrl;

    public function __construct()
    {
        $this->apiKey = config('services.termii.api_key') ?? '';
        $this->channelId = config('services.termii.channel_id') ?? '';
        $this->senderId = config('services.termii.sender_id') ?? '';
        $this->baseUrl = config('services.termii.base_url') ?? 'https://api.ng.termii.com/api';
    }

    public function sendMessage(string $phone, string $message): bool
    {
        // First try to send via WhatsApp Bot (preferred method)
        if ($this->sendViaWhatsAppBot($phone, $message)) {
            return true;
        }

        // Fallback to Termii if WhatsApp Bot fails
        return $this->sendViaTermii($phone, $message);
    }

        private function sendViaWhatsAppBot(string $phone, string $message): bool
    {
        try {
            $botUrl = config('services.whatsapp_bot.url', 'https://foodstuff-whatsapp-bot-1aeb07cc3b64.herokuapp.com');

            // Format phone number to international format
            $formattedPhone = $this->formatPhoneNumber($phone);

            $response = Http::timeout(10)->post("{$botUrl}/send-message", [
                'phone' => $formattedPhone,
                'message' => $message,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                if ($data['success'] ?? false) {
                    Log::info("WhatsApp message sent via bot successfully", [
                        'phone' => $phone,
                        'formatted_phone' => $formattedPhone,
                        'method' => 'whatsapp_bot',
                    ]);
                    return true;
                }
            }

            Log::warning("WhatsApp bot failed, falling back to Termii", [
                'phone' => $phone,
                'formatted_phone' => $formattedPhone,
                'response' => $response->body(),
                'status' => $response->status(),
            ]);
            return false;
        } catch (\Exception $e) {
            Log::warning("WhatsApp bot error, falling back to Termii", [
                'phone' => $phone,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    private function formatPhoneNumber(string $phone): string
    {
        // Remove any non-digit characters
        $cleaned = preg_replace('/\D/', '', $phone);

        // If it starts with 0, replace with 234 (Nigeria country code)
        if (str_starts_with($cleaned, '0')) {
            $cleaned = '234' . substr($cleaned, 1);
        }

        // If it doesn't start with country code, add 234
        if (!str_starts_with($cleaned, '234')) {
            $cleaned = '234' . $cleaned;
        }

        return $cleaned;
    }

    private function sendViaTermii(string $phone, string $message): bool
    {
        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/whatsapp/send", [
                'api_key' => $this->apiKey,
                'channel_id' => $this->channelId,
                'from' => $this->senderId,
                'to' => $phone,
                'type' => 'text',
                'text' => $message,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                Log::info("WhatsApp message sent via Termii successfully", [
                    'phone' => $phone,
                    'message_id' => $data['message_id'] ?? null,
                    'method' => 'termii',
                ]);
                return true;
            }

            Log::error("Failed to send WhatsApp message via Termii", [
                'phone' => $phone,
                'response' => $response->body(),
                'status' => $response->status(),
            ]);
            return false;
        } catch (\Exception $e) {
            Log::error("Termii service error", [
                'phone' => $phone,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    public function getMessageStatus(string $messageId): ?array
    {
        try {
            $response = Http::get("{$this->baseUrl}/insights/message-status", [
                'api_key' => $this->apiKey,
                'message_id' => $messageId,
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            return null;
        } catch (\Exception $e) {
            Log::error("Failed to get message status", [
                'message_id' => $messageId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    public function sendPaymentLink(string $phone, string $paymentUrl): bool
    {
        $message = "💳 *Payment Link*\n\n" .
                   "Click the link below to complete your payment:\n\n" .
                   "{$paymentUrl}\n\n" .
                   "Payment is secure and powered by Paystack. 🔒";

        return $this->sendMessage($phone, $message);
    }

    public function sendDeliveryUpdate(string $phone, string $status, string $message = ''): bool
    {
        $statusMessages = [
            'preparing' => '👨‍🍳 *Order Update*\n\nYour order is being prepared!',
            'ready_for_delivery' => '🚚 *Order Ready*\n\nYour order is ready for delivery!',
            'out_for_delivery' => '🛵 *Out for Delivery*\n\nYour order is on its way!',
            'delivered' => '✅ *Order Delivered*\n\nYour order has been delivered! Enjoy! 🎉'
        ];

        $statusMessage = $statusMessages[$status] ?? "📦 *Order Update*\n\n{$message}";

        if ($message) {
            $statusMessage .= "\n\n{$message}";
        }

        return $this->sendMessage($phone, $statusMessage);
    }

    public function sendOrderPreparing(string $phone, string $orderNumber): bool
    {
        $message = "👨‍🍳 *Order Update*\n\n" .
                   "Your order #{$orderNumber} is being prepared!\n\n" .
                   "We'll notify you when it's ready for delivery. 🚚";

        return $this->sendMessage($phone, $message);
    }

    public function sendOrderReady(string $phone, string $orderNumber): bool
    {
        $message = "🚚 *Order Ready*\n\n" .
                   "Your order #{$orderNumber} is ready for delivery!\n\n" .
                   "Our delivery agent will contact you soon. 📞";

        return $this->sendMessage($phone, $message);
    }

    public function sendOrderOutForDelivery(string $phone, string $orderNumber): bool
    {
        $message = "🛵 *Out for Delivery*\n\n" .
                   "Your order #{$orderNumber} is on its way!\n\n" .
                   "Please keep your phone nearby for delivery updates. 📱";

        return $this->sendMessage($phone, $message);
    }

    public function sendOrderDelivered(string $phone, string $orderNumber): bool
    {
        $message = "✅ *Order Delivered*\n\n" .
                   "Your order #{$orderNumber} has been delivered!\n\n" .
                   "Thank you for choosing FoodStuff Store! 🎉\n\n" .
                   "Enjoy your items! 🛒✨";

        return $this->sendMessage($phone, $message);
    }

    public function sendPaymentSuccess(string $phone, string $orderNumber, float $amount, ?string $sectionId = null): bool
    {
        $message = "💳 *Payment Successful*\n\n" .
                   "Your payment for order #{$orderNumber} has been confirmed!\n\n" .
                   "💰 Amount: ₦" . number_format($amount, 2) . "\n\n" .
                   "Your order is now being processed. 🚀";

        if ($sectionId) {
            $trackUrl = config('app.frontend_url', 'https://marketplace.foodstuff.store') . "/track_order?section_id={$sectionId}";
            $message .= "\n\n🔗 *Track your order:*\n{$trackUrl}";
        }

        return $this->sendMessage($phone, $message);
    }

    public function sendPaymentFailed(string $phone, string $orderNumber, ?string $sectionId = null): bool
    {
        $message = "❌ *Payment Failed*\n\n" .
                   "Your payment for order #{$orderNumber} was unsuccessful.\n\n" .
                   "Please try again or contact support for assistance. 📞";

        if ($sectionId) {
            $retryUrl = config('app.frontend_url', 'https://marketplace.foodstuff.store') . "?section_id={$sectionId}";
            $message .= "\n\n🔗 *Retry payment:*\n{$retryUrl}";
        }

        return $this->sendMessage($phone, $message);
    }

    public function sendOrderTrackingInfo(string $phone, string $orderNumber, string $status, ?string $sectionId = null): bool
    {
        $statusMessages = [
            'pending' => '⏳ *Order Pending*\n\nYour order is being processed.',
            'confirmed' => '✅ *Order Confirmed*\n\nYour order has been confirmed!',
            'paid' => '💰 *Payment Confirmed*\n\nPayment received! Order is being prepared.',
            'assigned' => '👨‍💼 *Agent Assigned*\n\nA delivery agent has been assigned to your order.',
            'preparing' => '👨‍🍳 *Preparing Order*\n\nYour order is being prepared in the kitchen.',
            'ready_for_delivery' => '📦 *Ready for Delivery*\n\nYour order is ready and waiting for pickup.',
            'out_for_delivery' => '🚚 *Out for Delivery*\n\nYour order is on its way to you!',
            'delivered' => '🎉 *Order Delivered*\n\nYour order has been delivered successfully!',
            'completed' => '🏁 *Order Completed*\n\nThank you for choosing FoodStuff Store!',
        ];

        $message = $statusMessages[$status] ?? "📋 *Order Update*\n\nOrder #{$orderNumber} status: " . ucfirst($status);

        if ($sectionId) {
            $trackUrl = config('app.frontend_url', 'https://marketplace.foodstuff.store') . "/track_order?section_id={$sectionId}";
            $message .= "\n\n🔗 *Track your order:*\n{$trackUrl}";
        }

        return $this->sendMessage($phone, $message);
    }

    public function sendOrderCancelled(string $phone, string $orderNumber, string $reason = ''): bool
    {
        $message = "🚫 *Order Cancelled*\n\n" .
                   "Your order #{$orderNumber} has been cancelled.";

        if ($reason) {
            $message .= "\n\nReason: {$reason}";
        }

        $message .= "\n\nPlease place a new order if needed. 🛒";

        return $this->sendMessage($phone, $message);
    }

    public function sendAgentReassigned(string $phone, string $orderNumber, string $agentName): bool
    {
        $message = "👤 *Agent Reassigned*\n\n" .
                   "Your order #{$orderNumber} has been reassigned to {$agentName}.\n\n" .
                   "You'll receive updates from your new agent. 📱";

        return $this->sendMessage($phone, $message);
    }
}
