# Instagram Multi-Account Posting Setup Guide

## 🎯 **Development Mode Approach - Post to All Your Instagram Accounts**

This guide shows you how to use **ONE Facebook App in Development Mode** to post to **ALL Instagram accounts** linked to your Facebook Pages. **No App Review needed!**

## Step 1: Create the Facebook App ✅ (You've Done This)

Your app is already created with:
- **App ID**: `1956604651783077`
- **App Secret**: `fe37068a102eeb5a13b901010c038f3a`
- **Redirect URI**: `https://bradygg.com/auth/instagram/callback`

## Step 2: Configure App Products

1. Go to [Meta for Developers](https://developers.facebook.com/) → My Apps → Your App
2. **Add Products**:
   - **Facebook Login** ✅
   - **Instagram Graph API** ✅
   - **Pages API** ✅

3. **Configure Facebook Login**:
   - Go to Facebook Login → Settings
   - **Valid OAuth Redirect URIs**: `https://bradygg.com/auth/instagram/callback`
   - **Client OAuth Login**: Yes
   - **Web OAuth Login**: Yes

## Step 3: App Permissions (Development Mode)

In **App Review** → **Permissions and Features**, you need these permissions:

### **Standard Permissions** (No review needed):
- `public_profile` ✅
- `email` ✅

### **Advanced Permissions** (Development Mode only):
- `pages_show_list` - Get user's Facebook Pages
- `pages_read_engagement` - Read Page data
- `instagram_basic` - Basic Instagram access
- `instagram_content_publish` - Post to Instagram

**Note**: In Development Mode, you can use these permissions for **Admins, Developers, and Testers** of your app without review.

## Step 4: Add App Users (Your Instagram Accounts)

1. Go to **App Roles** → **Roles**
2. **Add People**:
   - Add Facebook accounts that own the Instagram accounts you want to post to
   - Role: **Admin** or **Tester**
   - These users can use the app without App Review

## Step 5: Connect Instagram Business Accounts

For each Instagram account you want to automate:

1. **Create/Link Facebook Page**:
   - Go to [Facebook Pages](https://www.facebook.com/pages/create)
   - Create a page or use existing page
   - Make sure the Facebook account is an admin

2. **Convert Instagram to Professional**:
   - Open Instagram mobile app
   - Settings → Account → Switch to Professional Account
   - Choose **Creator** or **Business**
   - **Connect to Facebook Page**

3. **Link Instagram to Page**:
   - Go to Facebook Page Settings
   - Instagram → Connect Account
   - Login to Instagram and authorize

## Step 6: Update Your Application Code

Your app credentials are already set, but let's enhance the authentication flow:

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
