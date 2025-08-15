# FoodStuff Marketplace API Documentation

## Base URL

```
http://localhost:8000/api/v1
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
    "email": "john.doe@foodstuff.store",
    "password": "john"
}
```

## Public Endpoints

### 1. Find Nearby Markets

```http
GET /markets/nearby?latitude=6.5244&longitude=3.3792&radius=5
```

**Parameters:**

-   `latitude` (required): Customer's latitude
-   `longitude` (required): Customer's longitude
-   `radius` (optional): Search radius in km (default: 5, max: 50)

**Response:**

```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "name": "Central Market Lagos",
            "address": "123 Market Street, Lagos Island, Lagos",
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

### 2. Get Market Products

```http
GET /markets/{market_id}/products
```

**Optional Parameters:**

-   `category_id`: Filter by category
-   `search`: Search product names

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
            "image": null,
            "unit": "kg",
            "price": 1200.0,
            "stock_quantity": 100,
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
        "address": "123 Market Street, Lagos Island, Lagos"
    }
}
```

### 3. Search Products

```http
GET /products/search?query=rice
```

**Parameters:**

-   `query` (required): Search term
-   `category_id` (optional): Filter by category

### 4. Get Categories

```http
GET /categories
```

**Response:**

```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "name": "Grains & Cereals",
            "description": "Rice, beans, corn, and other grains",
            "image": "grains.jpg",
            "products_count": 4
        }
    ]
}
```

### 5. Geolocation Search

```http
GET /geolocation/search?query=Lagos
```

**Parameters:**

-   `query` (required): Location search term
-   `limit` (optional): Number of results (default: 5, max: 10)

**Response:**

```json
{
    "success": true,
    "data": [
        {
            "display_name": "Lagos, Nigeria",
            "latitude": 6.5244,
            "longitude": 3.3792,
            "type": "city",
            "importance": 0.9,
            "address": {
                "city": "Lagos",
                "country": "Nigeria"
            }
        }
    ],
    "count": 1
}
```

### 6. Create Order

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

### 7. Get Order Details

```http
GET /orders/{order_id}
```

### 8. Update Order Items

```http
PUT /orders/{order_id}/items
Content-Type: application/json

{
    "items": [
        {
            "product_id": 1,
            "quantity": 3,
            "unit_price": 1200.00,
            "product_name": "Rice"
        }
    ]
}
```

### 9. Initialize Payment

```http
POST /payments/initialize
Content-Type: application/json

{
    "order_id": 1,
    "email": "customer@example.com"
}
```

### 10. Verify Payment

```http
POST /payments/verify
Content-Type: application/json

{
    "reference": "FS20241201123456"
}
```

### 11. Dashboard Statistics

```http
GET /stats/dashboard
```

**Response:**

```json
{
    "success": true,
    "data": {
        "total_markets": 3,
        "total_agents": 5,
        "total_products": 30,
        "total_categories": 8,
        "total_orders": 25,
        "pending_orders": 5,
        "paid_orders": 15,
        "delivered_orders": 5,
        "total_revenue": 150000.0,
        "total_earnings": 15000.0,
        "pending_earnings": 5000.0
    }
}
```

### 12. Market Statistics

```http
GET /stats/markets
```

**Response:**

```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "name": "Central Market Lagos",
            "agents_count": 2,
            "orders_count": 10,
            "total_revenue": 50000.0,
            "is_active": true
        }
    ]
}
```

### 13. Agent Statistics

```http
GET /stats/agents
```

**Response:**

```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "name": "John Doe",
            "market": "Central Market Lagos",
            "orders_count": 5,
            "earnings_count": 5,
            "total_earnings": 5000.0,
            "is_active": true,
            "is_suspended": false
        }
    ]
}
```

### 14. Order Statistics

```http
GET /stats/orders
```

**Response:**

```json
{
    "success": true,
    "data": {
        "total_orders": 25,
        "orders_by_status": {
            "pending": 5,
            "paid": 15,
            "delivered": 5
        },
        "orders_by_market": [
            {
                "market_name": "Central Market Lagos",
                "count": 10
            }
        ],
        "revenue_by_month": [
            {
                "month": "2024-12",
                "revenue": 50000.0
            }
        ],
        "average_order_value": 6000.0,
        "top_products": [
            {
                "name": "Rice",
                "total_quantity": 50
            }
        ]
    }
}
```

### 15. Product Statistics

```http
GET /stats/products
```

**Response:**

```json
{
    "success": true,
    "data": {
        "total_products": 30,
        "products_by_category": [
            {
                "category_name": "Grains & Cereals",
                "count": 4
            }
        ],
        "available_products": 25,
        "products_with_stock": 20,
        "low_stock_products": 3,
        "out_of_stock_products": 5
    }
}
```

### 16. Earnings Statistics

```http
GET /stats/earnings
```

**Response:**

```json
{
    "success": true,
    "data": {
        "total_earnings": 20000.0,
        "paid_earnings": 15000.0,
        "pending_earnings": 5000.0,
        "earnings_by_agent": [
            {
                "agent_name": "John Doe",
                "total_earnings": 5000.0
            }
        ],
        "earnings_by_month": [
            {
                "month": "2024-12",
                "earnings": 5000.0
            }
        ]
    }
}
```

