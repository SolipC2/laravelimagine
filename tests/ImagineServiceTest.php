<?php

namespace SolipC2\LaravelImagine\Tests;

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Image;
use RuntimeException;
use SolipC2\LaravelImagine\Contracts\VideoClient;
use SolipC2\LaravelImagine\FakeXaiVideoClient;
use SolipC2\LaravelImagine\ImagineResult;
use SolipC2\LaravelImagine\ImagineService;
use SolipC2\LaravelImagine\XaiVideoClient;

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

    public function test_empty_image_prompt_fails(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Image prompt must not be empty');

        (new ImagineService(new FakeXaiVideoClient))->generateImage('   ');
    }

    public function test_image_without_key_and_without_fake_fails_clearly(): void
    {
        config(['ai.providers.xai.key' => null, 'services.xai.key' => null]);
        putenv('XAI_API_KEY');
        $_ENV['XAI_API_KEY'] = '';
        $_SERVER['XAI_API_KEY'] = '';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('XAI_API_KEY');

        (new ImagineService(new FakeXaiVideoClient))->generateImage('should fail');
    }

    public function test_container_resolves_imagine_service_with_fake_video_client(): void
    {
        config(['imagine.fake_video' => true]);

        $service = $this->app->make(ImagineService::class);
        $this->assertInstanceOf(ImagineService::class, $service);

        $client = $this->app->make(VideoClient::class);
        $this->assertInstanceOf(FakeXaiVideoClient::class, $client);

        $result = $service->generateVideo('container fake video');
        $this->assertTrue($result->hasMedia());
        $this->assertTrue($result->meta['fake'] ?? false);
    }

    public function test_live_video_client_requires_api_key(): void
    {
        config(['imagine.fake_video' => false, 'ai.providers.xai.key' => null, 'services.xai.key' => null]);
        putenv('XAI_API_KEY');
        $_ENV['XAI_API_KEY'] = '';
        $_SERVER['XAI_API_KEY'] = '';

        $client = new XaiVideoClient(app(HttpFactory::class), null);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('XAI_API_KEY');

        $client->generate('no key');
    }

    public function test_config_is_merged_under_imagine_key(): void
    {
        $this->assertSame('grok-imagine-video', config('imagine.video_model'));
        $this->assertNotNull(config('imagine.video_base_url'));
        $this->assertArrayHasKey('fake_video', config('imagine'));
    }
}
