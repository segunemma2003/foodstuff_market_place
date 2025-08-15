<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WhatsappSession;
use App\Models\Product;
use App\Models\Order;
use App\Services\WhatsAppService;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

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

            // Handle WhatsApp webhook events
            if (isset($data['entry'][0]['changes'][0]['value']['messages'][0])) {
                $message = $data['entry'][0]['changes'][0]['value']['messages'][0];
                $this->processMessage($message);
            }

            return response()->json(['status' => 'success']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function sendMessage(Request $request): JsonResponse
    {
        $request->validate([
            'phone' => 'required|string',
            'message' => 'required|string',
        ]);

        try {
            $this->whatsAppService->sendMessage($request->phone, $request->message);
            return response()->json(['status' => 'success']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    private function processMessage(array $message): void
    {
        $phone = $message['from'];
        $text = strtolower(trim($message['text']['body'] ?? ''));

        // Check for session resumption commands
        if (in_array($text, ['continue', 'resume', 'back', 'return'])) {
            $this->handleSessionResumption($phone);
            return;
        }

        // Check for session cancellation
        if (in_array($text, ['cancel', 'stop', 'end', 'quit'])) {
            $this->handleSessionCancellation($phone);
            return;
        }

        // Get or create session
        $session = $this->getOrCreateSession($phone);

        // Update session activity
        $session->updateActivity();

        // Process based on current step
        switch ($session->current_step) {
            case 'greeting':
                $this->handleGreeting($session, $text);
                break;
            case 'menu':
                $this->handleMenu($session, $text);
                break;
            case 'adding_items':
                $this->handleAddingItems($session, $text);
                break;
            case 'confirming_order':
                $this->handleConfirmingOrder($session, $text);
                break;
            default:
                $this->handleGreeting($session, $text);
        }
    }

    private function getOrCreateSession(string $phone): WhatsappSession
    {
        // Check for existing active session
        $session = WhatsappSession::where('whatsapp_number', $phone)
            ->where('status', 'active')
            ->first();

        if (!$session) {
            // Check for completed session and offer to start new one
            $completedSession = WhatsappSession::where('whatsapp_number', $phone)
                ->where('status', 'completed')
                ->where('last_activity', '>', now()->subDays(7)) // Within last 7 days
                ->first();

            if ($completedSession) {
                $this->offerNewSession($phone);
            }

            // Check for expired session and remind user
            $expiredSession = WhatsappSession::where('whatsapp_number', $phone)
                ->where('status', 'active')
                ->where('last_activity', '<', now()->subHours(24))
                ->first();

            if ($expiredSession) {
                $this->sendExpiredSessionMessage($phone, $expiredSession);
            }

            // Create new session
            $session = WhatsappSession::create([
                'whatsapp_number' => $phone,
                'session_id' => Str::uuid(),
                'status' => 'active',
                'current_step' => 'greeting',
                'cart_items' => [],
                'last_activity' => now(),
            ]);
        }

        return $session;
    }

    private function offerNewSession(string $phone): void
    {
        $message = "🎉 *Welcome back!*\n\n";
        $message .= "Your previous order has been completed successfully!\n\n";
        $message .= "🛒 *Ready to order again?*\n\n";
        $message .= "Just say *'hi'* or *'start shopping'* to begin a new order!\n\n";
        $message .= "💡 *Quick start:*\n";
        $message .= "• *'2kg rice'* - Add items directly\n";
        $message .= "• *'menu'* - See categories\n";
        $message .= "• *'help'* - Get assistance";

        $this->whatsAppService->sendMessage($phone, $message);
    }

    private function handleGreeting(WhatsappSession $session, string $text): void
    {
        $greetingKeywords = ['hi', 'hello', 'hey', 'start', 'begin', 'order', 'shop', 'buy'];

        if (in_array($text, $greetingKeywords)) {
            $message = "👋 *Welcome to FoodStuff Store!* 🛒\n\n";
            $message .= "I'm your personal shopping assistant! I can help you order fresh groceries and food items.\n\n";
            $message .= "🎯 *What would you like to do?*\n\n";
            $message .= "🛒 *Start Shopping* - Begin adding items to your cart\n";
            $message .= "📋 *View Menu* - See available product categories\n";
            $message .= "❓ *Help* - Get assistance\n";
            $message .= "📞 *Contact* - Speak with our team\n\n";
            $message .= "Just type what you'd like to do!";

            $this->whatsAppService->sendMessage($session->whatsapp_number, $message);
            $session->update(['current_step' => 'menu']);
        } else {
            $message = "👋 Hi there! I'm your FoodStuff Store assistant.\n\n";
            $message .= "To get started, just say *'hi'* or *'start shopping'* and I'll help you order groceries!\n\n";
            $message .= "Need help? Type *'help'* anytime.";

            $this->whatsAppService->sendMessage($session->whatsapp_number, $message);
        }
    }

    private function handleMenu(WhatsappSession $session, string $text): void
    {
        if (in_array($text, ['start shopping', 'shop', 'buy', 'order', 'add items'])) {
            $this->startShopping($session);
        } elseif (in_array($text, ['menu', 'categories', 'view menu'])) {
            $this->showCategories($session);
        } elseif (in_array($text, ['help', 'support'])) {
            $this->showHelp($session);
        } elseif (in_array($text, ['contact', 'call', 'speak'])) {
            $this->showContact($session);
        } else {
            $message = "🤔 I didn't quite understand that. Here are your options:\n\n";
            $message .= "🛒 Type *'start shopping'* to begin your order\n";
            $message .= "📋 Type *'menu'* to see categories\n";
            $message .= "❓ Type *'help'* for assistance\n";
            $message .= "📞 Type *'contact'* to speak with us";

            $this->whatsAppService->sendMessage($session->whatsapp_number, $message);
        }
    }

    private function startShopping(WhatsappSession $session): void
    {
        $message = "🛒 *Let's start shopping!* 🎉\n\n";
        $message .= "Simply tell me what you'd like to order. You can say things like:\n\n";
        $message .= "• *'2kg rice'*\n";
        $message .= "• *'5 pieces tomatoes'*\n";
        $message .= "• *'1 bag of beans'*\n";
        $message .= "• *'3 bottles of oil'*\n\n";
        $message .= "I'll add each item to your cart and show you what you have.\n\n";
        $message .= "When you're done, just type *'done'* to proceed to checkout!\n\n";
        $message .= "💡 *Tip:* You can also type *'view cart'* anytime to see your current items.";

        $this->whatsAppService->sendMessage($session->whatsapp_number, $message);
        $session->update(['current_step' => 'adding_items']);
    }

    private function showCategories(WhatsappSession $session): void
    {
        $categories = [
            '🌾 Grains & Cereals' => 'Rice, Beans, Corn, Wheat',
            '🥬 Vegetables' => 'Tomatoes, Onions, Peppers, Leafy Greens',
            '🍎 Fruits' => 'Bananas, Oranges, Apples, Pineapples',
            '🥩 Meat & Fish' => 'Chicken, Beef, Fish, Eggs',
            '🥛 Dairy & Eggs' => 'Milk, Cheese, Yogurt, Eggs',
            '🌶️ Spices & Seasonings' => 'Salt, Pepper, Curry, Herbs',
            '🥤 Beverages' => 'Juices, Soft Drinks, Water',
            '🍿 Snacks' => 'Biscuits, Chips, Nuts, Candies'
        ];

        $message = "📋 *Available Categories:*\n\n";

        foreach ($categories as $category => $examples) {
            $message .= "{$category}\n";
            $message .= "└ {$examples}\n\n";
        }

        $message .= "🛒 Ready to shop? Type *'start shopping'* to begin adding items!";

        $this->whatsAppService->sendMessage($session->whatsapp_number, $message);
    }

    private function showHelp(WhatsappSession $session): void
    {
        $message = "❓ *How can I help you?*\n\n";
        $message .= "🛒 *Ordering:*\n";
        $message .= "• Just tell me what you want: *'2kg rice'*\n";
        $message .= "• Type *'done'* when finished\n";
        $message .= "• View your cart anytime: *'view cart'*\n\n";
        $message .= "🔄 *Managing Orders:*\n";
        $message .= "• Type *'continue'* to resume previous session\n";
        $message .= "• Type *'cancel'* to start over\n";
        $message .= "• Type *'help'* anytime for assistance\n\n";
        $message .= "📞 *Need more help?*\n";
        $message .= "Call us: +234 801 234 5678\n";
        $message .= "Email: support@foodstuff.store\n\n";
        $message .= "Ready to shop? Type *'start shopping'*!";

        $this->whatsAppService->sendMessage($session->whatsapp_number, $message);
    }

    private function showContact(WhatsappSession $session): void
    {
        $message = "📞 *Contact Us*\n\n";
        $message .= "We're here to help! You can reach us through:\n\n";
        $message .= "📱 *WhatsApp:* +234 801 234 5678\n";
        $message .= "📧 *Email:* support@foodstuff.store\n";
        $message .= "🌐 *Website:* www.foodstuff.store\n";
        $message .= "⏰ *Hours:* 7AM - 10PM daily\n\n";
        $message .= "💬 *Live Chat:* Available on our website\n\n";
        $message .= "🛒 Ready to continue shopping? Type *'start shopping'*!";

        $this->whatsAppService->sendMessage($session->whatsapp_number, $message);
    }

    private function handleAddingItems(WhatsappSession $session, string $text): void
    {
        if ($text === 'done') {
            $this->handleOrderCompletion($session);
        } elseif (in_array($text, ['view cart', 'cart', 'show cart', 'my cart'])) {
            $this->showCart($session);
        } elseif (in_array($text, ['clear cart', 'empty cart', 'remove all'])) {
            $this->clearCart($session);
        } elseif (in_array($text, ['help', 'assistance'])) {
            $this->showShoppingHelp($session);
        } else {
            $this->parseAndAddItem($session, $text);
        }
    }

    private function parseAndAddItem(WhatsappSession $session, string $text): void
    {
        // Enhanced parsing logic
        $parsed = $this->parseItemText($text);

        if (!$parsed) {
            $message = "🤔 I didn't understand that. Please try:\n\n";
            $message .= "• *'2kg rice'*\n";
            $message .= "• *'5 pieces tomatoes'*\n";
            $message .= "• *'1 bag of beans'*\n\n";
            $message .= "Or type *'help'* for assistance.";

            $this->whatsAppService->sendMessage($session->whatsapp_number, $message);
            return;
        }

        // Search for product
        $product = $this->findProduct($parsed['product_name']);

        if (!$product) {
            $message = "❌ Sorry, I couldn't find *'{$parsed['product_name']}'*.\n\n";
            $message .= "💡 *Suggestions:*\n";
            $message .= "• Check spelling\n";
            $message .= "• Try a different name\n";
            $message .= "• Type *'menu'* to see categories\n\n";
            $message .= "Need help? Type *'help'*!";

            $this->whatsAppService->sendMessage($session->whatsapp_number, $message);
            return;
        }

        // Add to cart
        $cartItem = [
            'product_id' => $product->id,
            'name' => $product->name,
            'quantity' => $parsed['quantity'],
            'unit' => $parsed['unit'] ?: $product->unit,
            'price' => 0, // Will be set when market is selected
        ];

        $session->addToCart($cartItem);

        $message = "✅ *Added to cart:*\n";
        $message .= "• {$parsed['quantity']} {$parsed['unit']} {$product->name}\n\n";

        $cartCount = count($session->cart_items ?? []);
        $message .= "🛒 *Cart Summary:* {$cartCount} item(s)\n\n";

        $message .= "Continue adding items or type *'done'* when finished!\n\n";
        $message .= "💡 Type *'view cart'* to see all items.";

        $this->whatsAppService->sendMessage($session->whatsapp_number, $message);
    }

    private function parseItemText(string $text): ?array
    {
        // Remove common words that don't help with parsing
        $text = preg_replace('/\b(please|i want|i need|add|give me|get me)\b/i', '', $text);
        $text = trim($text);

        // Patterns for different quantity formats
        $patterns = [
            // "2kg rice" or "2 kg rice"
            '/^(\d+(?:\.\d+)?)\s*(kg|kilos?|kilograms?)\s+(.+)$/i',
            // "5 pieces tomatoes" or "5 pcs tomatoes"
            '/^(\d+)\s*(pieces?|pcs?|pcs)\s+(.+)$/i',
            // "1 bag beans" or "1 bag of beans"
            '/^(\d+)\s*(bags?|bag)\s+(?:of\s+)?(.+)$/i',
            // "3 bottles oil" or "3 bottles of oil"
            '/^(\d+)\s*(bottles?|bottle)\s+(?:of\s+)?(.+)$/i',
            // "10 eggs" or "10 pieces eggs"
            '/^(\d+)\s*(eggs?|pieces?|pcs?)\s+(.+)$/i',
            // "1 rice" (default unit)
            '/^(\d+(?:\.\d+)?)\s+(.+)$/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $quantity = (float) $matches[1];
                $unit = isset($matches[2]) ? strtolower($matches[2]) : 'kg';
                $productName = trim($matches[count($matches) - 1]);

                // Normalize units
                $unit = $this->normalizeUnit($unit);

                return [
                    'quantity' => $quantity,
                    'unit' => $unit,
                    'product_name' => $productName,
                ];
            }
        }

        return null;
    }

    private function normalizeUnit(string $unit): string
    {
        $unitMap = [
            'kg' => 'kg', 'kilos' => 'kg', 'kilogram' => 'kg', 'kilograms' => 'kg',
            'pieces' => 'pieces', 'piece' => 'pieces', 'pcs' => 'pieces', 'pc' => 'pieces',
            'bags' => 'bags', 'bag' => 'bags',
            'bottles' => 'bottles', 'bottle' => 'bottles',
            'eggs' => 'pieces', 'egg' => 'pieces',
        ];

        return $unitMap[strtolower($unit)] ?? 'kg';
    }

    private function findProduct(string $productName): ?Product
    {
        // First try exact match
        $product = Product::where('name', 'like', $productName)
            ->where('is_active', true)
            ->first();

        if (!$product) {
            // Try partial match
            $product = Product::where('name', 'like', "%{$productName}%")
                ->where('is_active', true)
                ->first();
        }

        return $product;
    }

    private function showCart(WhatsappSession $session): void
    {
        $cartItems = $session->cart_items ?? [];

        if (empty($cartItems)) {
            $message = "🛒 *Your cart is empty*\n\n";
            $message .= "Start adding items to your cart!\n\n";
            $message .= "Try saying:\n";
            $message .= "• *'2kg rice'*\n";
            $message .= "• *'5 pieces tomatoes'*\n";
            $message .= "• *'1 bag of beans'*";

            $this->whatsAppService->sendMessage($session->whatsapp_number, $message);
            return;
        }

        $message = "🛒 *Your Cart:*\n\n";
        $itemCount = 0;

        foreach ($cartItems as $item) {
            $itemCount++;
            $message .= "{$itemCount}. {$item['quantity']} {$item['unit']} {$item['name']}\n";
        }

        $message .= "\n📊 *Total Items:* " . count($cartItems) . "\n\n";
        $message .= "💡 *What would you like to do?*\n\n";
        $message .= "• Continue adding items\n";
        $message .= "• Type *'done'* to checkout\n";
        $message .= "• Type *'clear cart'* to start over";

        $this->whatsAppService->sendMessage($session->whatsapp_number, $message);
    }

    private function clearCart(WhatsappSession $session): void
    {
        $session->clearCart();

        $message = "🗑️ *Cart cleared!*\n\n";
        $message .= "Your cart is now empty. Ready to start fresh?\n\n";
        $message .= "Try adding some items:\n";
        $message .= "• *'2kg rice'*\n";
        $message .= "• *'5 pieces tomatoes'*\n";
        $message .= "• *'1 bag of beans'*";

        $this->whatsAppService->sendMessage($session->whatsapp_number, $message);
    }

    private function showShoppingHelp(WhatsappSession $session): void
    {
        $message = "🛒 *Shopping Help*\n\n";
        $message .= "💡 *How to add items:*\n";
        $message .= "• *'2kg rice'* - 2 kilograms of rice\n";
        $message .= "• *'5 pieces tomatoes'* - 5 pieces of tomatoes\n";
        $message .= "• *'1 bag of beans'* - 1 bag of beans\n";
        $message .= "• *'3 bottles of oil'* - 3 bottles of oil\n\n";
        $message .= "🔧 *Cart Commands:*\n";
        $message .= "• *'view cart'* - See your items\n";
        $message .= "• *'clear cart'* - Remove all items\n";
        $message .= "• *'done'* - Proceed to checkout\n";
        $message .= "• *'cancel'* - Start over\n\n";
        $message .= "❓ *Need more help?*\n";
        $message .= "Type *'contact'* to speak with us!";

        $this->whatsAppService->sendMessage($session->whatsapp_number, $message);
    }

    private function handleOrderCompletion(WhatsappSession $session): void
    {
        $cartItems = $session->cart_items ?? [];

        if (empty($cartItems)) {
            $message = "🛒 *Your cart is empty*\n\n";
            $message .= "Please add some items before checking out.\n\n";
            $message .= "Try saying:\n";
            $message .= "• *'2kg rice'*\n";
            $message .= "• *'5 pieces tomatoes'*";

            $this->whatsAppService->sendMessage($session->whatsapp_number, $message);
            return;
        }

        // Create order summary
        $message = "🎉 *Order Summary*\n\n";
        $itemCount = 0;

        foreach ($cartItems as $item) {
            $itemCount++;
            $message .= "{$itemCount}. {$item['quantity']} {$item['unit']} {$item['name']}\n";
        }

        $message .= "\n📊 *Total Items:* " . count($cartItems) . "\n\n";
        $message .= "🔄 *Creating your order...*\n\n";
        $message .= "Please wait while I prepare your checkout link...";

        $this->whatsAppService->sendMessage($session->whatsapp_number, $message);

        // Create order and send link
        $this->createOrderFromSession($session);
    }

    private function createOrderFromSession(WhatsappSession $session): void
    {
        try {
            $order = $this->orderService->createFromWhatsAppSession($session);

            $frontendUrl = config('app.frontend_url', 'https://foodstuff.store') . "/order/{$order->id}";

            $message = "🎉 *Order Created Successfully!*\n\n";
            $message .= "📋 *Order #:* {$order->order_number}\n";
            $message .= "📦 *Items:* " . count($session->cart_items ?? []) . "\n\n";
            $message .= "🔗 *Complete Your Order:*\n";
            $message .= "{$frontendUrl}\n\n";
            $message .= "✨ *What's Next:*\n";
            $message .= "• Select your preferred market\n";
            $message .= "• Review prices and confirm\n";
            $message .= "• Complete payment securely\n";
            $message .= "• Track your delivery\n\n";
            $message .= "💡 *Need help?* Reply *'help'* anytime!\n\n";
            $message .= "Thank you for choosing FoodStuff Store! 🛒";

            $this->whatsAppService->sendMessage($session->whatsapp_number, $message);

            // Mark session as completed
            $session->update(['status' => 'completed']);

        } catch (\Exception $e) {
            $message = "❌ *Sorry, there was an error creating your order.*\n\n";
            $message .= "Please try again or contact support:\n";
            $message .= "📞 +234 801 234 5678\n";
            $message .= "📧 support@foodstuff.store";

            $this->whatsAppService->sendMessage($session->whatsapp_number, $message);
        }
    }

    private function handleSessionResumption(string $phone): void
    {
        $session = WhatsappSession::where('whatsapp_number', $phone)
            ->where('status', 'active')
            ->first();

        if (!$session) {
            // Check if there's a recently completed session
            $completedSession = WhatsappSession::where('whatsapp_number', $phone)
                ->where('status', 'completed')
                ->where('last_activity', '>', now()->subDays(7))
                ->first();

            if ($completedSession) {
                $message = "🎉 *Welcome back!*\n\n";
                $message .= "Your previous order has been completed successfully!\n\n";
                $message .= "🛒 *Ready to order again?*\n\n";
                $message .= "Just say *'hi'* or *'start shopping'* to begin a new order!";
            } else {
                $message = "🤔 I don't see an active session to resume.\n\n";
                $message .= "Let's start fresh! Type *'hi'* to begin shopping.";
            }

            $this->whatsAppService->sendMessage($phone, $message);
            return;
        }

        $cartItems = $session->cart_items ?? [];

        if (empty($cartItems)) {
            $message = "🛒 *Welcome back!*\n\n";
            $message .= "Your cart is empty. Ready to start shopping?\n\n";
            $message .= "Try saying:\n";
            $message .= "• *'2kg rice'*\n";
            $message .= "• *'5 pieces tomatoes'*";
        } else {
            $message = "🛒 *Welcome back!*\n\n";
            $message .= "You have " . count($cartItems) . " item(s) in your cart.\n\n";
            $message .= "💡 *What would you like to do?*\n\n";
            $message .= "• Continue adding items\n";
            $message .= "• Type *'view cart'* to see items\n";
            $message .= "• Type *'done'* to checkout\n";
            $message .= "• Type *'clear cart'* to start over";
        }

        $this->whatsAppService->sendMessage($phone, $message);
    }

    private function handleSessionCancellation(string $phone): void
    {
        // Cancel any active session
        WhatsappSession::where('whatsapp_number', $phone)
            ->where('status', 'active')
            ->update(['status' => 'cancelled']);

        $message = "🔄 *Session cancelled!*\n\n";
        $message .= "Your cart has been cleared. Ready to start fresh?\n\n";
        $message .= "Type *'hi'* to begin a new shopping session!";

        $this->whatsAppService->sendMessage($phone, $message);
    }

    private function sendExpiredSessionMessage(string $phone, WhatsappSession $session): void
    {
        $message = "⏰ *Session Expired*\n\n";
        $message .= "Your previous shopping session has expired.\n\n";
        $message .= "💡 *Don't worry!* You can:\n";
        $message .= "• Type *'continue'* to resume\n";
        $message .= "• Type *'hi'* to start fresh\n";
        $message .= "• Type *'help'* for assistance";

        $this->whatsAppService->sendMessage($phone, $message);
    }

    private function handleConfirmingOrder(WhatsappSession $session, string $text): void
    {
        // This step is no longer needed since we go directly to frontend
        $this->handleGreeting($session, $text);
    }
}
