# FoodStuff Store - Comprehensive API Documentation

## Base URL

```
https://foodstuff-store-api.herokuapp.com/api/v1
```

## Authentication

### Admin Login

```http
POST /admin/login
Content-Type: application/json

{
    "email": "admin@foodstuff.store",
    "password": "admin123"
}
```

### Agent Login

```http
POST /agent/login
Content-Type: application/json

{
    "email": "agent@foodstuff.store",
    "password": "agentname"
}
```

---

## 🔍 **Order Search & Management APIs**

### 1. Search Orders by Order Number

```http
GET /orders/search?order_number=FS_12345
```

**Response:**

```json
{
    "success": true,
    "data": {
        "id": 1,
        "order_number": "FS_12345",
        "status": "pending",
        "customer_name": "John Customer",
        "whatsapp_number": "+2348012345678",
        "delivery_address": "123 Customer Street, Lagos",
        "total_amount": 2500.0,
        "market": {
            "id": 1,
            "name": "Central Market Lagos",
            "address": "123 Market Street, Lagos"
        },
        "agent": {
            "id": 1,
            "name": "John Doe",
            "phone": "+2348012345678"
        },
        "created_at": "2024-01-15T10:30:00Z"
    }
}
```

### 2. Get Order Items Only

```http
GET /orders/{order_id}/items
```

**Response:**

```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "product_name": "Rice",
            "quantity": 2,
            "unit_price": 1200.0,
            "total_price": 2400.0,
            "product": {
                "id": 1,
                "name": "Rice",
                "unit": "kg",
                "description": "Premium long grain rice"
            }
        }
    ]
}
```

### 3. Update Order Status

```http
PUT /orders/{order_id}/status
Content-Type: application/json

{
    "status": "delivered",
    "message": "Order delivered successfully"
}
```

---

## 🔍 **Autocomplete & Search APIs**

### 4. Product Autocomplete

```http
GET /products/autocomplete?query=rice&category_id=1
```

**Response:**

```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "name": "Rice",
            "description": "Premium long grain rice",
            "unit": "kg",
            "category": {
                "id": 1,
                "name": "Grains & Cereals"
            }
        }
    ],
    "count": 1
}
```

### 5. Market Autocomplete

```http
GET /markets/autocomplete?query=lagos
```

**Response:**

```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "name": "Central Market Lagos",
            "address": "123 Market Street, Lagos",
            "latitude": 6.5244,
            "longitude": 3.3792
        }
    ],
    "count": 1
}
```

### 6. Product Search

```http
GET /products/search?query=rice&category_id=1
```

---

## 🗺️ **Market & Proximity APIs**

### 7. Find Nearby Markets

```http
GET /markets/nearby?latitude=6.5244&longitude=3.3792&radius=5
```

**Response:**

```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "name": "Central Market Lagos",
            "address": "123 Market Street, Lagos",
            "latitude": 6.5244,
            "longitude": 3.3792,
            "distance": 0.5,
            "phone": "+2348012345678",
            "email": "central@foodstuff.store"
        }
    ],
    "count": 1
}
```

### 8. Get Market Products

```http
GET /markets/{market_id}/products?category_id=1&search=rice
```

### 9. Get Product Prices & Measurements

```http
GET /markets/{market_id}/prices
Content-Type: application/json

{
    "product_ids": [1, 2, 3]
}
```

**Response:**

```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "product_id": 1,
            "name": "Rice",
            "description": "Premium long grain rice",
            "unit": "kg",
            "measurement": "kg",
            "price": 1200.0,
            "stock_quantity": 100,
            "is_available": true,
            "category": {
                "id": 1,
                "name": "Grains & Cereals"
            },
            "agent": {
                "id": 1,
                "name": "John Doe"
            }
        }
    ],
    "market": {
        "id": 1,
        "name": "Central Market Lagos",
        "address": "123 Market Street, Lagos"
    }
}
```

---

## 💳 **Payment APIs**

### 10. Initialize Payment (Returns Paystack URL)

```http
POST /orders/{order_id}/checkout
Content-Type: application/json

{
    "email": "customer@example.com"
}
```

**Response:**

```json
{
    "success": true,
    "data": {
        "order_id": 1,
        "order_number": "FS_12345",
        "total_amount": 2500.0,
        "payment_url": "https://checkout.paystack.com/abc123",
        "reference": "FS_12345"
    }
}
```

---

## 👨‍💼 **Admin APIs (Protected)**

### 11. Admin Order Search

```http
GET /admin/orders/search?query=FS_12345
Authorization: Bearer admin_token
```

### 12. Approve Agent for Order

```http
PUT /admin/orders/{order_id}/approve-agent
Authorization: Bearer admin_token
```

**Response:**

```json
{
    "success": true,
    "message": "Agent approved and commission created",
    "data": {
        "commission_id": 1,
        "amount": 250.0
    }
}
```

### 13. Switch Agent for Order

```http
PUT /admin/orders/{order_id}/switch-agent
Authorization: Bearer admin_token
Content-Type: application/json

{
    "agent_id": 2
}
```

### 14. Switch Agent to Different Market

```http
PUT /admin/agents/{agent_id}/switch-market
Authorization: Bearer admin_token
Content-Type: application/json

{
    "market_id": 2
}
```

### 15. Get All Commissions

```http
GET /admin/commissions
Authorization: Bearer admin_token
```

**Response:**

```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "order_number": "FS_12345",
            "agent_name": "John Doe",
            "amount": 250.0,
            "status": "approved",
            "approved_at": "2024-01-15T10:30:00Z",
            "paid_at": null,
            "created_at": "2024-01-15T10:30:00Z"
        }
    ]
}
```

