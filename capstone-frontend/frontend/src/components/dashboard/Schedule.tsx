import React, { useCallback, useEffect, useState } from 'react';
import { CalendarDays, Clock, MapPin, Users } from 'lucide-react';
import { apiFetch } from '../../api';
import { Card, CardContent, CardHeader, CardTitle } from '../ui/card';
import { Input } from '../ui/input';
import type { UserRole } from '../../types/rbac';

type ScheduledClass = {
  id: string;
  subjectId: number;
  blockId: number;
  subjectCode: string;
  subjectName: string;
  block: string;
  studentCount: number;
  schedule: string;
  room: string;
  graceMinutes: number;
};

type ScheduleResponse = {
  teacherName: string;
  date: string;
  day: string;
  classes: ScheduledClass[];
};

export function Schedule({ role: _role }: { role: UserRole }) {
  const [schedule, setSchedule] = useState<ScheduleResponse | null>(null);
  const [graceMinutes, setGraceMinutes] = useState<Record<string, string>>({});
  const [isLoading, setIsLoading] = useState(true);
  const [isSaving, setIsSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);

  const loadSchedule = useCallback(async () => {
    setError(null);
    setIsLoading(true);
    try {
      const response = await apiFetch('/api/admin/schedule');
      const data = await response.json();
      if (!response.ok) {
        throw new Error(data.message ?? 'Failed to load your class schedule.');
      }
      setSchedule(data);
      setGraceMinutes(Object.fromEntries(data.classes.map((scheduledClass: ScheduledClass) => [scheduledClass.id, String(scheduledClass.graceMinutes)])));
    } catch (caughtError) {
      setError(caughtError instanceof Error ? caughtError.message : 'Failed to load your class schedule.');
    } finally {
      setIsLoading(false);
    }
  }, []);

  useEffect(() => {
    loadSchedule();
  }, [loadSchedule]);

  const saveGracePeriod = async (event: React.FormEvent, scheduledClass: ScheduledClass) => {
    event.preventDefault();
    setError(null);
    setSuccess(null);
    setIsSaving(true);
    try {
      const response = await apiFetch(`/api/admin/classes/${scheduledClass.subjectId}/${scheduledClass.blockId}/grace-period`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ graceMinutes: Number(graceMinutes[scheduledClass.id]) }),
      });
      const data = await response.json();
      if (!response.ok) {
        throw new Error(data.message ?? 'Failed to update the grace period.');
      }
      setGraceMinutes((current) => ({ ...current, [scheduledClass.id]: String(data.graceMinutes) }));
      setSuccess(data.message);
    } catch (caughtError) {
      setError(caughtError instanceof Error ? caughtError.message : 'Failed to update the grace period.');
    } finally {
      setIsSaving(false);
    }
  };

  if (isLoading) {
    return <div className="flex min-h-[320px] items-center justify-center text-neutral-500">Loading schedule...</div>;
  }

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-neutral-900">Class Schedule</h1>
        <p className="mt-1 text-sm text-neutral-500">
          {schedule ? `${schedule.day}, ${schedule.date} · ${schedule.teacherName}` : 'Today’s assigned classes'}
        </p>
      </div>

      {error && <div className="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-red-700">{error}</div>}

      {schedule && schedule.classes.length > 0 ? (
        <div className="space-y-4">
          {schedule.classes.map((scheduledClass) => (
            <Card key={scheduledClass.id}>
              <CardHeader className="pb-3">
                <CardTitle className="text-lg">
                  {scheduledClass.subjectCode} - {scheduledClass.subjectName}
                </CardTitle>
              </CardHeader>
              <CardContent className="grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-4">
                <Detail icon={CalendarDays} label="Block" value={scheduledClass.block} />
                <Detail icon={Users} label="Registered students" value={String(scheduledClass.studentCount)} />
                <Detail icon={Clock} label="Class time" value={scheduledClass.schedule} />
                <Detail icon={MapPin} label="Room" value={scheduledClass.room || 'Not assigned'} />
              </CardContent>
              <CardContent className="border-t border-neutral-100 pt-4">
                <form className="flex flex-col gap-3 sm:flex-row sm:items-end" onSubmit={(event) => saveGracePeriod(event, scheduledClass)}>
                  <label className="text-sm font-medium text-neutral-700">
                    Grace period (minutes)
                    <Input
                      type="number"
                      min={0}
                      max={60}
                      required
                      value={graceMinutes[scheduledClass.id] ?? '10'}
                      onChange={(event) => setGraceMinutes((current) => ({ ...current, [scheduledClass.id]: event.target.value }))}
                      className="mt-2 w-36"
                    />
                  </label>
                  <button type="submit" disabled={isSaving} className="h-10 rounded-md bg-neutral-900 px-5 text-sm font-medium text-white hover:bg-neutral-800 disabled:opacity-50">
                    {isSaving ? 'Saving...' : 'Save for this class'}
                  </button>
                </form>
                <p className="mt-2 text-xs text-neutral-500">The default is 10 minutes. Later scans are marked Late.</p>
              </CardContent>
            </Card>
          ))}
        </div>
      ) : (
        <Card>
          <CardContent className="py-12 text-center">
            <CalendarDays className="mx-auto mb-3 h-9 w-9 text-neutral-400" />
            <p className="font-medium text-neutral-900">None</p>
            <p className="mt-1 text-sm text-neutral-500">You have no assigned subject scheduled for today.</p>
          </CardContent>
        </Card>
      )}

      {success && <p className="text-sm text-emerald-700">{success}</p>}
    </div>
  );
}

function Detail({ icon: Icon, label, value }: { icon: typeof CalendarDays; label: string; value: string }) {
  return (
    <div className="flex items-start gap-2">
      <Icon className="mt-0.5 h-4 w-4 shrink-0 text-neutral-500" />
      <div>
        <p className="text-xs text-neutral-500">{label}</p>
        <p className="font-medium text-neutral-900">{value}</p>
      </div>
    </div>
  );
}
