import React, { useState } from 'react';
import { Lock } from 'lucide-react';
import { apiFetch } from '../api';

export function ForcePasswordChangeForm({ onChanged }: { onChanged: () => void }) {
  const [currentPassword, setCurrentPassword] = useState('');
  const [newPassword, setNewPassword] = useState('');
  const [confirmPassword, setConfirmPassword] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [isSaving, setIsSaving] = useState(false);

  const handleSubmit = async (event: React.FormEvent) => {
    event.preventDefault();
    setError(null);

    if (newPassword !== confirmPassword) {
      setError('The new password and confirmation do not match.');
      return;
    }

    setIsSaving(true);
    try {
      const response = await apiFetch('/api/auth/password', {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ currentPassword, newPassword, confirmPassword }),
      });
      const data = await response.json();

      if (!response.ok) {
        const firstValidationError = Object.values(data.errors ?? {}).flat()[0];
        throw new Error(typeof firstValidationError === 'string' ? firstValidationError : data.message ?? 'Password change failed.');
      }

      onChanged();
    } catch (caughtError) {
      setError(caughtError instanceof Error ? caughtError.message : 'Password change failed.');
    } finally {
      setIsSaving(false);
    }
  };

  return (
    <div className="min-h-screen bg-neutral-100 flex items-center justify-center p-4">
      <div className="w-full max-w-md rounded-2xl border border-neutral-200 bg-white p-8 shadow-sm">
        <div className="mb-6 text-center">
          <div className="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-neutral-900">
            <Lock className="h-5 w-5 text-white" />
          </div>
          <h1 className="text-2xl font-semibold text-neutral-900">Change your temporary password</h1>
          <p className="mt-2 text-sm text-neutral-500">
            Before opening the professor portal, replace the default password with one only you know.
          </p>
        </div>

        <form className="space-y-4" onSubmit={handleSubmit}>
          <PasswordField label="Current password" value={currentPassword} onChange={setCurrentPassword} />
          <PasswordField label="New password" value={newPassword} onChange={setNewPassword} minLength={8} />
          <PasswordField label="Confirm new password" value={confirmPassword} onChange={setConfirmPassword} minLength={8} />

          {error && <p className="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-600">{error}</p>}

          <button
            type="submit"
            disabled={isSaving}
            className="w-full rounded-lg bg-neutral-900 py-3 font-medium text-white hover:bg-neutral-800 disabled:cursor-not-allowed disabled:opacity-50"
          >
            {isSaving ? 'Changing password...' : 'Change password and continue'}
          </button>
        </form>
      </div>
    </div>
  );
}

function PasswordField({
  label,
  value,
  onChange,
  minLength,
}: {
  label: string;
  value: string;
  onChange: (value: string) => void;
  minLength?: number;
}) {
  return (
    <label className="block text-sm font-medium text-neutral-700">
      {label}
      <input
        type="password"
        required
        minLength={minLength}
        value={value}
        onChange={(event) => onChange(event.target.value)}
        className="mt-2 w-full rounded-lg border border-neutral-300 px-4 py-3 outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10"
      />
    </label>
  );
}
