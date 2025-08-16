<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\MarketProduct;
use App\Models\Agent;
use App\Models\AgentEarning;
use App\Models\WhatsappSession;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderService
{
    public function __construct(
        private WhatsAppService $whatsAppService
    ) {}

    public function createOrder(array $data): Order
    {
        return DB::transaction(function () use ($data) {
            // Generate order number
            $orderNumber = 'FS' . date('Ymd') . Str::random(6);

            // Calculate totals
            $subtotal = 0;
            $deliveryFee = $this->calculateDeliveryFee($data['delivery_latitude'], $data['delivery_longitude']);

            // Create order
            $order = Order::create([
                'order_number' => $orderNumber,
                'whatsapp_number' => $data['whatsapp_number'],
                'customer_name' => $data['customer_name'],
                'delivery_address' => $data['delivery_address'],
                'delivery_latitude' => $data['delivery_latitude'],
                'delivery_longitude' => $data['delivery_longitude'],
                'market_id' => $data['market_id'],
                'subtotal' => $subtotal,
                'delivery_fee' => $deliveryFee,
                'total_amount' => $subtotal + $deliveryFee,
                'status' => 'pending',
            ]);

            // Add order items
            foreach ($data['items'] as $item) {
                $marketProduct = MarketProduct::where('market_id', $data['market_id'])
                    ->where('product_id', $item['product_id'])
                    ->where('is_available', true)
                    ->first();

                if (!$marketProduct) {
                    throw new \Exception("Product not available in selected market");
                }

                $unitPrice = $marketProduct->price;
                $totalPrice = $item['quantity'] * $unitPrice;
                $subtotal += $totalPrice;

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item['product_id'],
                    'market_product_id' => $marketProduct->id,
                    'product_name' => $marketProduct->product->name,
                    'unit_price' => $unitPrice,
                    'quantity' => $item['quantity'],
                    'total_price' => $totalPrice,
                ]);
            }

            // Update order totals
            $order->update([
                'subtotal' => $subtotal,
                'total_amount' => $subtotal + $deliveryFee,
            ]);

            // Log initial status
            $order->updateStatus('pending', 'Order created successfully');

            return $order;
        });
    }

    public function createFromWhatsAppSession(WhatsappSession $session): Order
    {
        $cartItems = $session->cart_items ?? [];

        if (empty($cartItems)) {
            throw new \Exception('No items in cart');
        }

        // For WhatsApp sessions, we'll create a basic order structure
        // The actual market selection and pricing will happen on the frontend
        $orderNumber = 'FS' . date('Ymd') . Str::random(6);

        $order = Order::create([
            'order_number' => $orderNumber,
            'whatsapp_number' => $session->whatsapp_number,
            'customer_name' => 'Customer', // Will be updated on frontend
            'delivery_address' => $session->delivery_address ?? '',
            'delivery_latitude' => 0, // Will be updated on frontend
            'delivery_longitude' => 0, // Will be updated on frontend
            'market_id' => 1, // Default market, will be updated
            'subtotal' => 0,
            'delivery_fee' => 0,
            'total_amount' => 0,
            'status' => 'pending',
        ]);

        // Add basic order items (without pricing)
        foreach ($cartItems as $item) {
            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $item['product_id'],
                'product_name' => $item['name'],
                'unit_price' => 0, // Will be updated on frontend
                'quantity' => $item['quantity'],
                'total_price' => 0, // Will be updated on frontend
            ]);
        }

        $order->updateStatus('pending', 'Order created from WhatsApp session');

        return $order;
    }

    public function createOrderFromWhatsApp(string $whatsappNumber, array $items): Order
    {
        return DB::transaction(function () use ($whatsappNumber, $items) {
            // Generate order number
            $orderNumber = 'FS' . date('Ymd') . Str::random(6);

            // Create basic order (market and pricing will be set on frontend)
            $order = Order::create([
                'order_number' => $orderNumber,
                'whatsapp_number' => $whatsappNumber,
                'customer_name' => 'Customer', // Will be updated on frontend
                'delivery_address' => '', // Will be set on frontend
                'delivery_latitude' => null,
                'delivery_longitude' => null,
                'market_id' => null, // Will be selected on frontend
                'subtotal' => 0,
                'delivery_fee' => 0,
                'total_amount' => 0,
                'status' => 'pending',
            ]);

            // Add order items from WhatsApp
            foreach ($items as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => null, // Will be matched on frontend
                    'market_product_id' => null,
                    'product_name' => $item['name'],
                    'unit_price' => 0, // Will be set on frontend
                    'quantity' => $item['quantity'],
                    'total_price' => 0, // Will be calculated on frontend
                    'notes' => $item['notes'] ?? null,
                ]);
            }

            // Log initial status
            $order->updateStatus('pending', 'Order created from WhatsApp');

            return $order;
        });
    }

    public function assignAgent(Order $order): ?Agent
    {
        if (!$order->canBeAssigned()) {
            return null;
        }

        // Find available agent in the market
        $availableAgent = $order->market->getAvailableAgents()->first();

        if (!$availableAgent) {
            Log::warning('No available agents for order', [
                'order_id' => $order->id,
                'market_id' => $order->market_id,
            ]);
            return null;
        }

        $order->update([
            'agent_id' => $availableAgent->id,
        ]);

        $order->updateStatus('assigned', "Order assigned to agent {$availableAgent->full_name}");

        // Create agent earning record
        AgentEarning::create([
            'agent_id' => $availableAgent->id,
            'order_id' => $order->id,
            'amount' => $order->total_amount * 0.1, // 10% commission
            'status' => 'pending',
        ]);

        // Send WhatsApp notification to customer
        $this->whatsAppService->sendAgentAssignment(
            $order->whatsapp_number,
            $order->order_number,
            $availableAgent->full_name,
            $availableAgent->phone
        );

        return $availableAgent;
    }

    public function updateOrderStatus(Order $order, string $status, string $message = ''): void
    {
        $order->updateStatus($status, $message);

        // Send appropriate WhatsApp notification based on status
        $this->sendStatusNotification($order, $status, $message);

        // If order is delivered or completed, mark agent earning as paid and complete session
        if (in_array($status, ['delivered', 'completed'])) {
            $earning = $order->earnings()->first();
            if ($earning && $earning->status === 'pending') {
                $earning->markAsPaid('auto_payment_' . $order->id);
            }

            // Complete WhatsApp session
            $this->completeWhatsAppSession($order->whatsapp_number);
        }
    }

    /**
     * Send WhatsApp status notification
     */
    private function sendStatusNotification(Order $order, string $status, string $message = ''): void
    {
        $whatsappBotUrl = env('WHATSAPP_BOT_URL', 'https://foodstuff-whatsapp-bot-6536aa3f6997.herokuapp.com');

        $statusMessages = [
            'pending' => 'Your order is being processed.',
            'confirmed' => 'Your order has been confirmed!',
            'paid' => 'Payment received! Your order is being prepared.',
            'assigned' => 'An agent has been assigned to your order.',
            'preparing' => 'Your order is being prepared in the kitchen.',
            'ready_for_delivery' => 'Your order is ready for delivery!',
            'out_for_delivery' => 'Your order is on its way to you!',
            'delivered' => 'Your order has been delivered! Enjoy your meal!',
            'cancelled' => 'Your order has been cancelled.',
            'failed' => 'There was an issue with your order.',
            'completed' => 'Your order has been completed successfully!',
        ];

        $notificationMessage = $message ?: ($statusMessages[$status] ?? 'Your order status has been updated.');

        $data = [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'status' => $status,
            'message' => $notificationMessage,
            'whatsapp_number' => $order->whatsapp_number,
        ];

        // Send to WhatsApp bot
        try {
            $response = \Http::post($whatsappBotUrl . '/order-status-update', $data);

            if ($response->successful()) {
                Log::info('WhatsApp status notification sent successfully', [
                    'order_id' => $order->id,
                    'status' => $status,
                ]);
            } else {
                Log::error('Failed to send WhatsApp status notification', [
                    'order_id' => $order->id,
                    'response' => $response->body(),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Error sending WhatsApp status notification: ' . $e->getMessage());
        }
    }

    private function completeWhatsAppSession(string $whatsappNumber): void
    {
        // Find and mark the active session as completed
        WhatsappSession::where('whatsapp_number', $whatsappNumber)
            ->where('status', 'active')
            ->update([
                'status' => 'completed',
                'last_activity' => now(),
            ]);
    }

    public function reassignAgent(Order $order, Agent $newAgent): void
    {
        $oldAgentName = $order->agent ? $order->agent->full_name : 'Previous agent';

        // Update order with new agent
        $order->update([
            'agent_id' => $newAgent->id,
        ]);

        // Cancel old agent's earning if exists
        $oldEarning = $order->earnings()->where('agent_id', '!=', $newAgent->id)->first();
        if ($oldEarning) {
            $oldEarning->update(['status' => 'cancelled']);
        }

        // Create new agent earning record
        AgentEarning::create([
            'agent_id' => $newAgent->id,
            'order_id' => $order->id,
            'amount' => $order->total_amount * 0.1, // 10% commission
            'status' => 'pending',
        ]);

        // Log the reassignment
        $order->updateStatus('assigned', "Order reassigned from {$oldAgentName} to {$newAgent->full_name}");

        // Send WhatsApp notification
        $this->whatsAppService->sendAgentReassigned(
            $order->whatsapp_number,
            $order->order_number,
            $newAgent->full_name,
            $newAgent->phone
        );
    }

    public function cancelOrder(Order $order, string $reason = ''): void
    {
        $order->updateStatus('cancelled', $reason);

        // Cancel any pending earnings
        $order->earnings()->where('status', 'pending')->update(['status' => 'cancelled']);

        // Send cancellation notification
        $this->whatsAppService->sendOrderCancelled(
            $order->whatsapp_number,
            $order->order_number,
            $reason
        );
    }

    public function completeOrder(Order $order, string $message = ''): void
    {
        // Update order status to completed
        $order->updateStatus('completed', $message);

        // Mark agent earning as paid
        $earning = $order->earnings()->first();
        if ($earning && $earning->status === 'pending') {
            $earning->markAsPaid('auto_payment_' . $order->id);
        }

        // Mark WhatsApp session as completed
        $this->completeWhatsAppSession($order->whatsapp_number);

        // Send completion notification
        $agentName = $order->agent ? $order->agent->full_name : 'Our team';
        $this->whatsAppService->sendOrderDelivered(
            $order->whatsapp_number,
            $order->order_number,
            $agentName
        );
    }

    private function calculateDeliveryFee(float $lat, float $lng): float
    {
        // Simple delivery fee calculation based on distance
        // In a real implementation, this would be more sophisticated
        return 500.0; // Default delivery fee
    }
}
