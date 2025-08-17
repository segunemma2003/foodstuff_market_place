# WhatsApp Section-Based API Documentation

## Overview

This document describes the new WhatsApp section-based ordering system for FoodStuff Store. The system creates shopping sessions via WhatsApp and allows users to complete their orders on the web platform.

## Base URL

```
https://foodstuff-store-api-39172343a322.herokuapp.com/api/v1
```

## Authentication

Most endpoints are public and don't require authentication. Admin and agent endpoints require appropriate middleware.

## WhatsApp Bot Endpoints

### 1. Process WhatsApp Message

**POST** `/whatsapp/process-message`

Process incoming WhatsApp messages and return intelligent responses.

**Request Body:**

```json
{
    "from": "+2348012345678",
    "body": "hi"
}
```

**Response:**

```json
{
    "success": true,
    "reply": "🛒 Welcome to FoodStuff Store!..."
}
```

### 2. Confirm Section

**POST** `/whatsapp/confirm-section`

Confirm that a section exists and get its status. Used by the frontend to verify sections.

**Request Body:**

```json
{
    "section_id": "SEC_1734567890_abc123"
}
```

**Response:**

```json
{
    "success": true,
    "section": {
        "section_id": "SEC_1734567890_abc123",
        "status": "ongoing",
        "whatsapp_number": "+2348012345678",
        "delivery_address": "123 Main St, Lagos",
        "delivery_latitude": 6.5244,
        "delivery_longitude": 3.3792,
        "selected_market_id": 1,
        "order_id": 123,
        "created_at": "2024-08-17T10:30:00Z",
        "last_activity": "2024-08-17T10:35:00Z"
    }
}
```

### 3. Create Section

**POST** `/whatsapp/create-section`

Create a new shopping section for a WhatsApp user.

**Request Body:**

```json
{
    "whatsapp_number": "+2348012345678"
}
```

**Response:**

```json
{
    "success": true,
    "section_id": "SEC_1734567890_abc123",
    "message": "Section created successfully"
}
```

### 4. Get Section Status

**GET** `/whatsapp/section/{section_id}/status`

Get the current status of a shopping section.

**Response:**

```json
{
    "success": true,
    "section": {
        "section_id": "SEC_1734567890_abc123",
        "status": "ongoing",
        "whatsapp_number": "+2348012345678",
        "delivery_address": "123 Main St, Lagos",
        "delivery_latitude": 6.5244,
        "delivery_longitude": 3.3792,
        "selected_market_id": 1,
        "order_id": 123,
        "created_at": "2024-08-17T10:30:00Z",
        "last_activity": "2024-08-17T10:35:00Z"
    }
}
```

### 5. Get Nearby Markets

**POST** `/whatsapp/section/nearby-markets`

Get markets within 30km of the user's location with distance calculation and delivery information. Now supports search functionality.

**Request Body:**

```json
{
    "latitude": 6.5244,
    "longitude": 3.3792,
    "section_id": "SEC_1734567890_abc123",
    "search": "central"
}
```

**Response:**

```json
{
    "success": true,
    "markets": [
        {
            "id": 1,
            "name": "Lagos Central Market",
            "address": "123 Market St, Lagos",
            "distance": 2.5,
            "delivery_amount": 625,
            "delivery_time": "35m",
            "latitude": 6.5244,
            "longitude": 3.3792
        }
    ],
    "total": 1,
    "search_term": "central"
}
```

### 6. Search Markets

**POST** `/whatsapp/section/search-markets`

Search markets by name or address without location constraints. Useful for finding specific markets.

**Request Body:**

```json
{
    "search": "central market",
    "section_id": "SEC_1734567890_abc123"
}
```

**Response:**

```json
{
    "success": true,
    "markets": [
        {
            "id": 1,
            "name": "Lagos Central Market",
            "address": "123 Market St, Lagos",
            "distance": 2.5,
            "delivery_amount": 625,
            "delivery_time": "35m",
            "latitude": 6.5244,
            "longitude": 3.3792,
            "is_within_range": true
        },
        {
            "id": 2,
            "name": "Central Food Market",
            "address": "456 Food Ave, Lagos",
            "distance": null,
            "delivery_amount": null,
            "delivery_time": null,
            "latitude": 6.5244,
            "longitude": 3.3792,
            "is_within_range": false
        }
    ],
    "total": 2,
    "search_term": "central market"
}
```

### 7. Get Market Products

**GET** `/whatsapp/section/market-products`

Get products from a selected market with search and pagination.

**Query Parameters:**

-   `market_id` (required): Market ID
-   `section_id` (required): Section ID
-   `search` (optional): Search term for product names
-   `page` (optional): Page number (default: 1)
-   `per_page` (optional): Items per page (default: 20, max: 50)

**Response:**

```json
{
    "success": true,
    "products": [
        {
            "id": 1,
            "product_id": 1,
            "name": "Rice",
            "description": "Premium quality rice",
            "image": "https://example.com/rice.jpg",
            "category": "Grains",
            "prices": [
                {
                    "id": 1,
                    "measurement_scale": "1kg",
                    "price": 500,
                    "unit": "kg"
                }
            ],
            "is_available": true,
            "stock_quantity": 100
        }
    ],
    "pagination": {
        "current_page": 1,
        "last_page": 5,
        "per_page": 20,
        "total": 100
    }
}
```

### 8. Create Order

**POST** `/whatsapp/section/create-order`

Create an order from the shopping session and generate Paystack payment URL.

**Request Body:**

