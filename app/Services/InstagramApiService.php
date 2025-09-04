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
        $this->clientId = config('services.instagram.client_id');
        $this->clientSecret = config('services.instagram.client_secret');
        $this->redirectUri = config('services.instagram.redirect_uri');
        $this->graphApiUrl = config('services.instagram.graph_api_url');
        $this->basicDisplayApiUrl = config('services.instagram.basic_display_api_url');
    }

    /**
     * Generate Instagram authorization URL
     */
    public function getAuthorizationUrl($scopes = ['user_profile', 'user_media'])
    {
        $params = [
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'scope' => implode(',', $scopes),
            'response_type' => 'code',
        ];

        return $this->basicDisplayApiUrl . '/oauth/authorize?' . http_build_query($params);
    }

    /**
     * Exchange authorization code for access token
     */
    public function getAccessToken($code)
    {
        try {
            $response = $this->client->post($this->basicDisplayApiUrl . '/oauth/access_token', [
                'form_params' => [
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'grant_type' => 'authorization_code',
                    'redirect_uri' => $this->redirectUri,
                    'code' => $code,
                ]
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            Log::error('Instagram API Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get long-lived access token
     */
    public function getLongLivedToken($shortLivedToken)
    {
        try {
            $response = $this->client->get($this->graphApiUrl . '/access_token', [
                'query' => [
                    'grant_type' => 'ig_exchange_token',
                    'client_secret' => $this->clientSecret,
                    'access_token' => $shortLivedToken,
                ]
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            Log::error('Instagram API Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get user profile information
     */
    public function getUserProfile($accessToken)
    {
        try {
            $response = $this->client->get($this->graphApiUrl . '/me', [
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
     * Upload media to Instagram (photo)
     */
    public function createMediaObject($accessToken, $imageUrl, $caption = '')
    {
        try {
            $response = $this->client->post($this->graphApiUrl . '/me/media', [
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
     * Publish media to Instagram
     */
    public function publishMedia($accessToken, $creationId)
    {
        try {
            $response = $this->client->post($this->graphApiUrl . '/me/media_publish', [
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
     * Post a photo to Instagram (complete process)
     */
    public function postPhoto($accessToken, $imageUrl, $caption = '')
    {
        // Step 1: Create media object
        $mediaResponse = $this->createMediaObject($accessToken, $imageUrl, $caption);
        
        if (!$mediaResponse || !isset($mediaResponse['id'])) {
            return false;
        }

        // Step 2: Wait a moment for processing
        sleep(2);

        // Step 3: Publish the media
        $publishResponse = $this->publishMedia($accessToken, $mediaResponse['id']);

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
