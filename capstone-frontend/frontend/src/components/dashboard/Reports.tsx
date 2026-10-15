import React, { useEffect, useMemo, useState } from 'react';
import { ChevronDown, ChevronRight } from 'lucide-react';
import { Badge } from '../ui/badge';
import { Input } from '../ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '../ui/table';
import { apiFetch } from '../../api';
import type { UserRole } from '../../types/rbac';

type AttendanceStatus = 'Present' | 'Absent' | 'Late';

type ReportRow = {
  id: string;
  date: string;
  class: string;
  status: AttendanceStatus;
  student: { id: string; name: string; idNumber: string; email: string; block: string };
};

type ReportsResponse = {
  reports: ReportRow[];
  blocks: string[];
  scheduledDates: string[];
  message?: string;
};

const currentWeek = () => {
  const date = new Date();
  const thursday = new Date(date);
  thursday.setDate(date.getDate() + 4 - (date.getDay() || 7));
  const yearStart = new Date(thursday.getFullYear(), 0, 1);
  const week = Math.ceil((((thursday.getTime() - yearStart.getTime()) / 86_400_000) + 1) / 7);

  return `${thursday.getFullYear()}-W${String(week).padStart(2, '0')}`;
};

const weekRange = (weekValue: string) => {
  const [yearValue, weekNumberValue] = weekValue.split('-W');
  const year = Number(yearValue);
  const weekNumber = Number(weekNumberValue);
  const januaryFourth = new Date(year, 0, 4);
  const monday = new Date(januaryFourth);
  monday.setDate(januaryFourth.getDate() - (januaryFourth.getDay() || 7) + 1 + (weekNumber - 1) * 7);
  const sunday = new Date(monday);
  sunday.setDate(monday.getDate() + 6);
  const formatDate = (date: Date) => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;

  return { from: formatDate(monday), to: formatDate(sunday) };
};

const statusClassName = (status: AttendanceStatus) => {
  if (status === 'Present') return 'attendance-status attendance-status--present';
  if (status === 'Late') return 'attendance-status attendance-status--late';
  return 'attendance-status attendance-status--absent';
};

const statusDotClassName = (status: AttendanceStatus) => {
  if (status === 'Present') return 'attendance-status-dot--present';
  if (status === 'Late') return 'attendance-status-dot--late';
  return 'attendance-status-dot--absent';
};

const formatDayHeading = (dateValue: string) => new Intl.DateTimeFormat('en-US', {
  weekday: 'long',
  month: 'long',
  day: 'numeric',
  year: 'numeric',
  timeZone: 'UTC',
}).format(new Date(`${dateValue}T00:00:00Z`));

