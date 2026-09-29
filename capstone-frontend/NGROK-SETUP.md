# ngrok Setup for Frontend Preview

Use ngrok to share the student registration invite link from your local machine without deploying.

## Prerequisites

- [ngrok](https://ngrok.com/) installed ([download](https://ngrok.com/download))
- Frontend running locally

## Quick Start

### 1. Start your app

```bash
# Terminal 1 - Frontend
cd frontend && npm run dev
```

### 2. Start ngrok (expose frontend only)

```bash
# Terminal 3
ngrok http 3001
```

ngrok will show a public URL like:
```
Forwarding    https://abc123.ngrok-free.app -> http://localhost:3001
```

## How it works

- ngrok exposes the frontend (port 3001) for UI preview.
- API-backed features require the separate backend service configured through `VITE_API_URL`.
- The backend team must allow the ngrok frontend origin through CORS.

## Notes

- **Free ngrok:** The URL changes each time you restart ngrok. Update the frontend URL in the backend service's CORS configuration if API requests are needed.
- **ngrok free tier:** You may need to click “Visit Site” on the first load when ngrok shows an interstitial page.
- **API access:** If the separate backend must be reachable from another network, the backend team should expose their API with a separate tunnel and provide that URL for `VITE_API_URL`.
