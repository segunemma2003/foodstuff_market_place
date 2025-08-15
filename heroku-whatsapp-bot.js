const { Client, LocalAuth } = require('whatsapp-web.js');
const qrcode = require('qrcode-terminal');
const express = require('express');
const cors = require('cors');

// Express server for Laravel communication
const app = express();
app.use(cors());
app.use(express.json());

const PORT = process.env.PORT || process.env.WHATSAPP_BOT_PORT || 3000;

// Initialize WhatsApp client with Heroku-optimized settings
const client = new Client({
    authStrategy: new LocalAuth({
        clientId: 'foodstuff-store-bot',
        dataPath: process.env.NODE_ENV === 'production' ? '/tmp' : './.wwebjs_auth'
    }),
    puppeteer: {
        headless: true,
        args: [
            '--no-sandbox',
            '--disable-setuid-sandbox',
            '--disable-dev-shm-usage',
            '--disable-accelerated-2d-canvas',
            '--no-first-run',
            '--no-zygote',
            '--single-process',
            '--disable-gpu',
            '--disable-extensions',
            '--disable-plugins',
            '--disable-images',
            '--disable-javascript',
            '--disable-background-timer-throttling',
            '--disable-backgrounding-occluded-windows',
            '--disable-renderer-backgrounding',
            '--disable-features=TranslateUI',
            '--disable-ipc-flooding-protection'
        ],
        executablePath: process.env.NODE_ENV === 'production' ?
            process.env.GOOGLE_CHROME_BIN || '/app/.apt/usr/bin/google-chrome' :
            undefined
    }
});

let isReady = false;
let qrCodeGenerated = false;

// QR Code generation
client.on('qr', (qr) => {
    console.log('🔄 QR Code received - Scan this with your phone:');
    qrcode.generate(qr, { small: true });
    qrCodeGenerated = true;

    console.log('\n📱 Open WhatsApp > Settings > Linked Devices > Link a Device');
    console.log('📱 Scan the QR code above');
    console.log('⏰ You have 60 seconds to scan the QR code...');

    // Auto-restart if QR code expires
    setTimeout(() => {
        if (!isReady && qrCodeGenerated) {
            console.log('⏰ QR code expired, restarting...');
            client.destroy();
            setTimeout(() => {
                client.initialize();
            }, 5000);
        }
    }, 60000);
});

// Client ready
client.on('ready', () => {
    console.log('✅ WhatsApp client is ready!');
    isReady = true;
    qrCodeGenerated = false;
});

// Authentication failure
client.on('auth_failure', (msg) => {
    console.error('❌ Authentication failed:', msg);
    isReady = false;

    // Restart after auth failure
    setTimeout(() => {
        console.log('🔄 Restarting after auth failure...');
        client.destroy();
        setTimeout(() => {
            client.initialize();
        }, 5000);
    }, 10000);
});

// Disconnected
client.on('disconnected', (reason) => {
    console.log('🔌 Client was disconnected:', reason);
    isReady = false;

    // Restart after disconnection
    setTimeout(() => {
        console.log('🔄 Restarting after disconnection...');
        client.destroy();
        setTimeout(() => {
            client.initialize();
        }, 5000);
    }, 10000);
});

// Message received
client.on('message', async (msg) => {
    try {
        console.log('📨 Message from:', msg.from, 'Body:', msg.body);

        // Simple bot logic
        const response = await handleMessage(msg.body);

        if (response) {
            await msg.reply(response);
        }

    } catch (error) {
        console.error('❌ Error processing message:', error);
    }
});

// Simple message handler
async function handleMessage(message) {
    const msg = message.toLowerCase().trim();

    // Greeting
    if (['hi', 'hello', 'start', 'menu'].includes(msg)) {
        return `🛒 *Welcome to FoodStuff Store!* 🛒

I'm here to help you order your foodstuff items.

📝 *How to order:*
• Simply tell me what you need (e.g., '2kg rice, 1kg beans')
• I'll add items to your cart
• Type 'done' when you're finished

🛍️ *Available commands:*
• 'view cart' - See your current items
• 'clear cart' - Remove all items
• 'done' - Complete your order

What would you like to order today? 🥕🍚🥩`;
    }

    // Done command
    if (msg === 'done') {
        return `🎉 *Order Summary*

Your order has been created!

🔗 *Next Steps:*
Visit: https://marketplace.foodstuff.store

Thank you for choosing FoodStuff Store! 🛒✨`;
    }

    // View cart
    if (msg === 'view cart') {
        return `🛒 *Your Cart*

• 2kg Rice
• 1kg Beans
• 500g Tomatoes

Type 'done' to complete your order!`;
    }

    // Clear cart
    if (msg === 'clear cart') {
        return `🗑️ Cart cleared! Start adding items again or type 'done' to finish.`;
    }

    // Default response
    return `✅ *Added to cart:* ${message}

🛒 What else would you like to add? Or type 'done' to complete your order.`;
}

// API Routes
app.get('/health', (req, res) => {
    res.json({
        status: 'ok',
        whatsapp_ready: isReady,
        qr_generated: qrCodeGenerated,
        timestamp: new Date().toISOString(),
        environment: process.env.NODE_ENV || 'development'
    });
});

app.post('/send-message', async (req, res) => {
    try {
        const { phone, message } = req.body;

        if (!isReady) {
            return res.status(503).json({
                success: false,
                message: 'WhatsApp client not ready',
                qr_generated: qrCodeGenerated
            });
        }

        if (!phone || !message) {
            return res.status(400).json({
                success: false,
                message: 'Phone and message are required'
            });
        }

        // Format phone number
        const formattedPhone = phone.includes('@c.us') ? phone : `${phone}@c.us`;

        // Send message
        await client.sendMessage(formattedPhone, message);

        res.json({
            success: true,
            message: 'Message sent successfully'
        });

    } catch (error) {
        console.error('❌ Error sending message:', error);
        res.status(500).json({
            success: false,
            message: 'Failed to send message'
        });
    }
});

app.get('/status', (req, res) => {
    res.json({
        whatsapp_ready: isReady,
        qr_generated: qrCodeGenerated,
        uptime: process.uptime(),
        memory: process.memoryUsage(),
        environment: process.env.NODE_ENV || 'development'
    });
});

// Start server
app.listen(PORT, () => {
    console.log(`🚀 WhatsApp bot server running on port ${PORT}`);
    console.log(`🌍 Environment: ${process.env.NODE_ENV || 'development'}`);
    console.log('🔄 Initializing WhatsApp client...');

    // Initialize WhatsApp client
    setTimeout(() => {
        client.initialize();
    }, 2000);
});

// Handle process termination
process.on('SIGINT', () => {
    console.log('🛑 Shutting down WhatsApp bot...');
    client.destroy();
    process.exit(0);
});

process.on('SIGTERM', () => {
    console.log('🛑 Shutting down WhatsApp bot...');
    client.destroy();
    process.exit(0);
});

// Handle uncaught exceptions
process.on('uncaughtException', (error) => {
    console.error('❌ Uncaught Exception:', error);
    // Don't exit, let the process continue
});

process.on('unhandledRejection', (reason, promise) => {
    console.error('❌ Unhandled Rejection at:', promise, 'reason:', reason);
    // Don't exit, let the process continue
});
