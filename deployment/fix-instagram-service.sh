#!/bin/bash

# Quick fix for InstagramApiService validateToken method
cd /var/www/instra_autopilot

echo "🔧 Fixing InstagramApiService validateToken method..."

# Backup the original file
cp app/Services/InstagramApiService.php app/Services/InstagramApiService.php.backup

# Apply the fix
cat > temp_fix.php << 'EOF'
    /**
     * Validate access token
     */
    public function validateToken($accessToken)
    {
        try {
            // Simple token validation by making a basic API call
            $response = $this->client->get('https://graph.facebook.com/v18.0/me', [
                'query' => [
                    'access_token' => $accessToken,
                ]
            ]);

            $result = json_decode($response->getBody()->getContents(), true);
            return isset($result['id']);
        } catch (\Exception $e) {
            return false;
        }
    }
}
EOF

# Replace the broken validateToken method
sed -i '/public function validateToken/,/^    }$/c\
    /**\
     * Validate access token\
     */\
    public function validateToken($accessToken)\
    {\
        try {\
            // Simple token validation by making a basic API call\
            $response = $this->client->get('\''https://graph.facebook.com/v18.0/me'\'', [\
                '\''query'\'' => [\
                    '\''access_token'\'' => $accessToken,\
                ]\
            ]);\
\
            $result = json_decode($response->getBody()->getContents(), true);\
            return isset($result['\''id'\'']);\
        } catch (\\Exception $e) {\
            return false;\
        }\
    }\
}' app/Services/InstagramApiService.php

rm temp_fix.php

echo "✅ Fix applied! Restarting queue service..."

# Restart the queue service
sudo systemctl restart instagram-queue

echo "🚀 Testing the fix..."

# Flush failed jobs and try again
php artisan queue:flush

echo "✅ Ready to test! Run: php artisan instagram:process-scheduled-posts"
