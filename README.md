# solipc2/laravelimagine

**Grok Imagine connector for Laravel** — generate images and videos with xAI Grok Imagine.

This package is **only** a connector (no UI, no demo routes). It integrates via Laravel package auto-discovery.

| Mode  | How |
|-------|-----|
| Image | [`laravel/ai`](https://github.com/laravel/ai) → xAI `grok-imagine-image` |
| Video | Package `XaiVideoClient` → xAI `grok-imagine-video` |

## Requirements

- PHP 8.3+
- Laravel 12 or 13 (Illuminate HTTP/Support/Filesystem)
- [`laravel/ai`](https://github.com/laravel/ai) `^0.9` (requires PHP 8.3+ and Illuminate 12|13)
- An [xAI API key](https://console.x.ai/) for live generation

## Install

```bash
composer require solipc2/laravelimagine
```

Laravel auto-discovers `SolipC2\LaravelImagine\ImagineServiceProvider`. No manual provider registration.

## Configuration

Publish the config (optional):

```bash
php artisan vendor:publish --tag=imagine-config
```

| Env | Purpose | Default |
|-----|---------|---------|
| `XAI_API_KEY` | xAI API key (also used by `laravel/ai`) | — |
| `IMAGINE_FAKE` | Bind fake video client (offline demos/tests) | `false` |
| `XAI_VIDEO_MODEL` | Video model name | `grok-imagine-video` |
| `XAI_BASE_URL` | xAI API base URL | `https://api.x.ai/v1` |
| `IMAGINE_STORE_IMAGES` | Write image binaries to a disk for a URL | `true` |
| `IMAGINE_DISK` | Filesystem disk for images | `public` |

Ensure `laravel/ai` is configured for xAI (typically `XAI_API_KEY` and `config/ai.php` provider `xai`).

## Usage

```php
use SolipC2\LaravelImagine\ImagineService;
use Laravel\Ai\Image;

// Resolve from the container (auto-bound by the service provider)
$imagine = app(ImagineService::class);

// Image (live — requires XAI_API_KEY)
$image = $imagine->generateImage('a red cube on a table');
// $image->base64, $image->url, $image->mime, $image->hasMedia()

// Video (live — requires XAI_API_KEY; uses XaiVideoClient)
$video = $imagine->generateVideo('rocket launch over mars', [
    'duration' => 6,
    'aspect_ratio' => '16:9',
    'resolution' => '480p',
]);
// $video->url, $video->mime

// Unified entry
$result = $imagine->generate('image', 'blue sphere floating');
$result = $imagine->generate('video', 'waves on black sand');
```

### Testing / offline fakes

```php
use Laravel\Ai\Image;
use SolipC2\LaravelImagine\FakeXaiVideoClient;
use SolipC2\LaravelImagine\ImagineService;

// Images: laravel/ai fake gateway
Image::fake([base64_encode('test-bytes')]);
$image = app(ImagineService::class)->generateImage('test prompt');

// Videos: bind fake client or set IMAGINE_FAKE=true
app()->bind(
    \SolipC2\LaravelImagine\Contracts\VideoClient::class,
    fn () => new FakeXaiVideoClient
);
```

### Result shape

```php
$result->type;     // 'image' | 'video'
$result->prompt;
$result->url;      // nullable
$result->base64;   // nullable (images)
$result->mime;
$result->meta;     // array
$result->hasMedia();
$result->toArray();
```

## Scope

This package ships **only** the Grok Imagine connector:

- `ImagineService`, `ImagineResult`
- `XaiVideoClient`, `FakeXaiVideoClient`
- `Contracts\VideoClient`
- Config `imagine` + service provider bindings

It does **not** ship a chat UI, routes, or Docker stack.

## Development

```bash
composer install
composer test
```

## License

MIT — see [LICENSE](LICENSE).
