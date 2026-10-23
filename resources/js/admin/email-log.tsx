import '../bootstrap';

import React, { useState } from 'react';
import { createRoot } from 'react-dom/client';

import MainTitle from '@/components/MainTitle';
import { Button } from '@/components/ui/button';
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Skeleton } from '@/components/ui/skeleton';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import { formatDateTime } from '@/lib/dateHelpers';
import { readPageData } from '@/lib/pageData';

interface EmailLog {
  id: number;
  event: string;
  email_to: string;
  email_from: string;
  subject: string;
  body: string;
  result: string | null;
  error_message: string | null;
  created_at: string;
}

interface EmailLogData {
  emailLogs: EmailLog[];
  total: number;
  page: number;
  limit: number;
}

interface EmailLogAdminProps {
  initialData: EmailLogData;
}

export function EmailLogAdmin({ initialData }: EmailLogAdminProps) {
  const [emailLogs, setEmailLogs] = useState<EmailLog[]>(initialData.emailLogs);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [page, setPage] = useState(initialData.page);
  const limit = initialData.limit;
  const [total, setTotal] = useState(initialData.total);
  const [toFilter, setToFilter] = useState('');
  const [bodyFilter, setBodyFilter] = useState('');
  const [appliedFilters, setAppliedFilters] = useState({ to: '', body: '' });

  const fetchData = async (nextPage: number, filters = appliedFilters) => {
    setLoading(true);
    setError(null);
    try {
      const params = new URLSearchParams({
        page: nextPage.toString(),
        limit: limit.toString(),
      });
      if (filters.to) params.append('to', filters.to);
      if (filters.body) params.append('body', filters.body);

      const response = await fetch(`/api/admin/email-log/list?${params.toString()}`, {
        headers: { 'Accept': 'application/json' },
      });
      if (!response.ok) {
        const errorData = await response.json();
        throw new Error(errorData.message || 'Failed to fetch data');
      }
      const data = await response.json();
      setEmailLogs(data.emailLogs);
      setTotal(data.total);
      setPage(data.page);
    } catch (err) {
      console.error('Error fetching email log data:', err);
      setError(err instanceof Error ? err.message : 'An unknown error occurred');
    } finally {
      setLoading(false);
    }
  };

  const handleSearch = (e: React.FormEvent) => {
    e.preventDefault();
    const filters = { to: toFilter, body: bodyFilter };
    setAppliedFilters(filters);
    void fetchData(1, filters);
  };

  const totalPages = Math.ceil(total / limit);

  return (
    <div className="max-w-7xl mx-auto px-4 py-8">
      <MainTitle>Email Log</MainTitle>
      <p className="text-muted-foreground mt-2 mb-6">View and debug outgoing emails</p>

      <form onSubmit={handleSearch} className="flex gap-4 mb-6">
        <Input
          type="text"
          placeholder="Search by recipient..."
          value={toFilter}
          onChange={(e) => setToFilter(e.target.value)}
          className="max-w-xs"
        />
        <Input
          type="text"
          placeholder="Search by body content..."
          value={bodyFilter}
          onChange={(e) => setBodyFilter(e.target.value)}
          className="max-w-xs"
        />
        <Button type="submit">Search</Button>
      </form>

      {error && (
        <div className="mb-4 p-4 bg-destructive/10 border border-destructive rounded-lg text-destructive">
          {error}
        </div>
      )}

      {loading ? (
        <div className="space-y-3">
          <Skeleton className="h-10 w-full" />
          <Skeleton className="h-10 w-full" />
          <Skeleton className="h-10 w-full" />
          <Skeleton className="h-10 w-full" />
          <Skeleton className="h-10 w-full" />
        </div>
      ) : (
        <>
          <div className="rounded-lg border">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>To</TableHead>
                  <TableHead>From</TableHead>
                  <TableHead>Subject</TableHead>
                  <TableHead>Timestamp</TableHead>
                  <TableHead>Details</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {emailLogs.length === 0 ? (
                  <TableRow>
                    <TableCell colSpan={5} className="text-center text-muted-foreground py-8">
                      No email logs found
                    </TableCell>
                  </TableRow>
                ) : (
                  emailLogs.map((log) => (
                    <TableRow key={log.id}>
                      <TableCell className="font-medium">{log.email_to}</TableCell>
                      <TableCell>{log.email_from}</TableCell>
                      <TableCell>{log.subject}</TableCell>
                      <TableCell>{formatDateTime(log.created_at)}</TableCell>
                      <TableCell>
                        <Dialog>
                          <DialogTrigger asChild>
                            <Button variant="outline" size="sm">View</Button>
                          </DialogTrigger>
                          <DialogContent className="w-3/4 max-w-3xl max-h-[80vh] overflow-y-auto">
                            <DialogHeader>
                              <DialogTitle>Email Details</DialogTitle>
                            </DialogHeader>
                            <div className="space-y-3">
                              <p><strong>To:</strong> {log.email_to}</p>
                              <p><strong>From:</strong> {log.email_from}</p>
                              <p><strong>Subject:</strong> {log.subject}</p>
                              <p><strong>Event:</strong> {log.event}</p>
                              <p><strong>Result:</strong> {log.result || 'N/A'}</p>
                              <p><strong>Timestamp:</strong> {formatDateTime(log.created_at)}</p>
                              {log.error_message && (
                                <div className="p-3 bg-destructive/10 border border-destructive rounded text-destructive">
                                  <strong>Error:</strong> {log.error_message}
                                </div>
                              )}
                              <hr className="my-2" />
                              <p><strong>Body:</strong></p>
                              <textarea
                                className="w-full border rounded p-2 font-mono text-sm"
                                rows={20}
                                readOnly
                                value={log.body}
                              />
                            </div>
                          </DialogContent>
                        </Dialog>
                      </TableCell>
                    </TableRow>
                  ))
                )}
              </TableBody>
            </Table>
          </div>

          <div className="flex justify-between items-center mt-4">
            <Button
              onClick={() => { void fetchData(page - 1); }}
              disabled={page === 1}
              variant="outline"
            >
              Previous
            </Button>
            <span className="text-sm text-muted-foreground">
              Page {page} of {totalPages || 1} ({total} total)
            </span>
            <Button
              onClick={() => { void fetchData(page + 1); }}
              disabled={page >= totalPages}
              variant="outline"
            >
              Next
            </Button>
          </div>
        </>
      )}
    </div>
  );
}

const element = document.getElementById('email-log');
if (element) {
  createRoot(element).render(<EmailLogAdmin initialData={readPageData<EmailLogData>()} />);
}