### 16. Approve Commission

```http
PUT /admin/commissions/{commission_id}/approve
Authorization: Bearer admin_token
```

### 17. Reject Commission

```http
PUT /admin/commissions/{commission_id}/reject
Authorization: Bearer admin_token
```

### 18. Bulk Approve Commissions

```http
POST /admin/commissions/bulk-approve
Authorization: Bearer admin_token
Content-Type: application/json

{
    "commission_ids": [1, 2, 3]
}
```

### 19. Get System Settings

```http
GET /admin/settings
Authorization: Bearer admin_token
```

**Response:**

```json
{
    "success": true,
    "data": {
        "commission_rate": 0.1,
        "delivery_fee": 500,
        "max_delivery_distance": 5,
        "auto_assign_agents": true,
        "whatsapp_bot_url": "https://foodstuff-whatsapp-bot.herokuapp.com",
        "paystack_public_key": "pk_live_..."
    }
}
```

### 20. Update System Settings

```http
PUT /admin/settings
Authorization: Bearer admin_token
Content-Type: application/json

{
    "commission_rate": 0.15,
    "delivery_fee": 750,
    "max_delivery_distance": 7,
    "auto_assign_agents": false
}
```

---

## 👨‍💼 **Agent APIs (Protected)**

### 21. Agent Order Search

```http
GET /agent/orders/search?query=FS_12345
Authorization: Bearer agent_token
```

### 22. Get Agent Commissions

```http
GET /agent/commissions
Authorization: Bearer agent_token
```

### 23. Get Pending Commissions

```http
GET /agent/commissions/pending
Authorization: Bearer agent_token
```

### 24. Get Paid Commissions

```http
GET /agent/commissions/paid
Authorization: Bearer agent_token
```

---

## 📱 **WhatsApp Bot APIs**

### 25. Process WhatsApp Message

```http
POST /whatsapp/process-message
Content-Type: application/json

{
    "from": "+2348012345678",
    "body": "2kg rice, 1kg beans",
    "timestamp": 1642234567
}
```

### 26. Send WhatsApp Message

```http
POST /whatsapp/send-message
Content-Type: application/json

{
    "phone": "+2348012345678",
    "message": "Your order has been confirmed!"
}
```

### 27. Get WhatsApp Bot Status

```http
GET /whatsapp/status
```

---

## 📊 **Stats APIs**

### 28. Dashboard Stats

```http
GET /stats/dashboard
```

### 29. Market Stats

```http
GET /stats/markets
```

### 30. Agent Stats

```http
GET /stats/agents
```

### 31. Order Stats

```http
GET /stats/orders
```

### 32. Product Stats

```http
GET /stats/products
```

### 33. Earnings Stats

```http
GET /stats/earnings
```

### 34. Recent Activity

```http
GET /stats/recent-activity
```

### 35. Performance Metrics

```http
GET /stats/performance
```

---

## 🌍 **Geolocation APIs**

### 36. Location Search

```http
GET /geolocation/search?query=Lagos&limit=5
```

### 37. Reverse Geocoding

```http
GET /geolocation/reverse?latitude=6.5244&longitude=3.3792
```

---

## 📋 **Complete Order Flow Example**

### Step 1: Search for Products

```http
GET /products/autocomplete?query=rice
```

### Step 2: Find Nearby Markets

```http
GET /markets/nearby?latitude=6.5244&longitude=3.3792&radius=5
```

### Step 3: Get Product Prices

```http
GET /markets/1/prices
Content-Type: application/json

{
    "product_ids": [1, 2, 3]
}
```

### Step 4: Create Order

```http
POST /orders
Content-Type: application/json

{
    "whatsapp_number": "+2348012345678",
    "customer_name": "John Customer",
    "delivery_address": "123 Customer Street, Lagos",
    "delivery_latitude": 6.5244,
    "delivery_longitude": 3.3792,
    "market_id": 1,
    "items": [
        {
            "product_id": 1,
            "quantity": 2
        }
    ]
}
```

### Step 5: Update Order with Quantities

```http
PUT /orders/1/items
Content-Type: application/json

{
    "items": [
        {
            "product_id": 1,
            "quantity": 2,
            "unit_price": 1200.00,
            "product_name": "Rice"
        }
    ]
}
```

### Step 6: Initialize Payment

```http
POST /orders/1/checkout
Content-Type: application/json

{
    "email": "customer@example.com"
}
```

### Step 7: Update Order Status

```http
PUT /orders/1/status
Content-Type: application/json

{
    "status": "delivered",
    "message": "Order delivered successfully"
}
```

---

## 🔧 **Error Responses**

All APIs return consistent error responses:

```json
{
    "success": false,
    "message": "Error description",
    "errors": {
        "field": ["Validation error message"]
    }
}
```

## 📝 **Notes**

1. **Authentication**: Admin and Agent APIs require Bearer token authentication
2. **Rate Limiting**: APIs are optimized to respond within 1 second
3. **WhatsApp Integration**: Bot communicates with Laravel API via HTTP requests
4. **Proximity Calculation**: Uses Haversine formula for accurate distance calculation
5. **Payment Processing**: Paystack integration with automatic commission calculation
6. **Real-time Updates**: WhatsApp notifications sent for all order status changes

## 🚀 **Deployment**

-   **Main API**: `https://foodstuff-store-api.herokuapp.com`
-   **WhatsApp Bot**: `https://foodstuff-whatsapp-bot-6536aa3f6997.herokuapp.com`
-   **Frontend**: `https://marketplace.foodstuff.store`
