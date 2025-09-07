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
     * Exchange short-lived Instagram access token for long-lived token (60 days)
     * This is specifically for Instagram Basic Display API tokens
     */
    public function getLongLivedInstagramToken($shortLivedToken)
    {
        try {
            $response = $this->client->get('https://graph.instagram.com/access_token', [
                'query' => [
                    'grant_type' => 'ig_exchange_token',
                    'client_secret' => $this->clientSecret,
                    'access_token' => $shortLivedToken,
                ]
            ]);

            $result = json_decode($response->getBody()->getContents(), true);
            
            Log::info('Instagram long-lived token exchange successful', [
                'token_type' => $result['token_type'] ?? 'unknown',
                'expires_in' => $result['expires_in'] ?? 'unknown'
            ]);
            
            return $result;
        } catch (RequestException $e) {
            Log::error('Instagram Long-Lived Token Exchange Error', [
                'error' => $e->getMessage(),
                'response' => $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : null
            ]);
            return false;
        }
    }

    /**
     * Get all Facebook Pages managed by the user
     */
    public function getFacebookPages($userAccessToken)
    {
        try {
            $allPages = [];
            $nextUrl = null;
            $processedCount = 0;
            $maxPages = 100; // Limit to prevent server overload on 512MB RAM
            $batchSize = 25; // Process in smaller batches
            
            Log::info('Starting to fetch Facebook pages', ['max_pages' => $maxPages, 'batch_size' => $batchSize]);
            
            do {
                if ($nextUrl) {
                    $response = $this->client->get($nextUrl);
                } else {
                    $response = $this->client->get('https://graph.facebook.com/v18.0/me/accounts', [
                        'query' => [
                            'access_token' => $userAccessToken,
                            'fields' => 'id,name,access_token,instagram_business_account,tasks,category',
                            'limit' => $batchSize
                        ]
                    ]);
                }

                $result = json_decode($response->getBody()->getContents(), true);
                
                if (isset($result['data'])) {
                    $allPages = array_merge($allPages, $result['data']);
                    $processedCount += count($result['data']);
                    
                    Log::info('Fetched batch of pages', [
                        'batch_count' => count($result['data']),
                        'total_count' => $processedCount,
                        'memory_usage' => memory_get_usage(true) . ' bytes'
                    ]);
                }
                
                // Get next page URL if exists
                $nextUrl = $result['paging']['next'] ?? null;
                
                // Safety limit to prevent server overload
                if ($processedCount >= $maxPages) {
                    Log::warning('Reached maximum page limit to prevent server overload', [
                        'processed' => $processedCount,
                        'limit' => $maxPages,
                        'has_more' => (bool)$nextUrl
                    ]);
                    break;
                }
                
                // Add delay to prevent rate limiting and reduce server load
                if ($nextUrl) {
                    usleep(200000); // 200ms delay
                }
                
            } while ($nextUrl && $processedCount < $maxPages);
            
            Log::info('Finished fetching Facebook pages', [
                'total_pages' => count($allPages),
                'has_more_available' => (bool)$nextUrl,
                'memory_peak' => memory_get_peak_usage(true) . ' bytes'
            ]);
            
            return ['data' => $allPages];
            
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
     * Get all Instagram accounts accessible through Facebook Pages (optimized for low memory)
     */
    public function getAllInstagramAccounts($userAccessToken, $startFromPage = 0)
    {
        $instagramAccounts = [];
        $processedPages = 0;
        $maxProcessingTime = 45; // increased from 30 to 45 seconds
        $startTime = time();
        
        Log::info('Getting Instagram accounts', [
            'token_length' => strlen($userAccessToken),
            'max_processing_time' => $maxProcessingTime . 's',
            'start_from_page' => $startFromPage
        ]);
        
        // Get Facebook Pages with pagination
        $pagesResponse = $this->getFacebookPages($userAccessToken);
        
        Log::info('Pages response', [
            'success' => (bool)$pagesResponse,
            'has_data' => $pagesResponse && isset($pagesResponse['data']),
            'page_count' => $pagesResponse && isset($pagesResponse['data']) ? count($pagesResponse['data']) : 0
        ]);
        
        if (!$pagesResponse || !isset($pagesResponse['data'])) {
            Log::warning('No pages found or invalid response');
            return [];
        }

        foreach ($pagesResponse['data'] as $index => $page) {
            // Skip pages if we're starting from a specific page
            if ($index < $startFromPage) {
                continue;
            }
            
            // Check processing time limit to prevent timeout
            if (time() - $startTime > $maxProcessingTime) {
                Log::warning('Processing time limit reached', [
                    'processed_pages' => $processedPages,
                    'total_pages' => count($pagesResponse['data']),
                    'processing_time' => time() - $startTime . 's',
                    'remaining_pages' => count($pagesResponse['data']) - $processedPages
                ]);
                break;
            }
            
            Log::info('Processing page', [
                'page_id' => $page['id'],
                'page_name' => $page['name'],
                'page_index' => $index,
                'processed_count' => $processedPages + 1,
                'memory_usage' => round(memory_get_usage(true) / 1024 / 1024, 2) . 'MB'
            ]);
            
            try {
                // Get Instagram account for each page
                $igAccount = $this->getInstagramBusinessAccount($page['access_token'], $page['id']);
                
                if ($igAccount && isset($igAccount['instagram_business_account'])) {
                    $instagramAccounts[] = [
                        'facebook_page_id' => $page['id'],
                        'facebook_page_name' => $page['name'],
                        'facebook_page_access_token' => $page['access_token'],
                        'instagram_account' => $igAccount['instagram_business_account']
                    ];
                    
                    Log::info('Found Instagram account', [
                        'username' => $igAccount['instagram_business_account']['username'] ?? 'unknown',
                        'ig_account_id' => $igAccount['instagram_business_account']['id'] ?? 'unknown'
                    ]);
                } else {
                    Log::debug('No Instagram account for page', ['page_id' => $page['id']]);
                }
                
                $processedPages++;
                
                // Reduced delay to process more pages within time limit
                usleep(100000); // 100ms delay (reduced from 150ms)
                
            } catch (\Exception $e) {
                Log::error('Error processing page', [
                    'page_id' => $page['id'],
                    'error' => $e->getMessage()
                ]);
                continue;
            }
        }

        Log::info('Final Instagram accounts', [
            'total_accounts_found' => count($instagramAccounts),
            'pages_processed' => $processedPages,
            'total_pages' => count($pagesResponse['data']),
            'processing_time' => time() - $startTime . 's',
            'memory_peak' => round(memory_get_peak_usage(true) / 1024 / 1024, 2) . 'MB'
        ]);

        return $instagramAccounts;
    }

    /**
     * Get first batch of Instagram accounts quickly for immediate display
     */
    public function getInstagramAccountsFirstBatch($userAccessToken)
    {
        $instagramAccounts = [];
        $processedPages = 0;
        $maxProcessingTime = 25; // Quick first batch
        $maxPagesToProcess = 50; // Process first 50 pages quickly
        $startTime = time();
        
        Log::info('Getting first batch of Instagram accounts', [
            'token_length' => strlen($userAccessToken),
            'max_processing_time' => $maxProcessingTime . 's',
            'max_pages_to_process' => $maxPagesToProcess
        ]);
        
        // Get first batch of Facebook Pages
        $pagesResponse = $this->getFacebookPagesLimited($userAccessToken, $maxPagesToProcess);
        
        Log::info('First batch pages response', [
            'success' => (bool)$pagesResponse,
            'has_data' => $pagesResponse && isset($pagesResponse['data']),
            'page_count' => $pagesResponse && isset($pagesResponse['data']) ? count($pagesResponse['data']) : 0
        ]);
        
        if (!$pagesResponse || !isset($pagesResponse['data'])) {
            Log::warning('No pages found in first batch');
            return ['accounts' => [], 'has_more_pages' => false, 'processed_pages' => 0];
        }

        foreach ($pagesResponse['data'] as $page) {
            // Quick timeout check
            if (time() - $startTime > $maxProcessingTime) {
                Log::info('First batch time limit reached', [
                    'processed_pages' => $processedPages,
                    'processing_time' => time() - $startTime . 's'
                ]);
                break;
            }
            
            try {
                // Get Instagram account for each page
                $igAccount = $this->getInstagramBusinessAccount($page['access_token'], $page['id']);
                
                if ($igAccount && isset($igAccount['instagram_business_account'])) {
                    $instagramAccounts[] = [
                        'facebook_page_id' => $page['id'],
                        'facebook_page_name' => $page['name'],
                        'facebook_page_access_token' => $page['access_token'],
                        'instagram_account' => $igAccount['instagram_business_account']
                    ];
                    
                    Log::info('Found Instagram account in first batch', [
                        'username' => $igAccount['instagram_business_account']['username'] ?? 'unknown'
                    ]);
                }
                
                $processedPages++;
                
                // Minimal delay for first batch
                usleep(50000); // 50ms delay
                
            } catch (\Exception $e) {
                Log::error('Error processing page in first batch', [
                    'page_id' => $page['id'],
                    'error' => $e->getMessage()
                ]);
                continue;
            }
        }

        $hasMorePages = $pagesResponse['has_more_available'] || $processedPages < count($pagesResponse['data']);

        Log::info('First batch completed', [
            'accounts_found' => count($instagramAccounts),
            'pages_processed' => $processedPages,
            'has_more_pages' => $hasMorePages,
            'processing_time' => time() - $startTime . 's'
        ]);

        return [
            'accounts' => $instagramAccounts,
            'has_more_pages' => $hasMorePages,
            'processed_pages' => $processedPages
        ];
    }

    /**
     * Get limited number of Facebook pages for first batch
     */
    private function getFacebookPagesLimited($userAccessToken, $maxPages = 50)
    {
        try {
            $allPages = [];
            $nextUrl = null;
            $processedCount = 0;
            $batchSize = 25;
            
            do {
                if ($nextUrl) {
                    $response = $this->client->get($nextUrl);
                } else {
                    $response = $this->client->get('https://graph.facebook.com/v18.0/me/accounts', [
                        'query' => [
                            'access_token' => $userAccessToken,
                            'fields' => 'id,name,access_token,instagram_business_account,tasks,category',
                            'limit' => $batchSize
                        ]
                    ]);
                }

                $result = json_decode($response->getBody()->getContents(), true);
                
                if (isset($result['data'])) {
                    $allPages = array_merge($allPages, $result['data']);
                    $processedCount += count($result['data']);
                }
                
                $nextUrl = $result['paging']['next'] ?? null;
                
                // Stop at max pages for first batch
                if ($processedCount >= $maxPages) {
                    break;
                }
                
            } while ($nextUrl && $processedCount < $maxPages);
            
            return [
                'data' => $allPages,
                'has_more_available' => (bool)$nextUrl || $processedCount >= $maxPages
            ];
            
        } catch (RequestException $e) {
            Log::error('Facebook Pages Limited API Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Import Instagram accounts in batches for background processing
     */
    public function importAccountsBatch($userAccessToken, $nextUrl = null, $batchSize = 25)
    {
        $instagramAccounts = [];
        $processedPages = 0;
        $maxProcessingTime = 25; // seconds - shorter for background jobs
        $startTime = time();
        
        Log::info('Starting batch import', [
            'batch_size' => $batchSize,
            'has_next_url' => !empty($nextUrl),
            'max_processing_time' => $maxProcessingTime . 's'
        ]);
        
        try {
            if ($nextUrl) {
                // Continue from where we left off
                $response = $this->client->get($nextUrl);
            } else {
                // Start fresh batch
                $response = $this->client->get('https://graph.facebook.com/v18.0/me/accounts', [
                    'query' => [
                        'access_token' => $userAccessToken,
                        'fields' => 'id,name,access_token,instagram_business_account,tasks,category',
                        'limit' => $batchSize
                    ]
                ]);
            }

            $result = json_decode($response->getBody()->getContents(), true);
            
            if (!isset($result['data'])) {
                Log::warning('No data in batch response');
                return ['accounts' => [], 'next_url' => null];
            }

            Log::info('Processing batch of pages', [
                'page_count' => count($result['data']),
                'memory_usage' => round(memory_get_usage(true) / 1024 / 1024, 2) . 'MB'
            ]);

            foreach ($result['data'] as $page) {
                // Check processing time limit
                if (time() - $startTime > $maxProcessingTime) {
                    Log::warning('Batch processing time limit reached', [
                        'processed_pages' => $processedPages,
                        'processing_time' => time() - $startTime . 's'
                    ]);
                    break;
                }
                
                try {
                    // Get Instagram account for each page
                    $igAccount = $this->getInstagramBusinessAccount($page['access_token'], $page['id']);
                    
                    if ($igAccount && isset($igAccount['instagram_business_account'])) {
                        $instagramAccounts[] = [
                            'facebook_page_id' => $page['id'],
                            'facebook_page_name' => $page['name'],
                            'facebook_page_access_token' => $page['access_token'],
                            'instagram_account' => $igAccount['instagram_business_account']
                        ];
                        
                        Log::debug('Found Instagram account in batch', [
                            'username' => $igAccount['instagram_business_account']['username'] ?? 'unknown',
                            'page_id' => $page['id']
                        ]);
                    }
                    
                    $processedPages++;
                    
                    // Small delay to reduce server load
                    usleep(100000); // 100ms delay for background jobs
                    
                } catch (\Exception $e) {
                    Log::error('Error processing page in batch', [
                        'page_id' => $page['id'],
                        'error' => $e->getMessage()
                    ]);
                    continue;
                }
            }

            $nextPageUrl = $result['paging']['next'] ?? null;

            Log::info('Batch import completed', [
                'accounts_found' => count($instagramAccounts),
                'pages_processed' => $processedPages,
                'processing_time' => time() - $startTime . 's',
                'has_next_page' => !empty($nextPageUrl),
                'memory_peak' => round(memory_get_peak_usage(true) / 1024 / 1024, 2) . 'MB'
            ]);

            return [
                'accounts' => $instagramAccounts,
                'next_url' => $nextPageUrl
            ];

        } catch (RequestException $e) {
            Log::error('Batch import API error', [
                'error' => $e->getMessage(),
                'response_body' => $e->getResponse() ? $e->getResponse()->getBody()->getContents() : 'No response'
            ]);
            throw $e;
        }
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
     * Refresh Instagram long-lived access token
     * This refreshes an existing long-lived token to get a new 60-day token
     */
    public function refreshAccessToken($accessToken)
    {
        try {
            // The correct Instagram Graph API endpoint for refreshing long-lived tokens
            $response = $this->client->get('https://graph.instagram.com/refresh_access_token', [
                'query' => [
                    'grant_type' => 'ig_refresh_token',
                    'access_token' => $accessToken,
                ]
            ]);

            $result = json_decode($response->getBody()->getContents(), true);
            
            Log::info('Instagram token refresh successful', [
                'access_token_type' => $result['token_type'] ?? 'unknown',
                'expires_in' => $result['expires_in'] ?? 'unknown'
            ]);
            
            return $result;
        } catch (RequestException $e) {
            Log::error('Instagram Token Refresh Error', [
                'error' => $e->getMessage(),
                'response' => $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : null
            ]);
            return false;
        }
    }

    /**
     * Check if an error response indicates token expiration/invalidity
     */
    public function isTokenExpiredError($errorResponse)
    {
        if (!$errorResponse) return false;
        
        // Common Instagram API error codes/messages for expired tokens
        $expiredTokenIndicators = [
            'OAuthException',
            'Invalid OAuth access token',
            'Access token has expired',
            'Token is expired',
            'Invalid access token',
            'The access token expired',
            'OAuth error',
            '#200', // Instagram API error code for invalid token
            '#190', // Facebook API error code for expired token
        ];
        
        $errorString = is_array($errorResponse) ? json_encode($errorResponse) : (string)$errorResponse;
        
        foreach ($expiredTokenIndicators as $indicator) {
            if (stripos($errorString, $indicator) !== false) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Attempt to refresh Facebook Page access token
     */
    public function refreshPageAccessToken($userAccessToken)
    {
        try {
            // Get fresh page access token using user access token
            $response = $this->client->get($this->graphApiUrl . '/me/accounts', [
                'query' => [
                    'access_token' => $userAccessToken,
                    'fields' => 'access_token,name,id,instagram_business_account'
                ]
            ]);

            $result = json_decode($response->getBody()->getContents(), true);
            
            Log::info('Page token refresh successful', [
                'pages_count' => count($result['data'] ?? [])
            ]);
            
            return $result;
        } catch (RequestException $e) {
            Log::error('Facebook Page Token Refresh Error', [
                'error' => $e->getMessage(),
                'response' => $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : null
            ]);
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
