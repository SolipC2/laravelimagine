# laravelimagine

Grok Imagine connector demo for **laravelimagine.solipc2.com**.

- **Images**: `laravel/ai` → XAI `grok-imagine-image`
- **Video**: app `XaiVideoClient` → XAI `grok-imagine-video`
- **UI**: chat box on `/` with fake or live mode

```bash
# tests
php artisan test tests/Unit/ImagineServiceTest.php tests/Feature/ChatGenerateTest.php

# local Host
curl -H 'Host: laravelimagine.solipc2.com' http://127.0.0.1/
```

Set `XAI_API_KEY` in Portainer stack env for live generation; default `IMAGINE_FAKE=true` for demos.
