# Instagram API Integration Setup Guide

This guide will help you set up Instagram API integration for the Instagram Autopilot application.

## Step 1: Create Instagram App

1. Go to [Facebook Developers](https://developers.facebook.com/)
2. Click "Create App"
3. Select "Business" as the app type
4. Fill in your app details:
   - App name: "Instagram Autopilot"
   - App contact email: your email
   - Business account: your business account

## Step 2: Add Instagram Basic Display

1. In your app dashboard, click "Add Product"
2. Find "Instagram Basic Display" and click "Set Up"
3. Go to Instagram Basic Display → Basic Display
4. Click "Create New App"

## Step 3: Configure Instagram App

1. In Basic Display settings:
   - **Valid OAuth Redirect URIs**: Add `http://localhost:8000/auth/instagram/callback`
   - **Deauthorize Callback URL**: Add `http://localhost:8000/auth/instagram/deauthorize`
   - **Data Deletion Request URL**: Add `http://localhost:8000/auth/instagram/delete`

## Step 4: Get App Credentials

1. Go to Instagram Basic Display → Basic Display
2. Copy your **Instagram App ID** and **Instagram App Secret**
3. Update your `.env` file:

```env
INSTAGRAM_CLIENT_ID=your_instagram_app_id_here
INSTAGRAM_CLIENT_SECRET=your_instagram_app_secret_here
INSTAGRAM_REDIRECT_URI=http://localhost:8000/auth/instagram/callback
```

## Step 5: Add Test Users (For Development)

1. Go to Instagram Basic Display → Roles → Roles
2. Click "Add Instagram Testers"
3. Enter your Instagram username
4. Accept the tester invitation in your Instagram app

## Step 6: Configure Queue Worker

Since Instagram posting uses queues, you need to run a queue worker:

```bash
php artisan queue:work
```

## Step 7: Set Up Scheduled Tasks (Optional)

To automatically process scheduled posts, add this to your crontab:

```bash
* * * * * cd /path/to/your/project && php artisan instagram:process-scheduled-posts >> /dev/null 2>&1
```

Or run it manually:

```bash
php artisan instagram:process-scheduled-posts
```

## Available Commands

### Process Scheduled Posts
```bash
php artisan instagram:process-scheduled-posts
```

### Test Instagram Posting
```bash
php artisan instagram:test-post {account_id} {post_id}
```

## How It Works

1. **Authentication**: Users click "Connect Instagram Account" to authorize the app
2. **Token Management**: The app stores long-lived access tokens (60 days)
3. **Posting**: When scheduled, posts are queued and posted via Instagram Graph API
4. **Scheduling**: Posts are automatically dispatched based on container schedules

## Instagram API Limitations

- **Rate Limits**: 200 requests per hour per user
- **Content Requirements**: Images must be publicly accessible URLs
- **File Formats**: Supports JPEG, PNG, GIF
- **File Size**: Maximum 8MB for images
- **Caption Length**: Maximum 2,200 characters

## Troubleshooting

### "Invalid access token"
- Token may have expired (60 days)
- User may have changed Instagram password
- App permissions may have been revoked

### "Image URL not accessible"
- Ensure storage link is created: `php artisan storage:link`
- Check if image files exist in `storage/app/public/posts/`
- Verify APP_URL is set correctly in .env

### "Queue jobs not processing"
- Make sure queue worker is running: `php artisan queue:work`
- Check queue configuration in `config/queue.php`
- Verify database connection for queue table

## Security Notes

1. Never commit Instagram credentials to version control
2. Use HTTPS in production for redirect URIs
3. Regularly refresh access tokens before expiry
4. Monitor API usage to avoid rate limits

## Production Setup

For production deployment:

1. Update redirect URI to your domain
2. Set APP_URL to your production URL
3. Use a proper queue driver (Redis, SQS, etc.)
4. Set up SSL certificate for HTTPS
5. Configure proper cron jobs for scheduling
