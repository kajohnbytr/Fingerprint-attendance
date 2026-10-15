import React, { FormEvent, useEffect, useState } from 'react';
import { ShieldAlert } from 'lucide-react';
import { toast } from 'sonner';
import { apiFetch } from '../../api';
import { Button } from '../ui/button';
import { Input } from '../ui/input';
import { Label } from '../ui/label';
import { Switch } from '../ui/switch';
import { 
  Tabs, 
  TabsContent, 
  TabsList, 
  TabsTrigger 
} from '../ui/tabs';
import { 
  Card, 
  CardContent, 
  CardDescription, 
  CardFooter, 
  CardHeader, 
  CardTitle 
} from '../ui/card';

export function Settings() {
  const [settings, setSettings] = useState({
    schoolName: 'University Attendance Portal', attendanceRequired: 75, proxyDetection: true,
    requireAdminMfa: false, systemLockdown: false, sessionTimeoutMinutes: 30, passwordExpiryDays: 90,
    lowAttendanceAlerts: true, systemHealthReports: true,
  });
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    const controller = new AbortController();
    apiFetch('/api/admin/settings', { signal: controller.signal })
      .then(async response => {
        const body = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(body.message || 'Unable to load school settings.');
        setSettings(body.settings);
      })
      .catch(error => {
        if (error.name !== 'AbortError') toast.error(error.message);
      })
      .finally(() => setLoading(false));
    return () => controller.abort();
  }, []);

  const save = async (event: FormEvent) => {
    event.preventDefault();
    setSaving(true);
    try {
      const response = await apiFetch('/api/admin/settings', {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(settings),
      });
      const body = await response.json().catch(() => ({}));
      if (!response.ok) {
        const firstError = Object.values(body.errors || {}).flat()[0];
        throw new Error(String(firstError || body.message || 'Unable to save school settings.'));
      }
      setSettings(body.settings);
      window.dispatchEvent(new CustomEvent('school-settings-updated', { detail: body.settings }));
      toast.success(body.message || 'School portal settings saved.');
    } catch (error) {
      toast.error(error instanceof Error ? error.message : 'Unable to save school settings.');
    } finally {
      setSaving(false);
    }
  };

  if (loading) return <p className="text-neutral-500">Loading school settings…</p>;

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-neutral-900">System Configuration</h1>
        <p className="text-neutral-500">Manage school attendance, security, and notification policies.</p>
      </div>

      <Tabs defaultValue="general" className="w-full">
        <TabsList className="grid w-full grid-cols-4 lg:w-[400px]">
          <TabsTrigger value="general">General</TabsTrigger>
          <TabsTrigger value="attendance">Attendance</TabsTrigger>
          <TabsTrigger value="security">Security</TabsTrigger>
          <TabsTrigger value="notifications">Notifications</TabsTrigger>
        </TabsList>

        <div className="mt-6">
          {/* General Settings */}
          <TabsContent value="general">
            <form onSubmit={save}><Card>
              <CardHeader>
                <CardTitle>General Settings</CardTitle>
                <CardDescription>
                  Configure the school information shown by the attendance portal.
                </CardDescription>
              </CardHeader>
              <CardContent className="space-y-4">
                <div className="space-y-2">
                  <Label htmlFor="school-name">School Name</Label>
                  <Input id="school-name" value={settings.schoolName} maxLength={150} required onChange={event => setSettings({ ...settings, schoolName: event.target.value })} />
                </div>
              </CardContent>
              <CardFooter>
                <Button className="settings-save-button ml-auto" type="submit" disabled={saving}>{saving ? 'Saving…' : 'Save School Details'}</Button>
              </CardFooter>
            </Card></form>

          </TabsContent>

          {/* Attendance Settings */}
          <TabsContent value="attendance">
            <form onSubmit={save}><Card>
              <CardHeader>
                <CardTitle>Attendance Rules</CardTitle>
                <CardDescription>
                  Set thresholds for attendance marking and alerts.
                </CardDescription>
              </CardHeader>
              <CardContent className="space-y-4">
                <div className="space-y-2">
                  <Label htmlFor="threshold">Attendance Required (%)</Label>
                  <Input id="threshold" type="number" min={1} max={100} value={settings.attendanceRequired} onChange={event => setSettings({ ...settings, attendanceRequired: Number(event.target.value) })} />
                  <p className="text-xs text-neutral-500">Students below this threshold will be flagged.</p>
                </div>
                <div className="flex items-center justify-between">
                  <div className="space-y-0.5">
                    <Label className="text-base">Duplicate Attendance Protection</Label>
                    <p className="text-sm text-neutral-500">
                      Keep one valid fingerprint attendance record per student, subject, and class date.
                    </p>
                  </div>
                  <Switch className="settings-switch" checked={settings.proxyDetection} onCheckedChange={checked => setSettings({ ...settings, proxyDetection: checked })} aria-label="Duplicate attendance detection" />
                </div>
              </CardContent>
              <CardFooter>
                <Button className="settings-save-button ml-auto" type="submit" disabled={saving}>{saving ? 'Saving…' : 'Save Attendance Rules'}</Button>
              </CardFooter>
            </Card></form>
          </TabsContent>

          {/* Security Settings */}
          <TabsContent value="security">
            <form onSubmit={save}><Card>
              <CardHeader>
                <CardTitle>Security & Access</CardTitle>
                <CardDescription>
                  Manage login security and password policies.
                </CardDescription>
              </CardHeader>
              <CardContent className="space-y-4">
                <div className={`settings-lockdown-panel ${settings.systemLockdown ? 'is-active' : ''}`}>
                  <div className="settings-lockdown-icon" aria-hidden="true"><ShieldAlert /></div>
                  <div className="settings-lockdown-content">
                    <div className="settings-lockdown-heading">
                      <Label className="text-base">Emergency System Lockdown</Label>
                      <span className="settings-lockdown-status">{settings.systemLockdown ? 'Lockdown active' : 'System accessible'}</span>
                    </div>
                    <p>
                      Immediately block Program Heads, professors, students, and IoT devices. Super Admin access remains available to restore the system.
                    </p>
                  </div>
                  <Switch className="settings-switch settings-switch--danger" checked={settings.systemLockdown} onCheckedChange={checked => setSettings({ ...settings, systemLockdown: checked })} aria-label="Emergency system lockdown" />
                </div>
                <div className="flex items-center justify-between">
                  <div className="space-y-0.5">
                    <Label className="text-base">Two-Factor Authentication (2FA)</Label>
                    <p className="text-sm text-neutral-500">
                      Require 2FA for all admin accounts.
                    </p>
                  </div>
                  <Switch className="settings-switch" checked={settings.requireAdminMfa} onCheckedChange={checked => setSettings({ ...settings, requireAdminMfa: checked })} aria-label="Two-factor authentication" />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="session-timeout">Inactivity Timeout (Minutes)</Label>
                  <Input id="session-timeout" type="number" min={5} max={1440} value={settings.sessionTimeoutMinutes} onChange={event => setSettings({ ...settings, sessionTimeoutMinutes: Number(event.target.value) })} />
                  <p className="text-xs text-neutral-500">Users receive a two-minute warning before they are automatically signed out. Applies to all authenticated portals.</p>
                </div>
                <div className="space-y-2">
                  <Label htmlFor="password-expiry">Password Expiry (Days)</Label>
                  <Input id="password-expiry" type="number" min={0} max={365} value={settings.passwordExpiryDays} onChange={event => setSettings({ ...settings, passwordExpiryDays: Number(event.target.value) })} />
                  <p className="text-xs text-neutral-500">Use 0 to disable password expiration.</p>
                </div>
              </CardContent>
              <CardFooter>
                <Button className="settings-save-button ml-auto" type="submit" disabled={saving}>{saving ? 'Saving…' : 'Update Security'}</Button>
              </CardFooter>
            </Card></form>
          </TabsContent>

          {/* Notifications */}
          <TabsContent value="notifications">
             <form onSubmit={save}><Card>
              <CardHeader>
                <CardTitle>Notification Preferences</CardTitle>
                <CardDescription>
                  Configure email alerts.
                </CardDescription>
              </CardHeader>
              <CardContent className="space-y-4">
                <div className="flex items-center justify-between">
                  <div className="space-y-0.5">
                    <Label className="text-base">Low Attendance Alerts</Label>
                    <p className="text-sm text-neutral-500">
                      Email faculty when class attendance drops below 50%.
                    </p>
                  </div>
                  <Switch className="settings-switch" checked={settings.lowAttendanceAlerts} onCheckedChange={checked => setSettings({ ...settings, lowAttendanceAlerts: checked })} aria-label="Low attendance alerts" />
                </div>
                <div className="flex items-center justify-between">
                  <div className="space-y-0.5">
                    <Label className="text-base">System Health Reports</Label>
                    <p className="text-sm text-neutral-500">
                      Weekly email summary of system performance.
                    </p>
                  </div>
                  <Switch className="settings-switch" checked={settings.systemHealthReports} onCheckedChange={checked => setSettings({ ...settings, systemHealthReports: checked })} aria-label="System health reports" />
                </div>
              </CardContent>
              <CardFooter>
                <Button className="settings-save-button ml-auto" type="submit" disabled={saving}>{saving ? 'Saving…' : 'Save Preferences'}</Button>
              </CardFooter>
            </Card></form>
          </TabsContent>
        </div>
      </Tabs>
    </div>
  );
}
