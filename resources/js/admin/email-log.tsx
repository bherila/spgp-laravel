import '../bootstrap';

import React, { useCallback, useEffect, useState } from 'react';
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

function EmailLogAdmin() {
  const [emailLogs, setEmailLogs] = useState<EmailLog[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [page, setPage] = useState(1);
  const [limit] = useState(50);
  const [total, setTotal] = useState(0);
  const [toFilter, setToFilter] = useState('');
  const [bodyFilter, setBodyFilter] = useState('');

  const fetchData = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const params = new URLSearchParams({
        page: page.toString(),
        limit: limit.toString(),
      });
      if (toFilter) params.append('to', toFilter);
      if (bodyFilter) params.append('body', bodyFilter);

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
    } catch (err) {
      console.error('Error fetching email log data:', err);
      setError(err instanceof Error ? err.message : 'An unknown error occurred');
    } finally {
      setLoading(false);
    }
  }, [page, limit, toFilter, bodyFilter]);

  useEffect(() => {
    fetchData();
  }, [fetchData]);

  const handleSearch = (e: React.FormEvent) => {
    e.preventDefault();
    setPage(1);
    fetchData();
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
                      <TableCell>{new Date(log.created_at).toLocaleString()}</TableCell>
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
                              <p><strong>Timestamp:</strong> {new Date(log.created_at).toLocaleString()}</p>
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
              onClick={() => setPage(page - 1)}
              disabled={page === 1}
              variant="outline"
            >
              Previous
            </Button>
            <span className="text-sm text-muted-foreground">
              Page {page} of {totalPages || 1} ({total} total)
            </span>
            <Button
              onClick={() => setPage(page + 1)}
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
  createRoot(element).render(<EmailLogAdmin />);
}
