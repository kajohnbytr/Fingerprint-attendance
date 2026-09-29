import React, { Fragment, useEffect, useState } from 'react';
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
  message?: string;
};

const today = () => {
  const date = new Date();
  return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
};

const statusClassName = (status: AttendanceStatus) => {
  if (status === 'Present') return 'border-emerald-200 text-emerald-700 bg-emerald-50';
  if (status === 'Late') return 'border-amber-200 text-amber-700 bg-amber-50';
  return 'border-red-200 text-red-700 bg-red-50';
};

export function Reports({ role: _role }: { role: UserRole }) {
  const [reportDate, setReportDate] = useState(today);
  const [block, setBlock] = useState('');
  const [status, setStatus] = useState('');
  const [blocks, setBlocks] = useState<string[]>([]);
  const [reports, setReports] = useState<ReportRow[]>([]);
  const [expandedId, setExpandedId] = useState<string | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const abortController = new AbortController();

    const loadReports = async () => {
      setIsLoading(true);
      setError(null);

      try {
        const params = new URLSearchParams({ date: reportDate });
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

        setReports(data.reports ?? []);
        setBlocks(data.blocks ?? []);
        setExpandedId(null);
      } catch (caughtError) {
        if (caughtError instanceof Error && caughtError.name === 'AbortError') return;
        setError(caughtError instanceof Error ? caughtError.message : 'Unable to load attendance reports.');
      } finally {
        if (!abortController.signal.aborted) setIsLoading(false);
      }
    };

    loadReports();
    return () => abortController.abort();
  }, [reportDate, block, status]);

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-neutral-900">Attendance Reports</h1>
        <p className="text-neutral-500">Review student attendance by date, year and block, and status.</p>
      </div>

      <div className="grid grid-cols-1 gap-4 rounded-lg border border-neutral-200 bg-white p-4 shadow-sm md:grid-cols-3">
        <div className="space-y-2">
          <label className="text-sm font-medium text-neutral-700" htmlFor="report-date">Date</label>
          <Input id="report-date" type="date" value={reportDate} onChange={(event) => setReportDate(event.target.value)} />
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

      <div className="overflow-hidden rounded-lg border border-neutral-200 bg-white shadow-sm">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead className="w-8" />
              <TableHead>Date</TableHead>
              <TableHead>Year / Block</TableHead>
              <TableHead className="text-right">Status</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {isLoading ? (
              <TableRow><TableCell colSpan={4} className="py-8 text-center text-neutral-500">Loading attendance...</TableCell></TableRow>
            ) : reports.length === 0 ? (
              <TableRow><TableCell colSpan={4} className="py-8 text-center text-neutral-500">No attendance records found.</TableCell></TableRow>
            ) : reports.map((report) => {
              const isExpanded = expandedId === report.id;

              return (
                <Fragment key={report.id}>
                  <TableRow className="cursor-pointer transition-colors hover:bg-neutral-50" onClick={() => setExpandedId(isExpanded ? null : report.id)}>
                    <TableCell className="w-8 py-2">
                      {isExpanded ? <ChevronDown className="h-4 w-4 text-neutral-500" /> : <ChevronRight className="h-4 w-4 text-neutral-500" />}
                    </TableCell>
                    <TableCell className="font-medium">{report.date}</TableCell>
                    <TableCell>{report.class}</TableCell>
                    <TableCell className="text-right"><Badge variant="outline" className={statusClassName(report.status)}>{report.status}</Badge></TableCell>
                  </TableRow>
                  {isExpanded && (
                    <TableRow>
                      <TableCell colSpan={4} className="bg-neutral-50 px-6 py-4">
                        <div className="grid gap-2 text-sm sm:grid-cols-2 lg:grid-cols-4">
                          <p><span className="text-neutral-500">Student:</span> {report.student.name}</p>
                          <p><span className="text-neutral-500">ID:</span> {report.student.idNumber}</p>
                          <p><span className="text-neutral-500">Email:</span> {report.student.email}</p>
                          <p><span className="text-neutral-500">Block:</span> {report.student.block}</p>
                        </div>
                      </TableCell>
                    </TableRow>
                  )}
                </Fragment>
              );
            })}
          </TableBody>
        </Table>
      </div>
    </div>
  );
}
