<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\InstagramPost;
use App\Models\ContentContainer;
use App\Models\InstagramAccount;
use App\Jobs\PostToInstagramJob;
use App\Services\InstagramApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Log;
use Mockery;

class InstagramStoriesTest extends TestCase
{
    use RefreshDatabase;

    protected $instagramAccount;
    protected $contentContainer;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test Instagram account
        $this->instagramAccount = InstagramAccount::factory()->create([
            'username' => 'test_story_account',
            'access_token' => 'test_access_token',
            'instagram_business_account_id' => 'test_business_id'
        ]);

        // Create test content container
        $this->contentContainer = ContentContainer::factory()->create([
            'instagram_account_id' => $this->instagramAccount->id,
            'name' => 'Stories Test Container'
        ]);
    }

    /** @test */
    public function it_can_create_an_instagram_story_post()
    {
        $storyPost = InstagramPost::create([
            'content_container_id' => $this->contentContainer->id,
            'caption' => 'This is a test Instagram Story! 🎉',
            'images' => ['/images/test-story.jpg'],
            'hashtags' => ['#test', '#story', '#feature'],
            'post_type' => 'story',
            'is_story' => true,
            'story_stickers' => [
                [
                    'sticker_type' => 'poll',
                    'text' => 'Do you like the new Stories feature?',
                    'options' => ['Love it!', 'Need improvements'],
                    'position' => ['x' => 0.5, 'y' => 0.7]
                ],
                [
                    'sticker_type' => 'mention',
                    'username' => '@developer_account',
                    'position' => ['x' => 0.3, 'y' => 0.2]
                ]
            ],
            'story_duration' => 15,
            'order' => 1,
            'status' => 'scheduled'
        ]);

        // Verify story post was created with correct attributes
        $this->assertDatabaseHas('instagram_posts', [
            'id' => $storyPost->id,
            'is_story' => true,
            'post_type' => 'story',
            'story_duration' => 15
        ]);

        // Verify story stickers are stored correctly
        $this->assertIsArray($storyPost->story_stickers);
        $this->assertCount(2, $storyPost->story_stickers);
        $this->assertEquals('poll', $storyPost->story_stickers[0]['sticker_type']);
        $this->assertEquals('mention', $storyPost->story_stickers[1]['sticker_type']);

        return $storyPost;
    }

    /** @test */
    public function it_can_create_a_regular_post_vs_story()
    {
        // Create a regular post
        $regularPost = InstagramPost::create([
            'content_container_id' => $this->contentContainer->id,
            'caption' => 'This is a regular Instagram post',
            'images' => ['/images/regular-post.jpg'],
            'hashtags' => ['#regular', '#post'],
            'post_type' => 'photo',
            'is_story' => false,
            'order' => 1,
            'status' => 'scheduled'
        ]);

        // Create a story post
        $storyPost = InstagramPost::create([
            'content_container_id' => $this->contentContainer->id,
            'caption' => 'This is a story post',
            'images' => ['/images/story-post.jpg'],
            'hashtags' => ['#story'],
            'post_type' => 'story',
            'is_story' => true,
            'story_duration' => 10,
            'order' => 2,
            'status' => 'scheduled'
        ]);

        // Verify both posts have different characteristics
        $this->assertFalse($regularPost->is_story);
        $this->assertTrue($storyPost->is_story);
        $this->assertEquals('photo', $regularPost->post_type);
        $this->assertEquals('story', $storyPost->post_type);
        $this->assertEquals(15, $regularPost->story_duration); // Default value
        $this->assertEquals(10, $storyPost->story_duration); // Custom value
    }

    /** @test */
    public function it_processes_story_job_correctly()
    {
        Queue::fake();

        $storyPost = InstagramPost::create([
            'content_container_id' => $this->contentContainer->id,
            'caption' => 'Job processing test story',
            'images' => ['/images/job-test-story.jpg'],
            'post_type' => 'story',
            'is_story' => true,
            'story_stickers' => [
                [
                    'sticker_type' => 'question',
                    'text' => 'What do you think?',
                    'position' => ['x' => 0.5, 'y' => 0.8]
                ]
            ],
            'order' => 1,
            'status' => 'scheduled'
        ]);

        // Dispatch the job
        PostToInstagramJob::dispatch($storyPost, $this->instagramAccount);

        // Verify job was queued
        Queue::assertPushed(PostToInstagramJob::class, function ($job) use ($storyPost) {
            return $job->instagramPost->id === $storyPost->id && 
                   $job->instagramPost->is_story === true;
        });
    }

    /** @test */
    public function it_handles_story_vs_regular_post_logic_in_job()
    {
        // Mock the Instagram API service
        $mockService = Mockery::mock(InstagramApiService::class);
        $this->app->instance(InstagramApiService::class, $mockService);

        // Test Story Post
        $storyPost = InstagramPost::create([
            'content_container_id' => $this->contentContainer->id,
            'caption' => 'Story job test',
            'images' => ['https://example.com/story.jpg'],
            'is_story' => true,
            'story_stickers' => [['sticker_type' => 'poll', 'text' => 'Test?']],
            'status' => 'scheduled'
        ]);

        // Mock the postStory method should be called for stories
        $mockService->shouldReceive('postStory')
            ->once()
            ->with(
                Mockery::type('string'), // access token
                Mockery::type('string'), // business account id
                'https://example.com/story.jpg',
                Mockery::type('array') // stickers
            )
            ->andReturn(['id' => 'story_media_123']);

        // Execute the job
        $job = new PostToInstagramJob($storyPost, $this->instagramAccount);
        $job->handle();

        // Verify story was marked as posted
        $storyPost->refresh();
        $this->assertEquals('posted', $storyPost->status);
        $this->assertEquals('story_media_123', $storyPost->instagram_media_id);
    }

    /** @test */
    public function it_demonstrates_complete_stories_workflow()
    {
        Log::info('=== Instagram Stories Feature Demonstration ===');

        // Step 1: Create different types of stories
        $stories = [
            // Simple story
            InstagramPost::create([
                'content_container_id' => $this->contentContainer->id,
                'caption' => 'Simple story without stickers',
                'images' => ['/images/simple-story.jpg'],
                'is_story' => true,
                'story_duration' => 10,
                'order' => 1,
                'status' => 'draft'
            ]),

            // Interactive story with poll
            InstagramPost::create([
                'content_container_id' => $this->contentContainer->id,
                'caption' => 'Interactive story with poll',
                'images' => ['/images/poll-story.jpg'],
                'is_story' => true,
                'story_stickers' => [
                    [
                        'sticker_type' => 'poll',
                        'text' => 'Which feature should we build next?',
                        'options' => ['Auto-DM', 'Analytics'],
                        'position' => ['x' => 0.5, 'y' => 0.7]
                    ]
                ],
                'story_duration' => 15,
                'order' => 2,
                'status' => 'scheduled'
            ]),

            // Story with multiple stickers
            InstagramPost::create([
                'content_container_id' => $this->contentContainer->id,
                'caption' => 'Feature-rich story',
                'images' => ['/images/rich-story.jpg'],
                'is_story' => true,
                'story_stickers' => [
                    [
                        'sticker_type' => 'question',
                        'text' => 'Ask me anything!',
                        'position' => ['x' => 0.5, 'y' => 0.3]
                    ],
                    [
                        'sticker_type' => 'mention',
                        'username' => '@instagram',
                        'position' => ['x' => 0.2, 'y' => 0.8]
                    ],
                    [
                        'sticker_type' => 'hashtag',
                        'text' => '#InstagramStories',
                        'position' => ['x' => 0.8, 'y' => 0.1]
                    ]
                ],
                'story_duration' => 20,
                'order' => 3,
                'status' => 'scheduled'
            ])
        ];

        // Step 2: Verify all stories were created correctly
        $this->assertCount(3, $stories);
        foreach ($stories as $story) {
            $this->assertTrue($story->is_story);
            $this->assertDatabaseHas('instagram_posts', [
                'id' => $story->id,
                'is_story' => true
            ]);
        }

        // Step 3: Demonstrate querying stories vs regular posts
        $allStories = InstagramPost::where('is_story', true)->get();
        $regularPosts = InstagramPost::where('is_story', false)->orWhereNull('is_story')->get();

        Log::info('Stories created:', ['count' => $allStories->count()]);
        Log::info('Regular posts:', ['count' => $regularPosts->count()]);

        // Step 4: Show story-specific features
        $interactiveStory = $stories[1];
        $this->assertIsArray($interactiveStory->story_stickers);
        $this->assertEquals('poll', $interactiveStory->story_stickers[0]['sticker_type']);
        $this->assertEquals(15, $interactiveStory->story_duration);

        Log::info('=== Stories Feature Demo Complete! ===');
        
        return $stories;
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
