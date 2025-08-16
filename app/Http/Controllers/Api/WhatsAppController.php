<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WhatsAppService;
use App\Services\OrderService;
use App\Models\WhatsappSession;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class WhatsAppController extends Controller
{
    public function __construct(
        private WhatsAppService $whatsAppService,
        private OrderService $orderService
    ) {}

    public function webhook(Request $request): JsonResponse
    {
        try {
            $data = $request->all();
            Log::info('WhatsApp webhook received', $data);

            // Handle Termii webhook
            if (isset($data['event']) && $data['event'] === 'message') {
                $phone = $data['data']['from'] ?? null;
                $message = $data['data']['text'] ?? null;
                $messageId = $data['data']['message_id'] ?? null;

                if ($phone && $message) {
                    $this->processMessage($phone, $message, $messageId);
                }
            }

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            Log::error('WhatsApp webhook error: ' . $e->getMessage());
            return response()->json(['success' => false], 500);
        }
    }

    public function sendMessage(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'phone' => 'required|string',
                'message' => 'required|string',
            ]);

            $success = $this->whatsAppService->sendMessage(
                $request->phone,
                $request->message
            );

            if ($success) {
                return response()->json([
                    'success' => true,
                    'message' => 'Message sent successfully',
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to send message',
            ], 500);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }



    public function createOrder(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'whatsapp_number' => 'required|string',
                'items' => 'required|array',
                'items.*.name' => 'required|string',
                'items.*.quantity' => 'required|string',
                'items.*.notes' => 'nullable|string',
            ]);

            // Create or update WhatsApp session with cart items
            $session = WhatsappSession::updateOrCreate(
                ['whatsapp_number' => $request->whatsapp_number, 'status' => 'active'],
                [
                    'session_id' => uniqid('session_'),
                    'cart_items' => $request->items,
                    'last_activity' => now(),
                ]
            );

            // Generate a temporary order ID for frontend reference
            $tempOrderId = 'TEMP_' . time() . '_' . substr(md5($request->whatsapp_number), 0, 6);

            return response()->json([
                'success' => true,
                'order_id' => $tempOrderId,
                'order_number' => $tempOrderId,
                'message' => 'Cart items saved successfully',
                'session_id' => $session->session_id,
            ]);

        } catch (\Exception $e) {
            Log::error('Error creating WhatsApp session: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error saving cart items',
            ], 500);
        }
    }

    private function processMessage(string $phone, string $message, ?string $messageId = null): void
    {
        try {
            $session = $this->getOrCreateSession($phone);
            $response = $this->handleMessage($session, $message);

            if ($response) {
                $this->whatsAppService->sendMessage($phone, $response);
            }
        } catch (\Exception $e) {
            Log::error('Error processing message: ' . $e->getMessage());
        }
    }

    private function getOrCreateSession(string $phone): WhatsappSession
    {
        $session = WhatsappSession::where('whatsapp_number', $phone)
            ->where('status', 'active')
            ->first();

        if (!$session) {
            $session = WhatsappSession::create([
                'whatsapp_number' => $phone,
                'session_id' => uniqid('session_'),
                'status' => 'active',
                'current_step' => 'greeting',
                'cart_items' => [],
                'last_activity' => now(),
            ]);
        } else {
            $session->update(['last_activity' => now()]);
        }

        return $session;
    }

    private function handleMessage(WhatsappSession $session, string $message): ?string
    {
        $message = strtolower(trim($message));

        // Check for session resumption
        if (in_array($message, ['hi', 'hello', 'start', 'menu'])) {
            return $this->handleGreeting($session);
        }

        // Check for done command
        if ($message === 'done') {
            return $this->handleDone($session);
        }

        // Check for cart commands
        if ($message === 'view cart') {
            return $this->handleViewCart($session);
        }

        if ($message === 'clear cart') {
            return $this->handleClearCart($session);
        }

        // Process based on current step
        switch ($session->current_step) {
            case 'greeting':
                return $this->handleGreeting($session);
            case 'adding_items':
                return $this->handleAddingItems($session, $message);
            default:
                return $this->handleGreeting($session);
        }
    }

    private function handleGreeting(WhatsappSession $session): string
    {
        $session->update(['current_step' => 'adding_items']);

        return "🛒 *Welcome to FoodStuff Store!* 🛒\n\n" .
               "I'm here to help you order your foodstuff items.\n\n" .
               "📝 *How to order:*\n" .
               "• Simply tell me what you need (e.g., '2kg rice, 1kg beans')\n" .
               "• I'll add items to your cart\n" .
               "• Type 'done' when you're finished\n\n" .
               "🛍️ *Available commands:*\n" .
               "• 'view cart' - See your current items\n" .
               "• 'clear cart' - Remove all items\n" .
               "• 'done' - Complete your order\n\n" .
               "What would you like to order today? 🥕🍚🥩";
    }

    private function handleAddingItems(WhatsappSession $session, string $message): string
    {
        $items = $this->parseItemText($message);

        if (empty($items)) {
            return "❓ I didn't understand that. Please tell me what you need (e.g., '2kg rice, 1kg beans') or type 'done' to finish your order.";
        }

        // Add items to cart
        foreach ($items as $item) {
            $session->addToCart($item['name'], $item['quantity'], $item['unit']);
        }

        $cartCount = count($session->cart_items);

        return "✅ *Added to cart:*\n" .
               implode("\n", array_map(function($item) {
                   return "• {$item['quantity']} {$item['unit']} {$item['name']}";
               }, $items)) .
               "\n\n🛒 *Cart total:* {$cartCount} items\n\n" .
               "What else would you like to add? Or type 'done' to complete your order.";
    }

    private function handleDone(WhatsappSession $session): string
    {
        if (empty($session->cart_items)) {
            return "🛒 Your cart is empty. Please add some items first!";
        }

        $order = $this->createOrderFromSession($session);
        $session->update(['status' => 'completed']);

        $frontendUrl = config('app.frontend_url', 'https://marketplace.foodstuff.store');

        return "🎉 *Order Created Successfully!* 🎉\n\n" .
               "📋 *Order #:* {$order->order_number}\n" .
               "🛒 *Items:* " . count($session->cart_items) . " items\n\n" .
               "🔗 *Next Steps:*\n" .
               "Click the link below to:\n" .
               "• Choose your delivery location\n" .
               "• Select the nearest market\n" .
               "• Complete payment\n\n" .
               "{$frontendUrl}/checkout?order_id={$order->id}\n\n" .
               "Thank you for choosing FoodStuff Store! 🛒✨";
    }

    private function handleViewCart(WhatsappSession $session): string
    {
        if (empty($session->cart_items)) {
            return "🛒 Your cart is empty. Start adding items!";
        }

        $items = array_map(function($item) {
            return "• {$item['quantity']} {$item['unit']} {$item['name']}";
        }, $session->cart_items);

        return "🛒 *Your Cart:*\n\n" .
               implode("\n", $items) .
               "\n\nType 'done' to complete your order or add more items!";
    }

    private function handleClearCart(WhatsappSession $session): string
    {
        $session->clearCart();
        return "🗑️ Cart cleared! Start adding items again or type 'done' to finish.";
    }

    private function parseItemText(string $text): array
    {
        $items = [];

        // Simple parsing - you can enhance this
        $patterns = [
            '/(\d+)\s*(kg|g|liters?|l|pieces?|pcs?|bottles?|bags?)\s+(.+)/i',
            '/(\d+)\s+(.+)/i'
        ];

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $text, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $items[] = [
                        'quantity' => (int)$match[1],
                        'unit' => isset($match[2]) ? strtolower($match[2]) : 'piece',
                        'name' => trim($match[count($match) - 1])
                    ];
                }
            }
        }

        return $items;
    }

    private function createOrderFromSession(WhatsappSession $session): \App\Models\Order
    {
        $order = \App\Models\Order::create([
            'order_number' => 'FS' . time(),
            'whatsapp_number' => $session->whatsapp_number,
            'customer_name' => 'WhatsApp Customer',
            'delivery_address' => 'To be provided on frontend',
            'total_amount' => 0, // Will be calculated on frontend
            'status' => 'pending',
            'market_id' => null, // Will be selected on frontend
            'agent_id' => null, // Will be assigned later
        ]);

        return $order;
    }
}
