<?php

namespace Tests\Unit;

use App\Services\Imagine\Contracts\VideoClient;
use App\Services\Imagine\FakeXaiVideoClient;
use App\Services\Imagine\ImagineResult;
use App\Services\Imagine\ImagineService;
use App\Services\Imagine\XaiVideoClient;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Image;
use Tests\TestCase;

class ImagineServiceTest extends TestCase
{
    public function test_generate_image_uses_laravel_ai_entry_point_with_fake_gateway(): void
    {
        $payload = base64_encode('unit-test-image-bytes');
        Image::fake([$payload]);

        $service = new ImagineService(new FakeXaiVideoClient);
        $result = $service->generateImage('a red cube on a table');

        $this->assertSame('image', $result->type);
        $this->assertSame('a red cube on a table', $result->prompt);
        $this->assertNotEmpty($result->base64);
        $this->assertSame($payload, $result->base64);
        $this->assertTrue($result->hasMedia());
        $this->assertSame('laravel/ai', $result->meta['via'] ?? null);

        Image::assertGenerated(fn ($prompt) => str_contains($prompt->prompt ?? (string) $prompt, 'red cube')
            || (is_object($prompt) && str_contains($prompt->prompt, 'red cube')));
    }

    public function test_generate_video_uses_fake_client_entry_point(): void
    {
        $client = new FakeXaiVideoClient(function (string $prompt, array $options) {
            return new ImagineResult(
                type: 'video',
                prompt: $prompt,
                url: 'https://example.test/videos/from-fake.mp4',
                mime: 'video/mp4',
                meta: ['fake' => true, 'duration' => $options['duration'] ?? 6],
            );
        });

        $service = new ImagineService($client);
        $result = $service->generateVideo('rocket launch over mars', ['duration' => 6]);

        $this->assertSame('video', $result->type);
        $this->assertNotEmpty($result->url);
        $this->assertStringContainsString('from-fake.mp4', $result->url);
        $this->assertTrue($result->hasMedia());
    }

    public function test_generate_video_live_client_posts_to_xai_with_http_fake(): void
    {
        Http::fake([
            'api.x.ai/v1/videos/generations' => Http::response([
                'request_id' => 'req_test_1',
            ], 200),
            'api.x.ai/v1/videos/req_test_1' => Http::response([
                'status' => 'completed',
                'video' => ['url' => 'https://cdn.example.test/out.mp4'],
            ], 200),
        ]);

        $client = new XaiVideoClient(
            app(HttpFactory::class),
            'test-key-not-real',
            'https://api.x.ai/v1',
            'grok-imagine-video',
            10,
        );

        // Reduce sleep: override by making first poll succeed immediately
        $result = $client->generate('glowing rocket', ['duration' => 6]);

        $this->assertSame('video', $result->type);
        $this->assertSame('https://cdn.example.test/out.mp4', $result->url);
        $this->assertTrue($result->hasMedia());

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/videos/generations')
                && $request['model'] === 'grok-imagine-video'
                && $request['prompt'] === 'glowing rocket';
        });
    }

    public function test_generate_dispatches_by_mode(): void
    {
        Image::fake([base64_encode('mode-image')]);
        $service = new ImagineService(new FakeXaiVideoClient([
            'https://example.test/mode-video.mp4',
        ]));

        $img = $service->generate('image', 'mode test image');
        $vid = $service->generate('video', 'mode test video');

        $this->assertSame('image', $img->type);
        $this->assertNotEmpty($img->base64);
        $this->assertSame('video', $vid->type);
        $this->assertNotEmpty($vid->url);
    }
}