### 17. Recent Activity

```http
GET /stats/recent-activity
```

**Response:**

```json
{
    "success": true,
    "data": {
        "recent_orders": [
            {
                "id": 1,
                "order_number": "FS20241201123456",
                "customer_name": "John Customer",
                "total_amount": 6000.0,
                "status": "paid",
                "market": "Central Market Lagos",
                "agent": "John Doe",
                "created_at": "2024-12-01T10:00:00Z"
            }
        ],
        "recent_earnings": [
            {
                "id": 1,
                "agent_name": "John Doe",
                "order_number": "FS20241201123456",
                "amount": 600.0,
                "status": "paid",
                "created_at": "2024-12-01T10:00:00Z"
            }
        ]
    }
}
```

### 18. Performance Metrics

```http
GET /stats/performance
```

**Response:**

```json
{
    "success": true,
    "data": {
        "order_completion_rate": 85.5,
        "average_delivery_time": 2.5,
        "agent_performance": [
            {
                "agent_name": "John Doe",
                "completed_orders": 5,
                "total_earnings": 5000.0,
                "average_earnings_per_order": 1000.0
            }
        ],
        "market_performance": [
            {
                "market_name": "Central Market Lagos",
                "completed_orders": 10,
                "total_revenue": 50000.0,
                "average_order_value": 5000.0
            }
        ],
        "revenue_growth": {
            "current_month_revenue": 50000.0,
            "last_month_revenue": 40000.0,
            "growth_rate": 25.0,
            "trend": "up"
        }
    }
}
```

## Protected Endpoints (Admin)

**Headers:**

```
Authorization: Bearer admin_token_1234567890
```

### Admin Dashboard

```http
GET /admin/dashboard
```

### Market Management

```http
GET /admin/markets
POST /admin/markets
GET /admin/markets/{market_id}
PUT /admin/markets/{market_id}
DELETE /admin/markets/{market_id}
PUT /admin/markets/{market_id}/toggle-status
```

### Agent Management

```http
GET /admin/agents
POST /admin/agents
PUT /admin/agents/{agent_id}/suspend
PUT /admin/agents/{agent_id}/activate
PUT /admin/agents/{agent_id}/reset-password
```

### Order Management

```http
GET /admin/orders
PUT /admin/orders/{order_id}/assign-agent
PUT /admin/orders/{order_id}/status
```

## Protected Endpoints (Agent)

**Headers:**

```
Authorization: Bearer agent_token_1_1234567890
```

### Agent Dashboard

```http
GET /agent/dashboard
```

### Order Management

```http
GET /agent/orders
PUT /agent/orders/{order_id}/status
```

### Earnings

```http
GET /agent/earnings
```

### Product Management

```http
GET /agent/products
POST /agent/products
PUT /agent/products/{market_product_id}
DELETE /agent/products/{market_product_id}
```

### Profile Management

```http
GET /agent/profile
PUT /agent/profile
PUT /agent/change-password
```

## WhatsApp Integration

### Webhook

```http
POST /whatsapp/webhook
```

### Send Message

```http
POST /whatsapp/send-message
Content-Type: application/json

{
    "phone": "+2348012345678",
    "message": "Hello from FoodStuff Store!"
}
```

## Sample Data

### Test Admin Credentials

-   Email: `admin@foodstuff.store`
-   Password: `admin123`

### Test Agent Credentials

-   Email: `john.doe@foodstuff.store`
-   Password: `john`
-   Email: `jane.smith@foodstuff.store`
-   Password: `jane`
-   Email: `mike.johnson@foodstuff.store`
-   Password: `mike`
-   Email: `sarah.williams@foodstuff.store`
-   Password: `sarah`
-   Email: `david.brown@foodstuff.store`
-   Password: `david`

### Sample Markets

1. Central Market Lagos (ID: 1)
2. Victoria Island Market (ID: 2)
3. Ikeja Market (ID: 3)

### Sample Products

-   Rice, Beans, Corn, Wheat Flour
-   Tomatoes, Onions, Pepper, Carrots
-   Bananas, Oranges, Apples, Mangoes
-   Chicken, Beef, Fish, Eggs
-   Milk, Cheese, Yogurt
-   Salt, Sugar, Garlic, Ginger
-   Orange Juice, Water, Tea
-   Groundnuts, Biscuits, Chips

## Error Responses

### Validation Error

```json
{
    "success": false,
    "message": "The given data was invalid.",
    "errors": {
        "email": ["The email field is required."]
    }
}
```

### Authentication Error

```json
{
    "success": false,
    "message": "Invalid credentials"
}
```

### Not Found Error

```json
{
    "success": false,
    "message": "Resource not found"
}
```

## Rate Limiting

All endpoints are subject to rate limiting. Please implement appropriate retry logic with exponential backoff.

## Testing

Run the test suite:

```bash
php artisan test
```

Run specific API tests:

```bash
php artisan test --filter=ApiTest
```
