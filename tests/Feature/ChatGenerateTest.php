<?php

namespace Tests\Feature;

use App\Services\Imagine\Contracts\VideoClient;
use App\Services\Imagine\FakeXaiVideoClient;
use App\Services\Imagine\ImagineResult;
use App\Services\Imagine\ImagineService;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Image;
use Tests\TestCase;

class ChatGenerateTest extends TestCase
{
    public function test_home_page_shows_chat_ui(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('chat-form', false);
        $response->assertSee('id="prompt"', false);
        $response->assertSee('Generate', false);
        $response->assertSee('Grok Imagine', false);
    }

    public function test_generate_image_endpoint_uses_service_with_fake(): void
    {
        $response = $this->postJson('/generate', [
            'prompt' => 'blue sphere floating',
            'mode' => 'image',
            'fake' => true,
        ]);

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('result.type', 'image')
            ->assertJsonPath('gateway', 'fake');

        $this->assertNotEmpty($response->json('result.base64'));
        $decoded = base64_decode((string) $response->json('result.base64'), true);
        $this->assertNotFalse($decoded);
        $this->assertStringContainsString('laravelimagine-fake-image', $decoded);
    }

    public function test_generate_video_endpoint_uses_service_with_fake(): void
    {
        $this->app->instance(VideoClient::class, new FakeXaiVideoClient(
            fn (string $prompt) => new ImagineResult(
                type: 'video',
                prompt: $prompt,
                url: 'https://example.test/feature-video.mp4',
                mime: 'video/mp4',
                meta: ['fake' => true],
            )
        ));

        $response = $this->postJson('/generate', [
            'prompt' => 'waves on a black sand beach',
            'mode' => 'video',
            'fake' => true,
        ]);

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('result.type', 'video')
            ->assertJsonPath('gateway', 'fake');

        $this->assertNotEmpty($response->json('result.url'));
    }

    public function test_image_without_key_and_without_fake_returns_honest_error(): void
    {
        config(['ai.providers.xai.key' => null, 'imagine.fake_video' => false]);
        putenv('XAI_API_KEY');
        $_ENV['XAI_API_KEY'] = '';
        $_SERVER['XAI_API_KEY'] = '';

        $response = $this->postJson('/generate', [
            'prompt' => 'should fail honestly',
            'mode' => 'image',
            'fake' => false,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('gateway', 'live');
        $this->assertStringContainsString('XAI_API_KEY', $response->json('error'));
    }

    public function test_live_flag_overrides_imagine_fake_config_for_video_without_key(): void
    {
        // Stack default IMAGINE_FAKE=true must NOT force fake when request says live.
        config([
            'imagine.fake_video' => true,
            'ai.providers.xai.key' => null,
        ]);
        putenv('XAI_API_KEY');
        $_ENV['XAI_API_KEY'] = '';
        $_SERVER['XAI_API_KEY'] = '';

        $response = $this->postJson('/generate', [
            'prompt' => 'must not return example.test',
            'mode' => 'video',
            'fake' => false,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('gateway', 'live');
        $this->assertStringContainsString('XAI_API_KEY', (string) $response->json('error'));
        $this->assertNull($response->json('result'));
        $this->assertStringNotContainsString('example.test', $response->getContent());
    }

    public function test_live_flag_overrides_imagine_fake_config_for_image_without_key(): void
    {
        config([
            'imagine.fake_video' => true,
            'ai.providers.xai.key' => null,
        ]);
        putenv('XAI_API_KEY');
        $_ENV['XAI_API_KEY'] = '';
        $_SERVER['XAI_API_KEY'] = '';

        $response = $this->postJson('/generate', [
            'prompt' => 'must not fake image',
            'mode' => 'image',
            'fake' => false,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('gateway', 'live');
        $this->assertStringContainsString('XAI_API_KEY', (string) $response->json('error'));
        $body = $response->getContent();
        $this->assertStringNotContainsString('laravelimagine-fake-image', $body);
        $this->assertStringNotContainsString('"ok":true', $body);
    }

    public function test_live_video_with_http_fake_uses_real_xai_client_despite_config_fake(): void
    {
        config([
            'imagine.fake_video' => true,
            'ai.providers.xai.key' => 'test-live-key',
        ]);

        Http::fake([
            'api.x.ai/v1/videos/generations' => Http::response(['request_id' => 'req_live_1'], 200),
            'api.x.ai/v1/videos/req_live_1' => Http::response([
                'status' => 'completed',
                'video' => ['url' => 'https://cdn.x.ai/live-out.mp4'],
            ], 200),
        ]);

        $response = $this->postJson('/generate', [
            'prompt' => 'live rocket despite IMAGINE_FAKE',
            'mode' => 'video',
            'fake' => false,
            'duration' => 6,
        ]);

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('gateway', 'live')
            ->assertJsonPath('result.type', 'video')
            ->assertJsonPath('result.url', 'https://cdn.x.ai/live-out.mp4');

        $this->assertNotTrue($response->json('result.meta.fake') ?? false);
        $this->assertStringNotContainsString('example.test', $response->getContent());

        Http::assertSent(fn ($request) => str_contains($request->url(), '/videos/generations')
            && $request['prompt'] === 'live rocket despite IMAGINE_FAKE');
    }

    public function test_service_image_entry_point_with_image_fake(): void
    {
        Image::fake([base64_encode('direct-service')]);
        $service = new ImagineService(new FakeXaiVideoClient);
        $result = $service->generateImage('direct service path');
        $this->assertNotEmpty($result->base64);
        $this->assertTrue($result->hasMedia());
    }
}
