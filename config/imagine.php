<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Fake video generation
    |--------------------------------------------------------------------------
    |
    | When true, the package binds FakeXaiVideoClient instead of the live
    | xAI video client. Useful for tests and local demos without an API key.
    | Images still use laravel/ai's Image::fake() when you call it in tests.
    |
    */
    'fake_video' => (bool) env('IMAGINE_FAKE', false),

    /*
    |--------------------------------------------------------------------------
    | Video model & API base
    |--------------------------------------------------------------------------
    */
    'video_model' => env('XAI_VIDEO_MODEL', 'grok-imagine-video'),
    'video_base_url' => env('XAI_BASE_URL', 'https://api.x.ai/v1'),

    /*
    |--------------------------------------------------------------------------
    | Image storage (optional)
    |--------------------------------------------------------------------------
    |
    | After image generation, binary data may be written to a disk so a public
    | URL is available alongside base64. Disable store_images to skip writes.
    |
    */
    'store_images' => (bool) env('IMAGINE_STORE_IMAGES', true),
    'disk' => env('IMAGINE_DISK', 'public'),
    'image_path' => env('IMAGINE_IMAGE_PATH', 'imagine'),
];
