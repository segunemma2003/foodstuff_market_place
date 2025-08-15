const { Client, LocalAuth } = require('whatsapp-web.js');
const qrcode = require('qrcode-terminal');
const fs = require('fs');
const path = require('path');

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

// QR Code generation
client.on('qr', (qr) => {
    console.log('QR RECEIVED', qr);
    qrcode.generate(qr, { small: true });

    // Save QR code to file for Laravel to read
    fs.writeFileSync('/tmp/whatsapp-qr.txt', qr);
});

// Client ready
client.on('ready', () => {
    console.log('WhatsApp client is ready!');
    fs.writeFileSync('/tmp/whatsapp-status.txt', 'ready');
});

// Authentication failure
client.on('auth_failure', (msg) => {
    console.error('Authentication failed:', msg);
    fs.writeFileSync('/tmp/whatsapp-status.txt', 'auth_failed');
});

// Disconnected
client.on('disconnected', (reason) => {
    console.log('Client was disconnected:', reason);
    fs.writeFileSync('/tmp/whatsapp-status.txt', 'disconnected');
});

// Message received
client.on('message', async (msg) => {
    try {
        console.log('Message received:', msg.body);

        // Send message to Laravel API
        const response = await fetch('https://foodstuff-store-api-39172343a322.herokuapp.com/api/v1/whatsapp/process-message', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                from: msg.from,
                body: msg.body,
                timestamp: msg.timestamp,
                type: msg.type
            })
        });

        const data = await response.json();

        // Send response back to user
        if (data.success && data.message) {
            await msg.reply(data.message);
        }

    } catch (error) {
        console.error('Error processing message:', error);
    }
});

// Initialize client
client.initialize();

// Handle process termination
process.on('SIGINT', () => {
    console.log('Shutting down WhatsApp client...');
    client.destroy();
    process.exit(0);
});

process.on('SIGTERM', () => {
    console.log('Shutting down WhatsApp client...');
    client.destroy();
    process.exit(0);
});
