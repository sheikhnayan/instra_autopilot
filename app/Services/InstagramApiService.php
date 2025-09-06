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
        // $this->clientId = '1780164582874123';
        $this->clientSecret = 'cb4991c5bd1234f100d1ab2381f9395e';
        // $this->clientSecret = '10585cf119991806f43efd569e0bf7dd';
        $this->redirectUri = 'https://bradygg.com/auth/instagram/callback';
        $this->graphApiUrl = 'https://graph.facebook.com/v18.0';
        $this->basicDisplayApiUrl = 'https://graph.instagram.com/v18.0';
    }

    /**
     * Generate Facebook authorization URL for multi-account access
     */
    public function getAuthorizationUrl($scopes = ['public_profile', 'email', 'pages_show_list', 'pages_read_engagement', 'pages_manage_metadata', 'pages_manage_posts', 'instagram_basic', 'instagram_content_publish', 'business_management'])
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
            // First, let's check what permissions we have
            $permissionsResponse = $this->client->get('https://graph.facebook.com/v18.0/me/permissions', [
                'query' => [
                    'access_token' => $userAccessToken,
                ]
            ]);
            
            $permissions = json_decode($permissionsResponse->getBody()->getContents(), true);
            Log::info('User permissions', ['permissions' => $permissions]);
            
            $response = $this->client->get('https://graph.facebook.com/v18.0/me/accounts', [
                'query' => [
                    'access_token' => $userAccessToken,
                    'fields' => 'id,name,access_token,instagram_business_account,tasks,category'
                ]
            ]);

            $result = json_decode($response->getBody()->getContents(), true);
            
            // Also try to get business pages specifically
            try {
                $businessResponse = $this->client->get('https://graph.facebook.com/v18.0/me', [
                    'query' => [
                        'access_token' => $userAccessToken,
                        'fields' => 'accounts{id,name,access_token,instagram_business_account,tasks,category}'
                    ]
                ]);
                
                $businessResult = json_decode($businessResponse->getBody()->getContents(), true);
                Log::info('Business accounts query', ['result' => $businessResult]);
            } catch (RequestException $e) {
                Log::info('Business accounts query failed', ['error' => $e->getMessage()]);
            }
            
            return $result;
            
        } catch (RequestException $e) {
            Log::error('Facebook Pages API Error: ' . $e->getMessage());
            if ($e->getResponse()) {
                Log::error('Response body: ' . $e->getResponse()->getBody()->getContents());
            }
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
        
        Log::info('Getting Instagram accounts', ['token_length' => strlen($userAccessToken)]);
        
        // Get all Facebook Pages
        $pagesResponse = $this->getFacebookPages($userAccessToken);
        
        Log::info('Pages response', [
            'success' => (bool)$pagesResponse,
            'has_data' => $pagesResponse && isset($pagesResponse['data']),
            'page_count' => $pagesResponse && isset($pagesResponse['data']) ? count($pagesResponse['data']) : 0,
            'response' => $pagesResponse
        ]);
        
        if (!$pagesResponse || !isset($pagesResponse['data'])) {
            Log::warning('No pages found or invalid response');
            return [];
        }

        foreach ($pagesResponse['data'] as $page) {
            Log::info('Processing page', [
                'page_id' => $page['id'],
                'page_name' => $page['name'],
                'has_instagram_account' => isset($page['instagram_business_account'])
            ]);
            
            // Get Instagram account for each page
            $igAccount = $this->getInstagramBusinessAccount($page['access_token'], $page['id']);
            
            Log::info('Instagram account response', [
                'page_id' => $page['id'],
                'ig_response' => $igAccount,
                'has_ig_account' => $igAccount && isset($igAccount['instagram_business_account'])
            ]);
            
            if ($igAccount && isset($igAccount['instagram_business_account'])) {
                $instagramAccounts[] = [
                    'facebook_page_id' => $page['id'],
                    'facebook_page_name' => $page['name'],
                    'facebook_page_access_token' => $page['access_token'],
                    'instagram_account' => $igAccount['instagram_business_account']
                ];
            }
        }

        Log::info('Final Instagram accounts', [
            'count' => count($instagramAccounts),
            'accounts' => $instagramAccounts
        ]);

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
    public function createMediaObject($accessToken, $instagramAccountId, $imageUrl, $caption = '', $isCarouselItem = false)
    {
        try {
            $params = [
                'image_url' => $imageUrl,
                'access_token' => $accessToken,
            ];

            // For single posts, add caption. For carousel items, caption goes on the container
            if (!$isCarouselItem && $caption) {
                $params['caption'] = $caption;
            }

            // For carousel items, specify it's a carousel item
            if ($isCarouselItem) {
                $params['is_carousel_item'] = 'true';
            }

            $response = $this->client->post("https://graph.facebook.com/v18.0/{$instagramAccountId}/media", [
                'form_params' => $params
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            Log::error('Instagram API Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Create carousel container for multiple images
     */
    public function createCarouselContainer($accessToken, $instagramAccountId, $mediaIds, $caption = '')
    {
        try {
            $params = [
                'media_type' => 'CAROUSEL',
                'children' => implode(',', $mediaIds),
                'access_token' => $accessToken,
            ];

            if ($caption) {
                $params['caption'] = $caption;
            }

            $response = $this->client->post("https://graph.facebook.com/v18.0/{$instagramAccountId}/media", [
                'form_params' => $params
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            Log::error('Instagram API Error creating carousel container: ' . $e->getMessage());
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
     * Post multiple photos as a carousel to Instagram
     */
    public function postCarousel($accessToken, $instagramAccountId, $imageUrls, $caption = '')
    {
        Log::info('Starting carousel post creation', [
            'instagram_account_id' => $instagramAccountId,
            'image_count' => count($imageUrls),
            'caption_length' => strlen($caption)
        ]);

        $mediaIds = [];
        
        // Step 1: Create media objects for each image
        foreach ($imageUrls as $index => $imageUrl) {
            Log::info("Creating media object for image {$index}", ['url' => $imageUrl]);
            
            $mediaResponse = $this->createMediaObject($accessToken, $instagramAccountId, $imageUrl, '', true); // true for carousel
            
            if (!$mediaResponse || !isset($mediaResponse['id'])) {
                Log::error("Failed to create media object for image {$index}", ['response' => $mediaResponse]);
                return false;
            }
            
            $mediaIds[] = $mediaResponse['id'];
            Log::info("Created media object {$index}", ['media_id' => $mediaResponse['id']]);
        }

        // Step 2: Wait for all media to process
        sleep(3);

        // Step 3: Create carousel container
        $carouselResponse = $this->createCarouselContainer($accessToken, $instagramAccountId, $mediaIds, $caption);
        
        if (!$carouselResponse || !isset($carouselResponse['id'])) {
            Log::error('Failed to create carousel container', ['response' => $carouselResponse]);
            return false;
        }

        // Step 4: Wait for carousel processing
        sleep(2);

        // Step 5: Publish the carousel
        $publishResponse = $this->publishMedia($accessToken, $instagramAccountId, $carouselResponse['id']);
        
        Log::info('Carousel post completed', ['publish_response' => $publishResponse]);
        
        return $publishResponse;
    }

    /**
     * Post an Instagram Story
     */
    public function postStory($accessToken, $instagramAccountId, $imageUrl, $stickers = [])
    {
        Log::info('Starting story post creation', [
            'instagram_account_id' => $instagramAccountId,
            'image_url' => $imageUrl,
            'stickers' => $stickers
        ]);

        // Step 1: Create story media object
        $mediaResponse = $this->createStoryMediaObject($accessToken, $instagramAccountId, $imageUrl, $stickers);
        
        if (!$mediaResponse || !isset($mediaResponse['id'])) {
            Log::error('Failed to create story media object', ['response' => $mediaResponse]);
            return false;
        }

        // Step 2: Wait for processing
        sleep(2);

        // Step 3: Publish the story
        $publishResponse = $this->publishMedia($accessToken, $instagramAccountId, $mediaResponse['id']);
        
        Log::info('Story post completed', ['publish_response' => $publishResponse]);
        
        return $publishResponse;
    }

    /**
     * Create story media object
     */
    private function createStoryMediaObject($accessToken, $instagramAccountId, $imageUrl, $stickers = [])
    {
        try {
            $params = [
                'image_url' => $imageUrl,
                'media_type' => 'STORIES',
                'access_token' => $accessToken,
            ];

            // Add story stickers if provided
            if (!empty($stickers)) {
                // Instagram Stories support various stickers like polls, questions, etc.
                // This is a basic implementation - can be expanded for specific sticker types
                foreach ($stickers as $sticker) {
                    if (isset($sticker['type']) && isset($sticker['data'])) {
                        $params[$sticker['type']] = json_encode($sticker['data']);
                    }
                }
            }

            $response = $this->client->post("https://graph.facebook.com/v18.0/{$instagramAccountId}/media", [
                'form_params' => $params
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            Log::error('Instagram API Error creating story media: ' . $e->getMessage());
            return false;
        }
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

    /**
     * Get user info for debugging
     */
    public function getUserInfo($accessToken)
    {
        try {
            $response = $this->client->get('https://graph.facebook.com/v18.0/me', [
                'query' => [
                    'fields' => 'id,name,email',
                    'access_token' => $accessToken,
                ]
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            Log::error('Get User Info Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get user pages for debugging
     */
    public function getUserPages($accessToken)
    {
        try {
            $response = $this->client->get('https://graph.facebook.com/v18.0/me/accounts', [
                'query' => [
                    'fields' => 'id,name,access_token,instagram_business_account',
                    'access_token' => $accessToken,
                ]
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            Log::error('Get User Pages Error: ' . $e->getMessage());
            return false;
        }
    }
}
