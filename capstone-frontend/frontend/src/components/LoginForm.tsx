import React, { useState } from 'react';
import { Lock, Mail, Eye, EyeOff, RefreshCw } from 'lucide-react';
import type { AuthenticatedUser, UserRole } from '../types/rbac';

import { apiFetch, setApiToken } from '../api';

async function safeJson(response: Response): Promise<any> {
  const text = await response.text();
  if (!text) {
    throw new Error('API did not respond. Please try again later.');
  }
  try {
    return JSON.parse(text);
  } catch {
    throw new Error(
      response.ok
        ? 'Invalid response from server.'
        : `API error (${response.status}). Please try again later.`
    );
  }
}

export function LoginForm({
  onLogin,
  onForgot,
  forcedRole,
  showRoleSelector = false,
  title = 'Portal Login',
  subtitle = 'Enter your credentials to continue',
}: {
  onLogin?: (user: AuthenticatedUser) => void;
  onForgot?: () => void;
  forcedRole?: UserRole;
  showRoleSelector?: boolean;
  title?: string;
  subtitle?: string;
}) {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [captcha, setCaptcha] = useState(() => createCaptcha());
  const [captchaAnswer, setCaptchaAnswer] = useState('');
  const [captchaTouched, setCaptchaTouched] = useState(false);

  const isStudentLogin = forcedRole === 'student';
  const captchaHasValue = captchaAnswer.trim().length > 0;
  const captchaIsCorrect = captchaHasValue && Number(captchaAnswer) === captcha.answer;

  const getEmailPlaceholder = () => {
    switch (forcedRole) {
      case 'student':
        return 'student@gmail.com';
      case 'admin':
        return 'ADMIN ID';
      case 'super_admin':
        return 'This is a prohibited area';
      default:
        return 'email@example.com';
    }
  };

  const handleSubmit = async (e: any) => {
    e.preventDefault();
    setError(null);

    if (isStudentLogin) {
      setCaptchaTouched(true);
      if (!captchaAnswer.trim()) {
        setError('Required field cannot be left blank');
        return;
      }

      if (Number(captchaAnswer) !== captcha.answer) {
        setError('Please fill correct value');
        return;
      }
    }

    setIsLoading(true);

    try {
      const requestBody: { email: string; password: string; expectedRole?: string } = {
        email,
        password,
      };

      if (forcedRole) {
        requestBody.expectedRole = forcedRole;
      }

      const response = await apiFetch('/api/auth/login', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(requestBody),
      });

      const data = await safeJson(response);

      if (!response.ok) {
        throw new Error(data.error || 'Login failed');
      }

      if (data.user && onLogin) {
        if (typeof data.token !== 'string' || !data.token) {
          throw new Error('The API did not issue an access token.');
        }
        setApiToken(data.token);
        onLogin(data.user as AuthenticatedUser);
      }
    } catch (err) {
      let message = 'An error occurred during login';
      if (err instanceof Error) {
        message =
          err.message === 'Failed to fetch'
          ? 'Cannot reach the API. Check that the Laravel service is running and try again.'
            : err.message;
      }
      setError(message);
      setIsLoading(false);
    }
  };

  return (
    <div className="w-full max-w-md">
      <div className="bg-white rounded-2xl shadow-sm border border-neutral-200 p-8">
        {/* Header */}
        <div className="mb-8 text-center">
          <div className="inline-flex items-center justify-center w-12 h-12 rounded-full bg-neutral-900 mb-4">
            <Lock className="w-5 h-5 text-white" />
          </div>
          <h1 className="text-2xl font-semibold text-neutral-900 mb-2">{title}</h1>
          <p className="text-neutral-500">{subtitle}</p>
        </div>

        {/* Form */}
        <form onSubmit={handleSubmit} className="space-y-5">
          {/* Email Field */}
          <div>
            <label htmlFor="email" className="block text-sm font-medium text-neutral-700 mb-2">
              Email Address
            </label>
            <div className="relative">
              <Mail className="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-neutral-400" />
              <input
                id="email"
                type="email"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                placeholder={getEmailPlaceholder()}
                required
                className="w-full pl-11 pr-4 py-3 rounded-lg border border-neutral-300 focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 outline-none transition-all text-neutral-900 placeholder:text-neutral-400"
              />
            </div>
          </div>

          {/* Password Field */}
          <div>
            <label htmlFor="password" className="block text-sm font-medium text-neutral-700 mb-2">
              Password
            </label>
            <div className="relative">
              <input
                id="password"
                type={showPassword ? 'text' : 'password'}
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                placeholder="••••••••"
                required
                className="w-full pl-11 pr-4 py-3 rounded-lg border border-neutral-300 focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 outline-none transition-all text-neutral-900 placeholder:text-neutral-400"
              />
              <button
                type="button"
                onClick={() => setShowPassword((prev) => !prev)}
                className="absolute left-3 top-1/2 -translate-y-1/2 text-neutral-400 hover:text-neutral-700"
                aria-label={showPassword ? 'Hide password' : 'Show password'}
              >
                {showPassword ? <EyeOff className="w-4 h-4" /> : <Eye className="w-4 h-4" />}
              </button>
            </div>
          </div>

          {isStudentLogin && (
            <>
              {/* CAPTCHA */}
              <div>
                <div className="flex items-center gap-2">
                   <span className="flex h-14 w-20 rounded-md border border-neutral-300 bg-white px-4 py-2 text-lg font-semibold text-neutral-500">
                    {captcha.left}
                  </span>
                  <span className="text-neutral-500">+</span>
                  <span className="flex h-14 w-20 rounded-md border border-neutral-300 bg-white px-4 py-2 text-lg font-semibold text-neutral-500">
                    {captcha.right}
                  </span>
                  <span className="text-neutral-500">=</span>
                  <input
                    id="captcha"
                    type="text"
                    inputMode="numeric"
                    value={captchaAnswer}
                    onChange={(e) => {
                      setCaptchaAnswer(e.target.value.replace(/\D/g, ''));
                      setCaptchaTouched(true);
                    }}
                    onBlur={() => setCaptchaTouched(true)}
                    required
                    placeholder=""
                    aria-describedby="captcha-message"
                    className={`rounded-md border px-2 py-2 text-center text-lg font-semibold outline-none transition-all focus:outline-none focus:ring-0 ${
                      captchaTouched && !captchaAnswer.trim()
                        ? 'border-red-500 text-red-500 focus:ring-2 focus:ring-red-500/10'
                        : captchaHasValue && !captchaIsCorrect
                          ? 'border-red-500 text-red-500 focus:ring-2 focus:ring-red-500/10'
                          : captchaIsCorrect
                            ? 'border-green-500 text-green-600 focus:ring-2 focus:ring-green-500/10'
                            : 'border-neutral-300 text-neutral-900 focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10'
                    }`}
                    style={{
                      width: '6rem',
                      minWidth: '6rem',
                      maxWidth: '6rem',
                      flex: '0 0 6rem',
                      ...(captchaIsCorrect
                        ? { borderColor: '#22c55e', color: '#16a34a', outline: 'none' }
                        : captchaHasValue || captchaTouched
                          ? { borderColor: '#ef4444', color: '#ef4444', outline: 'none' }
                          : {}),
                    }}
                  />
                  <button
                    type="button"
                    onClick={() => {
                      setCaptcha(createCaptcha());
                      setCaptchaAnswer('');
                      setCaptchaTouched(false);
                      setError(null);
                    }}
                    className="rounded-lg px-3 text-neutral-600 transition-colors hover:bg-neutral-50 hover:text-neutral-900"
                    aria-label="Refresh CAPTCHA"
                    title="Refresh CAPTCHA"
                  >
                    <RefreshCw className="h-10 w-10" />
                  </button>
                </div>
                {captchaTouched && !captchaAnswer.trim() && (
                  <p id="captcha-message" className="mt-1 text-sm text-red-500">
                    Required field cannot be left blank
                  </p>
                )}
                {captchaTouched && captchaAnswer.trim() && Number(captchaAnswer) !== captcha.answer && (
                  <p id="captcha-message" className="mt-1 text-sm text-red-500">
                    Please fill correct value
                  </p>
                )}
              </div>

            </>
          )}

          {/* Error Message */}
          {error && (
            <div className="rounded-lg bg-red-50 border border-red-200 p-3">
              <p className="text-sm text-red-600">{error}</p>
            </div>
          )}

          {/* Remember Me & Forgot Password */}
          <div className="flex items-center justify-between">
            <label className="flex items-center cursor-pointer group">
              <input
                type="checkbox"
                className="w-4 h-4 rounded border-neutral-300 text-neutral-900 focus:ring-2 focus:ring-neutral-900/10 cursor-pointer"
              />
              <span className="ml-2 text-sm text-neutral-600 group-hover:text-neutral-900 transition-colors">
                Remember me
              </span>
            </label>
            <button
              type="button"
              onClick={onForgot}
              className="text-sm text-neutral-600 hover:text-neutral-900 transition-colors"
            >
              Forgot password?
            </button>
          </div>

          {/* Submit Button */}
          <button
            type="submit"
            disabled={isLoading}
            className="w-full py-3 rounded-lg bg-neutral-900 text-white font-medium hover:bg-neutral-800 focus:ring-4 focus:ring-neutral-900/20 transition-all disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {isLoading ? (
              <span className="flex items-center justify-center">
                <svg className="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                  <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                  <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Signing in...
              </span>
            ) : (
              'Sign In'
            )}
          </button>
        </form>

        {/* Footer */}
        <div className="mt-6 pt-6 border-t border-neutral-200">
          <p className="text-center text-sm text-neutral-500">
            Authorized access only. Your role controls available features.
          </p>
        </div>
      </div>
    </div>
  );
}

function createCaptcha() {
  const left = Math.floor(Math.random() * 8) + 2;
  const right = Math.floor(Math.random() * 8) + 1;
  return { left, right, answer: left + right };
}
