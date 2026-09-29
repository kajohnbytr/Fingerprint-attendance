
 # CITE Admin Panel

 A modern admin dashboard for CITE attendance monitoring with a minimalist login/forgot-password flow and responsive layout.

 ## Features
 - Admin login and forgot-password screens
 - Dashboard overview with charts and alerts
 - Reports table with CSV export
 - User management, settings, and audit logs sections
 - Responsive sidebar and layout

 ## Tech Stack
 - React + TypeScript (Vite)
 - Tailwind CSS + shadcn/ui components
 - Recharts for data visualizations
 - Lucide icons

## Getting Started
1. Install dependencies:
   ```bash
   cd frontend
   npm install
   ```
2. Start the dev server:
   ```bash
   npm run dev
   ```

## Scripts (Frontend)
- `npm run dev` - start development server
- `npm run build` - build for production
- `npm run preview` - preview the production build

## Laravel API connection

The Laravel backend and database are at the repository root. The frontend sends
requests to the backend; it never connects directly to MySQL.

Create `frontend/.env` from `frontend/.env.example` and set the API origin:

```env
VITE_API_URL=http://localhost:8000
```

For production, replace it with the deployed Laravel API origin, for example:

```env
VITE_API_URL=https://api.example.com
```

The API base defaults to `http://localhost:8000`. The Laravel backend enables
credentialed CORS and Sanctum session authentication for the local frontend ports.
For a deployed frontend, set `CORS_ALLOWED_ORIGINS` and
`SANCTUM_STATEFUL_DOMAINS` in the backend environment to the frontend's actual
origin and host. Keep `VITE_API_URL` pointed at the Laravel origin.

The connected React API workflows are login/session, admin overview, and student
profile, courses, and attendance. Run `php artisan migrate` from the repository
root before using the attendance-email feature.

## UI modules not yet backed by the Laravel API

Invite registration, archive requests, app reports, audit logs, schedule-change
requests, and several admin management screens are still UI-only. Their requests
are intentionally not represented as working backend features.

## IoT / Fingerprint Attendance
- The ESP8266 uses the Sanctum device token to call `POST /api/attendance`.
- The backend only records a scan when exactly one enrolled subject is active for
  the student at the server's current time, then emails the PHINMAED address saved
  on that student's record.
- Enter the student's PHINMAED address in the **Register Student** form on the
  ESP8266 captive portal. The device syncs it to the Laravel student record.
  Entering a student number already saved on that device updates their name and
  email without enrolling another fingerprint, which can also update older
  device records. SMTP settings must be configured in the Laravel environment
  for live delivery; the default local `log` mailer does not deliver external
  email.
- The firmware enters deep sleep after 5 minutes without a scan or portal client
  and wakes after 30 seconds. Deep sleep disables scanning and the captive portal;
  connect ESP8266 D0/GPIO16 to RST for timer wake. The fingerprint module remains
  powered, so a switched sensor supply is needed for maximum battery savings.
- To start fresh on the device, use **Fingerprints → Clear All Device Data** in
  the authenticated captive portal and confirm the warning. This clears sensor
  templates and local EEPROM student/enrollment logs only; Laravel student rows,
  fingerprint links, and attendance history remain unchanged.

## Notes
- The backend and database are intentionally not part of this repository.
- Set `VITE_API_URL` in the frontend environment; never put database credentials in it.
