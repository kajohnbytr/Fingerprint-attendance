/**
 * URL of the API service maintained in the separate backend repository.
 * Set VITE_API_URL in the frontend environment without a trailing slash.
 */
export const API_BASE_URL = import.meta.env.VITE_API_URL || 'http://localhost:8000';

/**
 * Base URL used for generated frontend links such as student invite URLs.
 * Prefer an explicit VITE_FRONTEND_URL, otherwise use the current browser origin.
 */
export const FRONTEND_BASE_URL =
  import.meta.env.VITE_FRONTEND_URL ??
  (typeof window !== 'undefined' ? window.location.origin : 'http://localhost:8080');
