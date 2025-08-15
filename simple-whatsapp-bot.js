const { Client, LocalAuth } = require('whatsapp-web.js');
const qrcode = require('qrcode-terminal');
const express = require('express');
const cors = require('cors');

// Express server for Laravel communication
const app = express();
app.use(cors());
app.use(express.json());

const PORT = process.env.PORT || 3000;

// Initialize WhatsApp client
const client = new Client({
    authStrategy: new LocalAuth(),
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
            '--disable-gpu'
        ]
    }
});

let isReady = false;

// QR Code generation
client.on('qr', (qr) => {
    console.log('🔄 QR Code received - Scan this with your phone:');
    qrcode.generate(qr, { small: true });
    console.log('\n📱 Open WhatsApp > Settings > Linked Devices > Link a Device');
    console.log('📱 Scan the QR code above');
});

// Client ready
client.on('ready', () => {
    console.log('✅ WhatsApp client is ready!');
    isReady = true;
});

// Authentication failure
client.on('auth_failure', (msg) => {
    console.error('❌ Authentication failed:', msg);
    isReady = false;
});

// Disconnected
client.on('disconnected', (reason) => {
    console.log('🔌 Client was disconnected:', reason);
    isReady = false;
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
        timestamp: new Date().toISOString()
    });
});

app.post('/send-message', async (req, res) => {
    try {
        const { phone, message } = req.body;

        if (!isReady) {
            return res.status(503).json({
                success: false,
                message: 'WhatsApp client not ready'
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

// Start server
app.listen(PORT, () => {
    console.log(`🚀 WhatsApp bot server running on port ${PORT}`);
    console.log('🔄 Initializing WhatsApp client...');
    client.initialize();
});

// Handle process termination
process.on('SIGINT', () => {
    console.log('🛑 Shutting down WhatsApp bot...');
    client.destroy();
    process.exit(0);
});
