import './bootstrap';
import { createRoot } from 'react-dom/client';
import React, { useEffect, useState } from 'react';
import MainTitle from '@/components/MainTitle';
import { Badge } from '@/components/ui/badge';
import { Skeleton } from '@/components/ui/skeleton';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import {
  Accordion,
  AccordionContent,
  AccordionItem,
  AccordionTrigger,
} from '@/components/ui/accordion';

interface PassRequest {
  id: string;
  passholder_email: string;
  pass_type: string;
  passholder_first_name: string;
  passholder_last_name: string;
  passholder_birth_date: string;
  is_renewal: boolean;
  renewal_pass_id: string | null;
  renewal_order_number: string | null;
  promo_code: string | null;
  redemption_date: string | null;
  assign_code_date: string | null;
  email_notify_time: string | null;
  created_at: string;
  updated_at: string;
}

interface Season {
  id: number;
  pass_name: string;
  pass_year: number;
  start_date: string;
  early_spring_deadline: string;
  final_deadline: string;
  pass_requests: PassRequest[];
  pass_requests_count: number;
  deleted_at: string | null;
}

function formatDate(dateStr: string | null): string {
  if (!dateStr) return '—';
  return new Date(dateStr).toLocaleDateString();
}

function Dashboard() {
  const mount = document.getElementById('dashboard');
  const userName = mount?.getAttribute('data-user-name') || 'User';

  const [seasons, setSeasons] = useState<Season[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const fetchPassRequests = async () => {
      try {
        setLoading(true);
        const response = await fetch('/dashboard/pass-requests', {
          headers: { 'Accept': 'application/json' },
        });
        if (!response.ok) throw new Error('Failed to fetch pass requests');
        const data = await response.json();
        setSeasons(data);
        setError(null);
      } catch (err) {
        setError(err instanceof Error ? err.message : 'An error occurred');
      } finally {
        setLoading(false);
      }
    };

    fetchPassRequests();
  }, []);

  // Calculate total pass requests
  const totalRequests = seasons.reduce((sum, season) => sum + (season.pass_requests?.length || 0), 0);
  
  // Filter to seasons with pass requests
  const seasonsWithRequests = seasons.filter(s => s.pass_requests && s.pass_requests.length > 0);

  return (
    <div className="max-w-6xl mx-auto px-4 py-8">
      <div className="mb-8">
        <MainTitle>Dashboard</MainTitle>
        <p className="text-muted-foreground mt-2">
          Welcome back, {userName}!
        </p>
      </div>
      
      <div className="grid gap-6 md:grid-cols-3 mb-8">
        <div className="rounded-lg border p-6">
          <h3 className="font-semibold text-sm text-muted-foreground uppercase tracking-wide">Total Pass Requests</h3>
          {loading ? (
            <Skeleton className="h-9 w-16 mt-2" />
          ) : (
            <p className="text-3xl font-bold mt-2">{totalRequests}</p>
          )}
        </div>
        <div className="rounded-lg border p-6">
          <h3 className="font-semibold text-sm text-muted-foreground uppercase tracking-wide">Active Seasons</h3>
          {loading ? (
            <Skeleton className="h-9 w-16 mt-2" />
          ) : (
            <p className="text-3xl font-bold mt-2">{seasons.length}</p>
          )}
        </div>
        <div className="rounded-lg border p-6">
          <h3 className="font-semibold text-sm text-muted-foreground uppercase tracking-wide">Pending</h3>
          {loading ? (
            <Skeleton className="h-9 w-16 mt-2" />
          ) : (
            <p className="text-3xl font-bold mt-2">
              {seasons.reduce((sum, s) => sum + (s.pass_requests?.filter(r => !r.redemption_date)?.length || 0), 0)}
            </p>
          )}
        </div>
      </div>

      {error && (
        <div className="mb-4 p-4 bg-destructive/10 border border-destructive rounded-lg text-destructive">
          {error}
        </div>
      )}

      <div className="mb-6">
        <h2 className="text-xl font-semibold mb-4">Your Pass Requests</h2>
        
        {loading ? (
          <div className="space-y-4">
            <div className="border rounded-lg p-4">
              <Skeleton className="h-6 w-48 mb-4" />
              <div className="space-y-3">
                <Skeleton className="h-10 w-full" />
                <Skeleton className="h-10 w-full" />
                <Skeleton className="h-10 w-full" />
              </div>
            </div>
          </div>
        ) : seasonsWithRequests.length === 0 ? (
          <div className="rounded-lg border p-8 text-center text-muted-foreground">
            <p>You don't have any pass requests yet.</p>
          </div>
        ) : (
          <Accordion type="multiple" className="space-y-4" defaultValue={seasonsWithRequests.map(s => s.id.toString())}>
            {seasonsWithRequests.map((season) => (
              <AccordionItem key={season.id} value={season.id.toString()} className="border rounded-lg">
                <AccordionTrigger className="px-4 hover:no-underline">
                  <div className="flex items-center gap-3">
                    <span className="font-semibold">{season.pass_name} {season.pass_year}</span>
                    <Badge variant="secondary">{season.pass_requests.length} request(s)</Badge>
                  </div>
                </AccordionTrigger>
                <AccordionContent className="px-0 pb-0">
                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead>Passholder</TableHead>
                        <TableHead>Email</TableHead>
                        <TableHead>Pass Type</TableHead>
                        <TableHead>Birth Date</TableHead>
                        <TableHead>Renewal</TableHead>
                        <TableHead>Redeemed</TableHead>
                        <TableHead>Created</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {season.pass_requests.map((request) => (
                        <TableRow key={request.id}>
                          <TableCell className="font-medium">
                            {request.passholder_first_name} {request.passholder_last_name}
                          </TableCell>
                          <TableCell>{request.passholder_email}</TableCell>
                          <TableCell>{request.pass_type}</TableCell>
                          <TableCell>{formatDate(request.passholder_birth_date)}</TableCell>
                          <TableCell>
                            {request.is_renewal ? (
                              <Badge variant="default">Yes</Badge>
                            ) : (
                              <Badge variant="secondary">No</Badge>
                            )}
                          </TableCell>
                          <TableCell>
                            {request.redemption_date ? (
                              <Badge variant="default">{formatDate(request.redemption_date)}</Badge>
                            ) : (
                              <Badge variant="outline">Pending</Badge>
                            )}
                          </TableCell>
                          <TableCell>{formatDate(request.created_at)}</TableCell>
                        </TableRow>
                      ))}
                    </TableBody>
                  </Table>
                </AccordionContent>
              </AccordionItem>
            ))}
          </Accordion>
        )}
      </div>
    </div>
  );
}

const dashboardElement = document.getElementById('dashboard');
if (dashboardElement) {
  createRoot(dashboardElement).render(<Dashboard />);
}
