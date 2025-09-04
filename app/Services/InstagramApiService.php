<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;

class InstagramApiService
{
    protected $client;
    protected $clientId;
    protected $clientSecret;
    protected $redirectUri;
    protected $graphApiUrl;
    protected $basicDisplayApiUrl;

    public function __construct()
    {
        $this->client = new Client();
        $this->clientId = '1907520249713676';
        $this->clientSecret = 'cb4991c5bd1234f100d1ab2381f9395e';
        $this->redirectUri = 'https://bradygg.com/auth/instagram/callback';
        $this->graphApiUrl = 'https://graph.facebook.com/v18.0';
        $this->basicDisplayApiUrl = 'https://graph.instagram.com/v18.0';
    }

    /**
     * Generate Facebook authorization URL for multi-account access
     */
    public function getAuthorizationUrl($scopes = ['public_profile', 'email', 'pages_show_list', 'pages_read_engagement', 'instagram_basic', 'instagram_content_publish'])
    {
        $params = [
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'scope' => implode(',', $scopes),
            'response_type' => 'code',
            'state' => csrf_token(), // Add CSRF protection
        ];

        return 'https://www.facebook.com/v18.0/dialog/oauth?' . http_build_query($params);
    }

    /**
     * Exchange authorization code for user access token
     */
    public function getAccessToken($code)
    {
        try {
            $response = $this->client->post('https://graph.facebook.com/v18.0/oauth/access_token', [
                'form_params' => [
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'redirect_uri' => $this->redirectUri,
                    'code' => $code,
                ]
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            Log::error('Facebook OAuth Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get long-lived user access token (60 days)
     */
    public function getLongLivedUserToken($shortLivedToken)
    {
        try {
            $response = $this->client->get('https://graph.facebook.com/v18.0/oauth/access_token', [
                'query' => [
                    'grant_type' => 'fb_exchange_token',
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'fb_exchange_token' => $shortLivedToken,
                ]
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            Log::error('Facebook Token Exchange Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all Facebook Pages managed by the user
     */
    public function getFacebookPages($userAccessToken)
    {
        try {
            $response = $this->client->get('https://graph.facebook.com/v18.0/me/accounts', [
                'query' => [
                    'access_token' => $userAccessToken,
                    'fields' => 'id,name,access_token,instagram_business_account'
                ]
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            Log::error('Facebook Pages API Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get Instagram Business Account connected to a Facebook Page
     */
    public function getInstagramBusinessAccount($pageAccessToken, $pageId)
    {
        try {
            $response = $this->client->get("https://graph.facebook.com/v18.0/{$pageId}", [
                'query' => [
                    'fields' => 'instagram_business_account{id,username,name,profile_picture_url,followers_count,media_count}',
                    'access_token' => $pageAccessToken,
                ]
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            Log::error('Instagram Business Account API Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all Instagram accounts accessible through Facebook Pages
     */
    public function getAllInstagramAccounts($userAccessToken)
    {
        $instagramAccounts = [];
        
        // Get all Facebook Pages
        $pagesResponse = $this->getFacebookPages($userAccessToken);
        
        if (!$pagesResponse || !isset($pagesResponse['data'])) {
            return [];
        }

        foreach ($pagesResponse['data'] as $page) {
            // Get Instagram account for each page
            $igAccount = $this->getInstagramBusinessAccount($page['access_token'], $page['id']);
            
            if ($igAccount && isset($igAccount['instagram_business_account'])) {
                $instagramAccounts[] = [
                    'facebook_page_id' => $page['id'],
                    'facebook_page_name' => $page['name'],
                    'facebook_page_access_token' => $page['access_token'],
                    'instagram_account' => $igAccount['instagram_business_account']
                ];
            }
        }

        return $instagramAccounts;
    }

    /**
     * Get user profile information (Instagram Business Account)
     */
    public function getUserProfile($accessToken, $instagramAccountId)
    {
        try {
            $response = $this->client->get("https://graph.facebook.com/v18.0/{$instagramAccountId}", [
                'query' => [
                    'fields' => 'id,username,account_type,media_count',
                    'access_token' => $accessToken,
                ]
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            Log::error('Instagram API Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Upload media to Instagram (photo) - Graph API
     */
    public function createMediaObject($accessToken, $instagramAccountId, $imageUrl, $caption = '')
    {
        try {
            $response = $this->client->post("https://graph.facebook.com/v18.0/{$instagramAccountId}/media", [
                'form_params' => [
                    'image_url' => $imageUrl,
                    'caption' => $caption,
                    'access_token' => $accessToken,
                ]
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            Log::error('Instagram API Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Publish media to Instagram - Graph API
     */
    public function publishMedia($accessToken, $instagramAccountId, $creationId)
    {
        try {
            $response = $this->client->post("https://graph.facebook.com/v18.0/{$instagramAccountId}/media_publish", [
                'form_params' => [
                    'creation_id' => $creationId,
                    'access_token' => $accessToken,
                ]
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            Log::error('Instagram API Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Post a photo to Instagram (complete process) - Graph API
     */
    public function postPhoto($accessToken, $instagramAccountId, $imageUrl, $caption = '')
    {
        // Step 1: Create media object
        $mediaResponse = $this->createMediaObject($accessToken, $instagramAccountId, $imageUrl, $caption);
        
        if (!$mediaResponse || !isset($mediaResponse['id'])) {
            return false;
        }

        // Step 2: Wait a moment for processing
        sleep(2);

        // Step 3: Publish the media
        $publishResponse = $this->publishMedia($accessToken, $instagramAccountId, $mediaResponse['id']);

        return $publishResponse;
    }

    /**
     * Get user's media
     */
    public function getUserMedia($accessToken, $limit = 25)
    {
        try {
            $response = $this->client->get($this->graphApiUrl . '/me/media', [
                'query' => [
                    'fields' => 'id,caption,media_type,media_url,permalink,thumbnail_url,timestamp',
                    'limit' => $limit,
                    'access_token' => $accessToken,
                ]
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            Log::error('Instagram API Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Refresh access token
     */
    public function refreshAccessToken($accessToken)
    {
        try {
            $response = $this->client->get($this->graphApiUrl . '/refresh_access_token', [
                'query' => [
                    'grant_type' => 'ig_refresh_token',
                    'access_token' => $accessToken,
                ]
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            Log::error('Instagram API Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Validate access token
     */
    public function validateToken($accessToken)
    {
        $userProfile = $this->getUserProfile($accessToken);
        return $userProfile !== false;
    }
}
