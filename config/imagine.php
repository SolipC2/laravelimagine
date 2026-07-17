<?php

return [
    /*
    | When true, video generation uses FakeXaiVideoClient regardless of API key.
    | Tests and local demos set this (or IMAGINE_FAKE=true).
    */
    'fake_video' => (bool) env('IMAGINE_FAKE', env('APP_ENV') === 'testing'),

    'video_model' => env('XAI_VIDEO_MODEL', 'grok-imagine-video'),
    'video_base_url' => env('XAI_BASE_URL', 'https://api.x.ai/v1'),
];
