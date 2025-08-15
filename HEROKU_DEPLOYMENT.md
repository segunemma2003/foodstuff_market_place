# 🚀 Heroku Deployment Guide - FoodStuff Store API

## 📋 **Prerequisites**

1. **Heroku Account**: Sign up at [heroku.com](https://heroku.com)
2. **Heroku CLI**: Install from [devcenter.heroku.com](https://devcenter.heroku.com/articles/heroku-cli)
3. **Git Repository**: Your code should be in a Git repository
4. **Termii Account**: For WhatsApp messaging
5. **Paystack Account**: For payment processing

---

## 🔧 **Step 1: Install Heroku CLI**

### **macOS (using Homebrew):**

```bash
brew tap heroku/brew && brew install heroku
```

### **Windows:**

Download and install from [Heroku CLI](https://devcenter.heroku.com/articles/heroku-cli)

### **Linux:**

```bash
curl https://cli-assets.heroku.com/install.sh | sh
```

---

## 🔐 **Step 2: Login to Heroku**

```bash
heroku login
```

---

## 🏗️ **Step 3: Create Heroku App**

```bash
# Create new Heroku app
heroku create foodstuff-store-api

# Or use existing app
heroku git:remote -a your-app-name
```

---

## 🗄️ **Step 4: Add PostgreSQL Database**

```bash
# Add PostgreSQL addon
heroku addons:create heroku-postgresql:mini

# Verify database is created
heroku config | grep DATABASE_URL
```

---

## ⚙️ **Step 5: Configure Environment Variables**

```bash
# Set Laravel configuration
heroku config:set APP_NAME="FoodStuff Store"
heroku config:set APP_ENV=production
heroku config:set APP_DEBUG=false
heroku config:set LOG_CHANNEL=stack
heroku config:set CACHE_DRIVER=file
heroku config:set QUEUE_CONNECTION=sync
heroku config:set SESSION_DRIVER=file
heroku config:set SESSION_LIFETIME=120

# Set database configuration
heroku config:set DB_CONNECTION=postgresql

# Set your app URL (replace with your actual app name)
heroku config:set APP_URL=https://your-app-name.herokuapp.com
heroku config:set APP_FRONTEND_URL=https://foodstuff.store

# Set Termii WhatsApp configuration
heroku config:set TERMII_API_KEY=your_termii_api_key
heroku config:set TERMII_CHANNEL_ID=your_whatsapp_channel_id
heroku config:set TERMII_SENDER_ID=your_sender_id
heroku config:set TERMII_BASE_URL=https://api.ng.termii.com/api

# Set Paystack configuration
heroku config:set PAYSTACK_SECRET_KEY=your_paystack_secret_key
heroku config:set PAYSTACK_PUBLIC_KEY=your_paystack_public_key
heroku config:set PAYSTACK_BASE_URL=https://api.paystack.co
```

---

## 📤 **Step 6: Deploy to Heroku**

```bash
# Add all files to git
git add .

# Commit changes
git commit -m "Deploy FoodStuff Store API to Heroku"

# Push to Heroku
git push heroku main

# Or if using master branch
git push heroku master
```

---

## 🗃️ **Step 7: Run Database Migrations**

```bash
# Run migrations
heroku run php artisan migrate --force

# Run seeders
heroku run php artisan db:seed --force
```

---

## 🧪 **Step 8: Test the Deployment**

```bash
# Open your app
heroku open

# Check logs
heroku logs --tail

# Test API endpoints
curl https://your-app-name.herokuapp.com/api/v1/stats/dashboard
```

---

## 🔍 **Step 9: Verify Configuration**

```bash
# Check all environment variables
heroku config

# Check app status
heroku ps

# Check database status
heroku pg:info
```

---

## 📱 **Step 10: Configure WhatsApp Webhook**

### **Update Termii Webhook URL:**

```
https://your-app-name.herokuapp.com/api/v1/whatsapp/webhook
```

### **Test WhatsApp Integration:**

```bash
# Test sending a message
curl -X POST "https://your-app-name.herokuapp.com/api/v1/whatsapp/send-message" \
  -H "Content-Type: application/json" \
  -d '{
    "phone": "+2348012345678",
    "message": "Hello from FoodStuff Store!"
  }'
```

---

## 🚨 **Troubleshooting**

### **Issue 1: Build Fails**

```bash
# Check build logs
heroku logs --tail

# Common fixes:
# - Ensure composer.json is valid
# - Check PHP version compatibility
# - Verify all dependencies are in composer.json
```

### **Issue 2: Database Connection Fails**

```bash
# Check database URL
heroku config | grep DATABASE_URL

# Test database connection
heroku run php artisan tinker
# Then try: DB::connection()->getPdo();
```

### **Issue 3: Environment Variables Not Set**

```bash
# List all config vars
heroku config

# Set missing variables
heroku config:set VARIABLE_NAME=value
```

### **Issue 4: App Crashes**

```bash
# Check application logs
heroku logs --tail

# Restart the app
heroku restart
```

---

## 📊 **Monitoring & Maintenance**

### **Check App Performance:**

```bash
# View app metrics
heroku ps

# Check dyno usage
heroku ps:scale web=1
```

### **Database Management:**

```bash
# Backup database
heroku pg:backups:capture

# Download backup
heroku pg:backups:download

# View database info
heroku pg:info
```

### **Logs Management:**

```bash
# View real-time logs
heroku logs --tail

# View recent logs
heroku logs --num 100
```

---

## 🔄 **Continuous Deployment**

### **Automatic Deployments:**

1. **Connect GitHub Repository**

    - Go to Heroku Dashboard
    - Connect your GitHub repository
    - Enable automatic deploys

2. **Deploy on Push:**
    ```bash
    # Every push to main branch will auto-deploy
    git push origin main
    ```

---

## 📈 **Scaling**

### **Scale Web Dynos:**

```bash
# Scale to 2 web dynos
heroku ps:scale web=2

# Scale to 0 (pause app)
heroku ps:scale web=0
```

### **Add Worker Dynos (for queues):**

```bash
# Add worker dyno
heroku ps:scale worker=1
```

---

## 🔒 **Security**

### **SSL Certificate:**

```bash
# Heroku provides SSL automatically
# Your app will be available at https://your-app-name.herokuapp.com
```

### **Environment Variables:**

-   ✅ Never commit sensitive data to Git
-   ✅ Use Heroku config vars for secrets
-   ✅ Rotate API keys regularly

---

## 📞 **Support**

### **Heroku Support:**

-   [Heroku Documentation](https://devcenter.heroku.com/)
-   [Heroku Status](https://status.heroku.com/)
-   [Heroku Support](https://help.heroku.com/)

### **Laravel Support:**

-   [Laravel Documentation](https://laravel.com/docs)
-   [Laravel Forge](https://forge.laravel.com/)

---

## ✅ **Deployment Checklist**

-   [ ] Heroku CLI installed and logged in
-   [ ] Heroku app created
-   [ ] PostgreSQL database added
-   [ ] Environment variables configured
-   [ ] Code deployed to Heroku
-   [ ] Database migrations run
-   [ ] Database seeders run
-   [ ] API endpoints tested
-   [ ] WhatsApp webhook configured
-   [ ] SSL certificate working
-   [ ] Monitoring set up

---

## 🎉 **Success!**

Your FoodStuff Store API is now deployed on Heroku!

**App URL**: `https://your-app-name.herokuapp.com`

**API Base URL**: `https://your-app-name.herokuapp.com/api/v1`

**WhatsApp Webhook**: `https://your-app-name.herokuapp.com/api/v1/whatsapp/webhook`

---

## 📱 **Next Steps**

1. **Configure Frontend**: Update frontend to use new API URL
2. **Set up Monitoring**: Configure alerts and monitoring
3. **Test End-to-End**: Test complete order flow
4. **Go Live**: Start accepting real orders!

**🚀 Your FoodStuff Store API is ready for production!**
