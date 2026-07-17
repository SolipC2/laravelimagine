<?php

namespace Tests\Feature;

use App\Services\Imagine\Contracts\VideoClient;
use App\Services\Imagine\FakeXaiVideoClient;
use App\Services\Imagine\ImagineResult;
use App\Services\Imagine\ImagineService;
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
            ->assertJsonPath('result.type', 'image');

        $this->assertNotEmpty($response->json('result.base64'));
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
            ->assertJsonPath('result.type', 'video');

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
            ->assertJsonPath('ok', false);
        $this->assertStringContainsString('XAI_API_KEY', $response->json('error'));
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