```json
{
    "section_id": "SEC_1734567890_abc123",
    "items": [
        {
            "product_id": 1,
            "quantity": 2,
            "measurement_scale": "1kg",
            "unit_price": 500,
            "product_name": "Rice"
        }
    ],
    "customer_name": "John Doe",
    "customer_phone": "+2348012345678",
    "subtotal": 1000,
    "delivery_fee": 625,
    "service_charge": 50,
    "total_amount": 1675
}
```

**Response:**

```json
{
    "success": true,
    "order_id": 123,
    "order_number": "FS20240817001",
    "payment_url": "https://checkout.paystack.com/abc123",
    "message": "Order created successfully"
}
```

### 9. Get Order Status

**GET** `/whatsapp/section/{section_id}/order-status`

Get the current status of an order for a section.

**Response:**

```json
{
    "success": true,
    "order": {
        "id": 123,
        "order_number": "FS20240817001",
        "status": "paid",
        "customer_name": "John Doe",
        "delivery_address": "123 Main St, Lagos",
        "market_name": "Lagos Central Market",
        "subtotal": 1000,
        "delivery_fee": 625,
        "total_amount": 1675,
        "created_at": "2024-08-17T10:30:00Z",
        "updated_at": "2024-08-17T10:35:00Z"
    }
}
```

### 10. Get User Orders

**GET** `/whatsapp/user-orders`

Get all active orders for a WhatsApp user.

**Query Parameters:**

-   `whatsapp_number` (required): WhatsApp number

**Response:**

```json
{
    "success": true,
    "orders": [
        {
            "section_id": "SEC_1734567890_abc123",
            "order_number": "FS20240817001",
            "status": "paid",
            "market_name": "Lagos Central Market",
            "total_amount": 1675,
            "created_at": "2024-08-17T10:30:00Z"
        }
    ],
    "total": 1
}
```

## Payment Callback Endpoints

### 11. Payment Callback

**POST** `/payment-callback`

Handle payment callbacks from Paystack with section information.

**Request Body:**

```json
{
    "section_id": "SEC_1734567890_abc123",
    "payment_status": "success",
    "transaction_reference": "TXN_123456789",
    "amount": 1675,
    "message": "Payment successful"
}
```

**Response:**

```json
{
    "success": true,
    "message": "Payment processed successfully",
    "order_status": "paid",
    "section_status": "paid"
}
```

### 12. Update Order Status

**POST** `/order-status-update`

Update order status and send WhatsApp notifications.

**Request Body:**

```json
{
    "section_id": "SEC_1734567890_abc123",
    "status": "out_for_delivery",
    "message": "Your order is on its way!",
    "agent_id": 1
}
```

**Response:**

```json
{
    "success": true,
    "message": "Order status updated successfully",
    "order_status": "out_for_delivery",
    "session_status": "out_for_delivery"
}
```

## WhatsApp Bot Flow

### New User Flow:

1. **User sends greeting** (`hi`, `hello`, `hey`)
2. **Bot checks for previous orders** - If none found, proceeds as new user
3. **Bot introduces itself** and asks if user wants to use the platform
4. **User responds affirmatively** (`yes`, `go ahead`, `ok`, etc.)
5. **Bot creates section** and sends shopping URL
6. **User clicks URL** and goes to marketplace
7. **Frontend confirms section** via API
8. **User enters delivery address** (OpenStreetMap integration)
9. **Frontend gets nearby markets** via API
10. **User selects market** and browses products
11. **User adds products to cart** with measurements and quantities
12. **Frontend calculates totals** (including delivery and service charges)
13. **User checks out** and creates order
14. **Backend generates Paystack URL** and returns it
15. **User completes payment** on Paystack
16. **Payment callback updates** order and section status
17. **WhatsApp notifications** sent for status updates

### Returning User Flow:

1. **User sends greeting** (`hi`, `hello`, `hey`)
2. **Bot checks for previous orders** - If found, shows choice menu
3. **Bot asks user to choose**:
    - Option 1: Make a new order
    - Option 2: Track previous orders
4. **User selects option**:
    - If "1" or "new order": Bot creates section and sends shopping URL
    - If "2" or "track orders": Bot shows order tracking options
5. **If invalid choice**: Bot corrects user and asks for valid option
6. **Continues with appropriate flow** based on user choice

### Order Tracking Flow:

1. **User requests tracking** (`track order`, `track`, `status`) or selects option 2
2. **Bot checks for active orders**
3. **If single order**: Bot sends tracking URL
4. **If multiple orders**: Bot lists orders and asks for specific order number
5. **User can say "track all"** to get individual tracking links
6. **Tracking URLs** redirect to `/track_order?section_id={section_id}`
7. **Frontend loads order details** and sends to WhatsApp

## Delivery Fee Calculation

-   **Base delivery fee**: ₦500
-   **Per km fee**: ₦50/km
-   **Heavy item fee**: 15% of base delivery fee per kg over 4kg
-   **Service charge**: 5% of subtotal

## Section Statuses

-   `active`: Initial session created
-   `ongoing`: User is shopping
-   `order_created`: Order has been created
-   `paid`: Payment completed
-   `assigned`: Agent assigned
-   `preparing`: Order being prepared
-   `ready_for_delivery`: Order ready for pickup
-   `out_for_delivery`: Order en route
-   `delivered`: Order delivered
-   `completed`: Order completed
-   `cancelled`: Order cancelled
-   `payment_failed`: Payment failed

## Error Responses

All endpoints return consistent error responses:

```json
{
    "success": false,
    "message": "Error description"
}
```

Common HTTP status codes:

-   `200`: Success
-   `400`: Bad request (validation errors)
-   `404`: Section/order not found
-   `500`: Server error
