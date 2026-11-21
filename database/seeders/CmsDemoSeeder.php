<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CmsDemoSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        /*
        |--------------------------------------------------------------------------
        | 1. CMS Pages
        |--------------------------------------------------------------------------
        */

        $pages = [
            [
                'title' => 'About Us',
                'slug' => 'about-us',
                'body' => 'We create memorable travel experiences with carefully planned tours and exceptional service.',
                'status' => 'published',
                'is_homepage' => 0,
                'meta_title' => 'About Us',
                'meta_description' => 'Learn more about our travel company and services.',
                'sort_order' => 1,
            ],
            [
                'title' => 'Contact Us',
                'slug' => 'contact-us',
                'body' => 'Get in touch with our team for tour enquiries and assistance.',
                'status' => 'published',
                'is_homepage' => 0,
                'meta_title' => 'Contact Us',
                'meta_description' => 'Contact our team for travel enquiries.',
                'sort_order' => 2,
            ],
            [
                'title' => 'Privacy Policy',
                'slug' => 'privacy-policy',
                'body' => 'This privacy policy explains how we handle customer information.',
                'status' => 'draft',
                'is_homepage' => 0,
                'meta_title' => 'Privacy Policy',
                'meta_description' => 'Read our privacy policy.',
                'sort_order' => 3,
            ],
        ];

        foreach ($pages as $page) {
            DB::table('cms_pages')->updateOrInsert(
                ['slug' => $page['slug']],
                array_merge($page, [
                    'updated_at' => $now,
                ], DB::table('cms_pages')
                    ->where('slug', $page['slug'])
                    ->exists() ? [] : [
                        'created_at' => $now,
                        'published_at' => $page['status'] === 'published' ? $now : null,
                    ])
            );
        }

        /*
        |--------------------------------------------------------------------------
        | 2. Homepage Settings
        |--------------------------------------------------------------------------
        */

        $homepageData = [
            'hero_title' => 'Discover Your Next Adventure',
            'hero_subtitle' => 'Explore amazing destinations and create unforgettable memories with us.',
            'hero_button_text' => 'Explore Tours',
            'hero_button_url' => '/tours',
            'featured_tour_ids' => json_encode([]),
            'featured_section_title' => 'Popular Tours',
            'featured_section_description' => 'Explore our handpicked travel experiences.',
            'trust_section_title' => 'Why Travel With Us?',
            'trust_section_description' => 'Enjoy reliable service and memorable travel experiences.',
            'primary_cta_title' => 'Ready for Your Next Journey?',
            'primary_cta_button_text' => 'Book Now',
            'primary_cta_button_url' => '/tours',
            'secondary_cta_title' => 'Need Assistance?',
            'secondary_cta_button_text' => 'Contact Us',
            'secondary_cta_button_url' => '/contact-us',
            'updated_at' => $now,
        ];

        $homepage = DB::table('cms_homepage_settings')
            ->orderBy('id')
            ->first();

        if ($homepage) {
            DB::table('cms_homepage_settings')
                ->where('id', $homepage->id)
                ->update($homepageData);
        } else {
            $homepageData['created_at'] = $now;

            DB::table('cms_homepage_settings')->insert($homepageData);
        }

        /*
        |--------------------------------------------------------------------------
        | 3. Testimonials
        |--------------------------------------------------------------------------
        */

        $testimonials = [
            [
                'customer_name' => 'Arun Kumar',
                'designation' => 'Business Owner',
                'location' => 'Kerala',
                'testimonial' => 'Excellent travel experience. Everything was organised perfectly.',
                'rating' => 5,
                'is_published' => 1,
                'sort_order' => 1,
            ],
            [
                'customer_name' => 'Meera S',
                'designation' => 'Teacher',
                'location' => 'Trivandrum',
                'testimonial' => 'The team was helpful and our trip was memorable.',
                'rating' => 5,
                'is_published' => 1,
                'sort_order' => 2,
            ],
            [
                'customer_name' => 'Rahul M',
                'designation' => 'Engineer',
                'location' => 'Kochi',
                'testimonial' => 'Good planning and excellent customer support.',
                'rating' => 4,
                'is_published' => 0,
                'sort_order' => 3,
            ],
        ];

        foreach ($testimonials as $testimonial) {
            DB::table('cms_testimonials')->updateOrInsert(
                [
                    'customer_name' => $testimonial['customer_name'],
                    'testimonial' => $testimonial['testimonial'],
                ],
                array_merge($testimonial, [
                    'updated_at' => $now,
                ], DB::table('cms_testimonials')
                    ->where('customer_name', $testimonial['customer_name'])
                    ->where('testimonial', $testimonial['testimonial'])
                    ->exists() ? [] : ['created_at' => $now])
            );
        }

        /*
        |--------------------------------------------------------------------------
        | 4. Trust Items
        |--------------------------------------------------------------------------
        */

        $trustItems = [
            [
                'title' => 'Trusted Travel Experts',
                'description' => 'Get assistance from experienced travel professionals.',
                'icon' => 'fa-solid fa-user-shield',
                'is_published' => 1,
                'sort_order' => 1,
            ],
            [
                'title' => 'Best Travel Experiences',
                'description' => 'Enjoy carefully planned trips and memorable destinations.',
                'icon' => 'fa-solid fa-globe',
                'is_published' => 1,
                'sort_order' => 2,
            ],
            [
                'title' => 'Customer Support',
                'description' => 'Our team is available to help you throughout your journey.',
                'icon' => 'fa-solid fa-headset',
                'is_published' => 1,
                'sort_order' => 3,
            ],
        ];

        foreach ($trustItems as $item) {
            DB::table('cms_trust_items')->updateOrInsert(
                ['title' => $item['title']],
                array_merge($item, [
                    'updated_at' => $now,
                ], DB::table('cms_trust_items')
                    ->where('title', $item['title'])
                    ->exists() ? [] : ['created_at' => $now])
            );
        }

        /*
        |--------------------------------------------------------------------------
        | 5. Menus
        |--------------------------------------------------------------------------
        */

        $menus = [
            [
                'name' => 'Main Header Menu',
                'slug' => 'main-header',
                'location' => 'header',
                'is_active' => 1,
            ],
            [
                'name' => 'Footer Menu',
                'slug' => 'footer-links',
                'location' => 'footer',
                'is_active' => 1,
            ],
        ];

        foreach ($menus as $menuData) {
            DB::table('cms_menus')->updateOrInsert(
                ['slug' => $menuData['slug']],
                array_merge($menuData, [
                    'updated_at' => $now,
                ], DB::table('cms_menus')
                    ->where('slug', $menuData['slug'])
                    ->exists() ? [] : ['created_at' => $now])
            );
        }

        /*
        |--------------------------------------------------------------------------
        | 6. Menu Items
        |--------------------------------------------------------------------------
        */

        $headerMenu = DB::table('cms_menus')
            ->where('slug', 'main-header')
            ->first();

        $footerMenu = DB::table('cms_menus')
            ->where('slug', 'footer-links')
            ->first();

        $menuItems = [
            [$headerMenu->id, 'Home', '/', 1],
            [$headerMenu->id, 'About Us', '/about-us', 2],
            [$headerMenu->id, 'Tours', '/tours', 3],
            [$headerMenu->id, 'Contact Us', '/contact-us', 4],

            [$footerMenu->id, 'About Us', '/about-us', 1],
            [$footerMenu->id, 'Privacy Policy', '/privacy-policy', 2],
            [$footerMenu->id, 'Contact Us', '/contact-us', 3],
        ];

        foreach ($menuItems as [$menuId, $label, $url, $order]) {
            DB::table('cms_menu_items')->updateOrInsert(
                [
                    'menu_id' => $menuId,
                    'label' => $label,
                ],
                [
                    'url' => $url,
                    'parent_id' => null,
                    'target' => '_self',
                    'is_active' => 1,
                    'sort_order' => $order,
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
        }

        /*
        |--------------------------------------------------------------------------
        | 7. Site Settings
        |--------------------------------------------------------------------------
        */

        // 7. Site Settings
        $settings = [
            'site_name' => 'LuxyBiz',
            'site_tagline' => 'Explore the World with Us',
            'contact_email' => 'info@example.com',
            'contact_phone' => '+91 9876543210',
            'contact_address' => 'Trivandrum, Kerala, India',
            'footer_description' => 'Discover memorable travel experiences with LuxyBiz.',
            'copyright_text' => '© ' . date('Y') . ' LuxyBiz. All rights reserved.',
            'social_links' => [
                'facebook' => '',
                'instagram' => '',
                'youtube' => '',
            ],
            'footer_settings' => [],
        ];

        foreach ($settings as $key => $value) {
            DB::table('cms_site_settings')->updateOrInsert(
                ['key' => $key],
                [
                    'value' => json_encode($value, JSON_UNESCAPED_UNICODE),
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
        }
        $this->command->info('CMS demo data seeded successfully.');
    }
}
