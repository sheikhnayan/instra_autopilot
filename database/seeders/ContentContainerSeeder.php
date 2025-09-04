<?php

namespace Database\Seeders;

use App\Models\ContentContainer;
use App\Models\InstagramPost;
use Illuminate\Database\Seeder;

class ContentContainerSeeder extends Seeder
{
    public function run(): void
    {
        // Create sample containers
        $containers = [
            [
                'name' => 'jhjh',
                'description' => 'jgjghjh',
                'type' => 'instagram',
                'is_active' => true
            ],
            [
                'name' => 'Promotional Story Post (Bar Night)',
                'description' => 'Bar Night Promo Post',
                'type' => 'instagram',
                'is_active' => true
            ],
            [
                'name' => 'College Student Posts (Account Growth)',
                'description' => 'Account Growth for New Accounts',
                'type' => 'instagram',
                'is_active' => true
            ]
        ];

        foreach ($containers as $containerData) {
            $container = ContentContainer::create($containerData);

            // Create sample posts for each container
            if ($container->name === 'jhjh') {
                InstagramPost::create([
                    'content_container_id' => $container->id,
                    'caption' => 'Sample post content for testing',
                    'images' => ['/images/sample1.jpg'],
                    'hashtags' => ['#test', '#sample'],
                    'post_type' => 'photo',
                    'order' => 1,
                    'status' => 'draft'
                ]);
            } elseif ($container->name === 'Promotional Story Post (Bar Night)') {
                InstagramPost::create([
                    'content_container_id' => $container->id,
                    'caption' => 'Join us every Wednesday for Beer Open Bar! 🍺 4PM - 5PM #BeerNight #OpenBar #Wednesday',
                    'images' => ['/images/beer-promo.jpg'],
                    'hashtags' => ['#BeerNight', '#OpenBar', '#Wednesday', '#Promotion'],
                    'post_type' => 'photo',
                    'order' => 1,
                    'status' => 'draft'
                ]);
            } elseif ($container->name === 'College Student Posts (Account Growth)') {
                for ($i = 1; $i <= 3; $i++) {
                    InstagramPost::create([
                        'content_container_id' => $container->id,
                        'caption' => "College life post #{$i} - Building our community! 📚✨",
                        'images' => ["/images/college-post-{$i}.jpg"],
                        'hashtags' => ['#CollegeLife', '#Students', '#Community', '#Growth'],
                        'post_type' => 'photo',
                        'order' => $i,
                        'status' => 'draft'
                    ]);
                }
            }
        }
    }
}