export function Reports({ role: _role }: { role: UserRole }) {
  const [reportWeek, setReportWeek] = useState(currentWeek);
  const [block, setBlock] = useState('');
  const [status, setStatus] = useState('');
  const [blocks, setBlocks] = useState<string[]>([]);
  const [reports, setReports] = useState<ReportRow[]>([]);
  const [scheduledDates, setScheduledDates] = useState<string[]>([]);
  const [expandedDates, setExpandedDates] = useState<Set<string>>(() => new Set());
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const abortController = new AbortController();

    const loadReports = async () => {
      setIsLoading(true);
      setError(null);

      try {
        const params = new URLSearchParams(weekRange(reportWeek));
        if (block) params.set('block', block);
        if (status) params.set('status', status);

        const response = await apiFetch(`/api/admin/reports?${params.toString()}`, {
          signal: abortController.signal,
        });
        const contentType = response.headers.get('content-type') ?? '';

        if (!contentType.includes('application/json')) {
          throw new Error('The attendance service returned an invalid response. Please sign in again or contact the administrator.');
        }

        const data = (await response.json()) as ReportsResponse;
        if (!response.ok) throw new Error(data.message || 'Unable to load attendance reports.');

        if (!Array.isArray(data.reports) || !Array.isArray(data.blocks) || !Array.isArray(data.scheduledDates)) {
          throw new Error('The attendance service returned an invalid report format.');
        }

        setReports(data.reports);
        setBlocks(data.blocks);
        setScheduledDates(data.scheduledDates);
        setExpandedDates(new Set());
      } catch (caughtError) {
        if (caughtError instanceof Error && caughtError.name === 'AbortError') return;
        setError(caughtError instanceof Error ? caughtError.message : 'Unable to load attendance reports.');
      } finally {
        if (!abortController.signal.aborted) setIsLoading(false);
      }
    };

    loadReports();
    return () => abortController.abort();
  }, [reportWeek, block, status]);

  const dailyReports = useMemo(() => {
    const { from } = weekRange(reportWeek);
    const monday = new Date(`${from}T00:00:00`);

    return Array.from({ length: 7 }, (_, index) => {
      const date = new Date(monday);
      date.setDate(monday.getDate() + index);
      const dateValue = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;

      return {
        date: dateValue,
        rows: reports.filter((report) => report.date === dateValue),
      };
    }).filter((day) => scheduledDates.includes(day.date));
  }, [reportWeek, reports, scheduledDates]);

  const toggleDay = (date: string) => {
    setExpandedDates((current) => {
      const next = new Set(current);
      if (next.has(date)) next.delete(date);
      else next.add(date);

      return next;
    });
  };

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-neutral-900">Weekly Attendance Reports</h1>
        <p className="text-neutral-500">Review each day of the selected week and the attendance status of every student.</p>
      </div>

      <div className="grid grid-cols-1 gap-4 rounded-lg border border-neutral-200 bg-white p-4 shadow-sm md:grid-cols-3">
        <div className="space-y-2">
          <label className="text-sm font-medium text-neutral-700" htmlFor="report-week">Week</label>
          <Input id="report-week" type="week" value={reportWeek} onChange={(event) => {
            if (event.target.value) setReportWeek(event.target.value);
          }} />
        </div>
        <div className="space-y-2">
          <label className="text-sm font-medium text-neutral-700" htmlFor="report-block">Year and block</label>
          <select id="report-block" className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm" value={block} onChange={(event) => setBlock(event.target.value)}>
            <option value="">All year levels and blocks</option>
            {blocks.map((blockName) => <option key={blockName} value={blockName}>{blockName}</option>)}
          </select>
        </div>
        <div className="space-y-2">
          <label className="text-sm font-medium text-neutral-700" htmlFor="report-status">Status</label>
          <select id="report-status" className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm" value={status} onChange={(event) => setStatus(event.target.value)}>
            <option value="">All statuses</option>
            <option value="present">Present</option>
            <option value="absent">Absent</option>
            <option value="late">Late</option>
          </select>
        </div>
      </div>

      {error && <p className="text-sm text-red-600" role="alert">{error}</p>}

      {isLoading ? (
        <div className="rounded-lg border border-neutral-200 bg-white py-10 text-center text-neutral-500 shadow-sm">Loading weekly attendance...</div>
      ) : (
        <div className="space-y-4">
          {dailyReports.length === 0 ? (
            <div className="rounded-lg border border-neutral-200 bg-white py-10 text-center text-neutral-500 shadow-sm">
              No subjects are assigned to you for this selected week.
            </div>
          ) : dailyReports.map((day) => {
            const isExpanded = expandedDates.has(day.date);

            return (
            <section key={day.date} className="overflow-hidden rounded-lg border border-neutral-200 bg-white shadow-sm">
              <button
                type="button"
                className="flex w-full items-center justify-between bg-neutral-50 px-4 py-3 text-left transition-colors hover:bg-neutral-100"
                aria-expanded={isExpanded}
                aria-controls={`attendance-${day.date}`}
                onClick={() => toggleDay(day.date)}
              >
                <div>
                  <h2 className="font-semibold text-neutral-900">{formatDayHeading(day.date)}</h2>
                  <p className="text-xs text-neutral-500">{day.rows.length} attendance {day.rows.length === 1 ? 'record' : 'records'}</p>
                </div>
                {isExpanded ? <ChevronDown className="h-5 w-5 text-neutral-500" /> : <ChevronRight className="h-5 w-5 text-neutral-500" />}
              </button>
              {isExpanded && <div id={`attendance-${day.date}`} className="border-t border-neutral-200"><Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Student ID</TableHead>
                    <TableHead>Student</TableHead>
                    <TableHead>Year / Block</TableHead>
                    <TableHead className="text-right">Status</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {day.rows.length === 0 ? (
                    <TableRow>
                      <TableCell colSpan={4} className="py-6 text-center text-neutral-500">No completed attendance sessions for this day.</TableCell>
                    </TableRow>
                  ) : day.rows.map((report) => (
                    <TableRow key={report.id}>
                      <TableCell className="font-mono text-sm">{report.student.idNumber}</TableCell>
                      <TableCell>
                        <p className="font-medium text-neutral-900">{report.student.name}</p>
                        <p className="text-xs text-neutral-500">{report.student.email}</p>
                      </TableCell>
                      <TableCell>{report.class}</TableCell>
                      <TableCell className="text-right">
                        <Badge variant="outline" className={`gap-2 px-2.5 py-1 ${statusClassName(report.status)}`}>
                          <span className={`attendance-status-dot ${statusDotClassName(report.status)}`} aria-hidden="true" />
                          {report.status}
                        </Badge>
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table></div>}
            </section>
            );
          })}
        </div>
      )}
    </div>
  );
}
