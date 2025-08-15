<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

-   [Simple, fast routing engine](https://laravel.com/docs/routing).
-   [Powerful dependency injection container](https://laravel.com/docs/container).
-   Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
-   Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
-   Database agnostic [schema migrations](https://laravel.com/docs/migrations).
-   [Robust background job processing](https://laravel.com/docs/queues).
-   [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

You may also try the [Laravel Bootcamp](https://bootcamp.laravel.com), where you will be guided through building a modern Laravel application from scratch.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

-   **[Vehikl](https://vehikl.com)**
-   **[Tighten Co.](https://tighten.co)**
-   **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
-   **[64 Robots](https://64robots.com)**
-   **[Curotec](https://www.curotec.com/services/technologies/laravel)**
-   **[DevSquad](https://devsquad.com/hire-laravel-developers)**
-   **[Redberry](https://redberry.international/laravel-development)**
-   **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

# FoodStuff Marketplace API

A comprehensive Laravel 12 API for a foodstuff marketplace with WhatsApp integration, real-time geolocation, and Paystack payment processing.

## Features

### 🛒 Core Features

-   **WhatsApp Bot Integration**: Users can place orders via WhatsApp chat
-   **Real-time Geolocation**: Find nearby markets within 5km radius
-   **Paystack Payment Integration**: Secure payment processing
-   **Agent Management**: Automated agent assignment and commission tracking
-   **Order Management**: Complete order lifecycle management
-   **Admin Dashboard**: Comprehensive admin panel for market and agent management

### 📱 WhatsApp Bot Features

-   Natural language order processing
-   Session management for ongoing conversations
-   Automatic order creation and link generation
-   Real-time status updates via WhatsApp

### 🗺️ Geolocation Features

-   Free OpenStreetMap integration for address search
-   Distance calculation between locations
-   Reverse geocoding support
-   Cached results for performance

### 💳 Payment Features

-   Paystack payment gateway integration
-   Payment verification and callback handling
-   Automatic agent commission calculation
-   Payment status tracking

### 👥 User Management

-   **Admin Panel**: Market and agent management
-   **Agent Dashboard**: Order management and earnings tracking
-   **Customer Interface**: Order placement and tracking

## API Endpoints

### Public Endpoints

#### WhatsApp Bot

-   `POST /api/v1/whatsapp/webhook` - WhatsApp webhook handler
-   `POST /api/v1/whatsapp/send-message` - Send WhatsApp message

#### Markets & Products

-   `GET /api/v1/markets/nearby` - Find nearby markets
-   `GET /api/v1/markets/{market}/products` - Get market products
-   `GET /api/v1/products/search` - Search products
-   `GET /api/v1/categories` - Get product categories

#### Orders

-   `POST /api/v1/orders` - Create new order
-   `GET /api/v1/orders/{order}` - Get order details
-   `PUT /api/v1/orders/{order}/items` - Update order items
-   `POST /api/v1/orders/{order}/checkout` - Initialize checkout

#### Payments

-   `POST /api/v1/payments/initialize` - Initialize payment
-   `POST /api/v1/payments/verify` - Verify payment
-   `POST /api/v1/payments/callback` - Payment callback

#### Geolocation

-   `GET /api/v1/geolocation/search` - Search locations
-   `GET /api/v1/geolocation/reverse` - Reverse geocoding

### Admin Endpoints (Protected)

#### Authentication

-   `POST /api/v1/admin/login` - Admin login

#### Dashboard

-   `GET /api/v1/admin/dashboard` - Admin dashboard stats

#### Market Management

-   `GET /api/v1/admin/markets` - List all markets
-   `POST /api/v1/admin/markets` - Create market
-   `GET /api/v1/admin/markets/{market}` - Get market details
-   `PUT /api/v1/admin/markets/{market}` - Update market
-   `DELETE /api/v1/admin/markets/{market}` - Delete market
-   `PUT /api/v1/admin/markets/{market}/toggle-status` - Toggle market status

#### Agent Management

-   `GET /api/v1/admin/agents` - List all agents
-   `POST /api/v1/admin/agents` - Create agent
-   `PUT /api/v1/admin/agents/{agent}/suspend` - Suspend agent
-   `PUT /api/v1/admin/agents/{agent}/activate` - Activate agent
-   `PUT /api/v1/admin/agents/{agent}/reset-password` - Reset agent password

#### Order Management

-   `GET /api/v1/admin/orders` - List all orders
-   `PUT /api/v1/admin/orders/{order}/assign-agent` - Assign agent to order
-   `PUT /api/v1/admin/orders/{order}/status` - Update order status

### Agent Endpoints (Protected)

#### Authentication

-   `POST /api/v1/agent/login` - Agent login

#### Dashboard

-   `GET /api/v1/agent/dashboard` - Agent dashboard stats

#### Orders

-   `GET /api/v1/agent/orders` - Get agent orders
-   `PUT /api/v1/agent/orders/{order}/status` - Update order status

#### Earnings

-   `GET /api/v1/agent/earnings` - Get agent earnings

#### Products

-   `GET /api/v1/agent/products` - Get agent products
-   `POST /api/v1/agent/products` - Add product
-   `PUT /api/v1/agent/products/{marketProduct}` - Update product
-   `DELETE /api/v1/agent/products/{marketProduct}` - Remove product

#### Profile

-   `GET /api/v1/agent/profile` - Get agent profile
-   `PUT /api/v1/agent/profile` - Update agent profile
-   `PUT /api/v1/agent/change-password` - Change password

## Installation

### Prerequisites

-   PHP 8.2+
-   Composer
-   MySQL/PostgreSQL
-   Node.js & NPM (for frontend)

### Setup

1. **Clone the repository**

```bash
git clone <repository-url>
cd foodstuff-marketplace
```

2. **Install dependencies**

```bash
composer install
npm install
```

3. **Environment setup**

```bash
cp .env.example .env
php artisan key:generate
```

4. **Configure environment variables**

```env
# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=foodstuff_marketplace
DB_USERNAME=root
DB_PASSWORD=

# WhatsApp Configuration
WHATSAPP_BASE_URL=https://graph.facebook.com/v18.0
WHATSAPP_ACCESS_TOKEN=your_whatsapp_access_token
WHATSAPP_PHONE_NUMBER_ID=your_phone_number_id
WHATSAPP_WEBHOOK_VERIFY_TOKEN=your_webhook_verify_token

# Paystack Configuration
PAYSTACK_PUBLIC_KEY=your_paystack_public_key
PAYSTACK_SECRET_KEY=your_paystack_secret_key
PAYSTACK_WEBHOOK_SECRET=your_webhook_secret

# Frontend URL
FRONTEND_URL=http://localhost:3000
```

5. **Run migrations**

```bash
php artisan migrate
```

6. **Seed database (optional)**

```bash
php artisan db:seed
```

7. **Start the server**

```bash
php artisan serve
```

## Database Schema

### Core Tables

-   `markets` - Market information and locations
-   `agents` - Agent profiles and authentication
-   `categories` - Product categories
-   `products` - Product catalog
-   `market_products` - Product pricing per market
-   `orders` - Order information
-   `order_items` - Individual items in orders
-   `order_status_logs` - Order status history
-   `whatsapp_sessions` - WhatsApp chat sessions
-   `agent_earnings` - Agent commission tracking

## WhatsApp Bot Flow

1. **Greeting**: User sends greeting message
2. **Order Initiation**: User types "order" to start
3. **Item Addition**: User lists items (e.g., "2 kg rice", "5 tomatoes")
4. **Order Completion**: User types "done" when finished
5. **Address Collection**: User provides delivery address
6. **Order Creation**: System creates order and sends checkout link
7. **Status Updates**: Real-time updates via WhatsApp

## Payment Flow

1. **Order Creation**: Customer creates order via WhatsApp or frontend
2. **Market Selection**: Customer selects preferred market
3. **Price Review**: Customer reviews prices and confirms
4. **Payment Initialization**: Paystack payment is initialized
5. **Payment Processing**: Customer completes payment
6. **Payment Verification**: System verifies payment
7. **Agent Assignment**: System automatically assigns available agent
8. **Order Fulfillment**: Agent processes and delivers order

## Admin Features

### Market Management

-   Add/edit/delete markets
-   Set market locations and contact info
-   Toggle market availability

### Agent Management

-   Create agent accounts with default passwords
-   Suspend/activate agents
-   Reset agent passwords
-   Monitor agent performance

### Order Management

-   View all orders with filters
-   Manually assign agents
-   Update order statuses
-   Monitor order fulfillment

## Agent Features

### Order Management

-   View assigned orders
-   Update order status
-   Track order progress

### Product Management

-   Add products to market
-   Set prices and stock levels
-   Manage product availability

### Earnings Tracking

-   View commission earnings
-   Track payment status
-   Monitor performance metrics

## Performance Optimization

-   **Caching**: Geolocation results cached for 1 hour
-   **Database Indexing**: Optimized indexes on frequently queried columns
-   **API Response Time**: All endpoints optimized for <1 second response
-   **Lazy Loading**: Efficient relationship loading

## Security Features

-   **Input Validation**: Comprehensive request validation
-   **SQL Injection Protection**: Eloquent ORM with parameter binding
-   **XSS Protection**: Output sanitization
-   **Rate Limiting**: API rate limiting (to be implemented)
-   **Authentication**: Token-based authentication for admin/agent routes

## Testing

```bash
# Run tests
php artisan test

# Run specific test suite
php artisan test --filter=OrderTest
```

## Deployment

### Production Checklist

-   [ ] Set `APP_ENV=production`
-   [ ] Configure production database
-   [ ] Set up SSL certificates
-   [ ] Configure WhatsApp webhook URL
-   [ ] Set up Paystack webhook URL
-   [ ] Configure email settings
-   [ ] Set up monitoring and logging
-   [ ] Configure backup strategy

### Environment Variables

Ensure all required environment variables are set in production:

-   Database credentials
-   WhatsApp API credentials
-   Paystack API credentials
-   Application keys and secrets

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests
5. Submit a pull request

## License

This project is licensed under the MIT License.

## Support

For support, email operations@foodstuff.store or create an issue in the repository.

# FoodStuff Store WhatsApp Bot

A WhatsApp bot for the FoodStuff Store marketplace that handles customer orders and communicates with the main Laravel API.

## Features

-   🤖 WhatsApp Web.js integration
-   🔄 Auto-restart on disconnection
-   📱 QR code authentication
-   🔗 Laravel API communication
-   🛒 Order management
-   📊 Health monitoring

## Quick Start

### Local Development

```bash
# Install dependencies
npm install

# Start development server
npm run dev
```

### Heroku Deployment

```bash
# Deploy to Heroku
git push heroku master

# Check logs
heroku logs --tail

# Scale dyno
heroku ps:scale web=1
```

## Environment Variables

-   `NODE_ENV`: Node.js environment (production/development)
-   `LARAVEL_API_URL`: URL of the main Laravel API

## API Endpoints

-   `GET /health`: Health check
-   `GET /status`: Bot status
-   `POST /send-message`: Send WhatsApp message

## QR Code Authentication

1. Start the bot
2. Scan QR code with WhatsApp
3. Bot will be ready for messages

## Monitoring

```bash
# Check bot status
curl https://your-app.herokuapp.com/health

# View logs
heroku logs --tail
```

## Architecture

This bot runs separately from the main Laravel API and communicates via HTTP requests. It handles:

-   WhatsApp message processing
-   Session management
-   Auto-restart on failures
-   Communication with Laravel API
