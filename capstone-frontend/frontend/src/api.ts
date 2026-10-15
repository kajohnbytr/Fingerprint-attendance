import { API_BASE_URL } from './config';

const API_ORIGIN = API_BASE_URL.replace(/\/+$/, '');
const TOKEN_KEY = 'capstone_frontend_token';

export function setApiToken(token: string): void {
  sessionStorage.setItem(TOKEN_KEY, token);
}

export function clearApiToken(): void {
  sessionStorage.removeItem(TOKEN_KEY);
}

export function hasApiToken(): boolean {
  return !!sessionStorage.getItem(TOKEN_KEY);
}

export async function apiFetch(path: string, init: RequestInit = {}): Promise<Response> {
  const headers = new Headers(init.headers);
  headers.set('Accept', 'application/json');

  const token = sessionStorage.getItem(TOKEN_KEY);
  if (token) {
    headers.set('Authorization', `Bearer ${token}`);
  }

  const normalizedPath = path.startsWith('/') ? path : `/${path}`;
  const response = await fetch(`${API_ORIGIN}${normalizedPath}`, {
    ...init,
    headers,
    credentials: 'omit',
  });

  if (response.status === 503) {
    response.clone().json().then(body => {
      if (body?.lockdown === true) window.dispatchEvent(new Event('system-lockdown'));
    }).catch(() => undefined);
  }

  return response;
}
