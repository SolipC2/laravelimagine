<?php

namespace App\Http\Controllers;

use App\Services\Imagine\Contracts\VideoClient;
use App\Services\Imagine\FakeXaiVideoClient;
use App\Services\Imagine\ImagineService;
use App\Services\Imagine\XaiVideoClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Laravel\Ai\Image;
use Throwable;

class ChatController extends Controller
{
    public function index(): View
    {
        return view('chat', [
            'hasKey' => filled(config('ai.providers.xai.key') ?? env('XAI_API_KEY')),
            'fakeVideo' => (bool) config('imagine.fake_video'),
        ]);
    }

    public function generate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'prompt' => ['required', 'string', 'max:4000'],
            'mode' => ['required', 'in:image,video'],
            'duration' => ['nullable', 'integer', 'min:1', 'max:15'],
            'fake' => ['nullable', 'boolean'],
        ]);

        // Explicit request "fake" wins over IMAGINE_FAKE / config default.
        $useFake = $request->exists('fake')
            ? $request->boolean('fake')
            : (bool) config('imagine.fake_video');

        try {
            if ($data['mode'] === 'image' && $useFake) {
                Image::fake([
                    base64_encode('laravelimagine-fake-image-'.$data['prompt']),
                ]);
            }

            $videoClient = $useFake
                ? new FakeXaiVideoClient
                : $this->makeLiveVideoClient();

            // Always build a fresh service so Live is never stuck on a fake singleton.
            $imagine = new ImagineService($videoClient);

            $options = array_filter([
                'duration' => $data['duration'] ?? 6,
            ]);

            $result = $imagine->generate($data['mode'], $data['prompt'], $options);

            if (! $result->hasMedia()) {
                return response()->json([
                    'ok' => false,
                    'error' => 'Generation completed without media payload.',
                ], 500);
            }

            // Guard: Live must never return demo/fake media markers.
            if (! $useFake && ($result->meta['fake'] ?? false) === true) {
                return response()->json([
                    'ok' => false,
                    'error' => 'Live mode produced fake media; refusing to return it.',
                ], 500);
            }

            return response()->json([
                'ok' => true,
                'result' => $result->toArray(),
                'gateway' => $useFake ? 'fake' : 'live',
            ]);
        } catch (Throwable $e) {
            $message = $e->getMessage();
            $status = str_contains(strtolower($message), 'xai_api_key') ? 422 : 500;

            return response()->json([
                'ok' => false,
                'error' => $message,
                'gateway' => $useFake ? 'fake' : 'live',
            ], $status);
        }
    }

    protected function makeLiveVideoClient(): VideoClient
    {
        return new XaiVideoClient(
            app(\Illuminate\Http\Client\Factory::class),
            config('ai.providers.xai.key') ?? env('XAI_API_KEY'),
            rtrim((string) config('imagine.video_base_url', 'https://api.x.ai/v1'), '/'),
            (string) config('imagine.video_model', 'grok-imagine-video'),
        );
    }
}
