<?php

namespace App\Http\Controllers;

use App\Services\Imagine\Contracts\VideoClient;
use App\Services\Imagine\FakeXaiVideoClient;
use App\Services\Imagine\ImagineService;
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

    public function generate(Request $request, ImagineService $imagine): JsonResponse
    {
        $data = $request->validate([
            'prompt' => ['required', 'string', 'max:4000'],
            'mode' => ['required', 'in:image,video'],
            'duration' => ['nullable', 'integer', 'min:1', 'max:15'],
            'fake' => ['nullable', 'boolean'],
        ]);

        $useFake = $request->boolean('fake') || (bool) config('imagine.fake_video');

        try {
            if ($data['mode'] === 'image' && $useFake) {
                Image::fake([
                    base64_encode('laravelimagine-fake-image-'.$data['prompt']),
                ]);
            }

            if ($data['mode'] === 'video' && $useFake) {
                app()->instance(VideoClient::class, new FakeXaiVideoClient);
                $imagine = new ImagineService(app(VideoClient::class));
            }

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

            return response()->json([
                'ok' => true,
                'result' => $result->toArray(),
            ]);
        } catch (Throwable $e) {
            $message = $e->getMessage();
            $status = str_contains(strtolower($message), 'xai_api_key') ? 422 : 500;

            return response()->json([
                'ok' => false,
                'error' => $message,
            ], $status);
        }
    }
}
