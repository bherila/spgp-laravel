import './bootstrap';
import { createRoot } from 'react-dom/client';
import React, { useEffect, useState } from 'react';
import MainTitle from '@/components/MainTitle';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Skeleton } from '@/components/ui/skeleton';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from '@/components/ui/dialog';
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
import { FileEdit, Trash2, X } from 'lucide-react';

interface SeasonPassType {
  id: number;
  pass_type_name: string;
}

interface PassRequest {
  id: string;
  passholder_email: string;
  pass_type: string;
  season_pass_type?: SeasonPassType | null;
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

function getPassTypeName(request: PassRequest): string {
  return request.season_pass_type?.pass_type_name ?? request.pass_type ?? 'Unknown';
}

function Dashboard() {
  const mount = document.getElementById('dashboard');
  const userName = mount?.getAttribute('data-user-name') || 'User';

  const [seasons, setSeasons] = useState<Season[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [renewalModalOpen, setRenewalModalOpen] = useState(false);
  const [renewalRequest, setRenewalRequest] = useState<PassRequest | null>(null);
  const [renewalOrderNumber, setRenewalOrderNumber] = useState('');
  const [renewalSubmitting, setRenewalSubmitting] = useState(false);
  const [cancelConfirmId, setCancelConfirmId] = useState<string | null>(null);

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

  useEffect(() => {
    fetchPassRequests();
  }, []);

  const handleOpenRenewalModal = (request: PassRequest) => {
    setRenewalRequest(request);
    setRenewalOrderNumber(request.renewal_order_number || '');
    setRenewalModalOpen(true);
  };

  const handleSubmitRenewal = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!renewalRequest) return;

    setRenewalSubmitting(true);
    try {
      const response = await fetch(`/pass-requests/${renewalRequest.id}/renewal-order`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
        },
        body: JSON.stringify({ renewal_order_number: renewalOrderNumber }),
      });

      if (!response.ok) {
        const data = await response.json();
        throw new Error(data.message || 'Failed to update renewal order');
      }

      setRenewalModalOpen(false);
      setRenewalRequest(null);
      setRenewalOrderNumber('');
      fetchPassRequests();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'An error occurred');
    } finally {
      setRenewalSubmitting(false);
    }
  };

  const handleRemoveRenewal = async (requestId: string) => {
    try {
      const response = await fetch(`/pass-requests/${requestId}/renewal-order`, {
        method: 'DELETE',
        headers: { 'Accept': 'application/json' },
      });

      if (!response.ok) {
        const data = await response.json();
        throw new Error(data.message || 'Failed to remove renewal order');
      }

      fetchPassRequests();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'An error occurred');
    }
  };

  const handleCancelRequest = async (requestId: string) => {
    try {
      const response = await fetch(`/pass-requests/${requestId}`, {
        method: 'DELETE',
        headers: { 'Accept': 'application/json' },
      });

      if (!response.ok) {
        const data = await response.json();
        throw new Error(data.message || 'Failed to cancel pass request');
      }

      setCancelConfirmId(null);
      fetchPassRequests();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'An error occurred');
    }
  };

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
                        <TableHead>Pass Type</TableHead>
                        <TableHead>Renewal Order</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead>Actions</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {season.pass_requests.map((request) => (
                        <TableRow key={request.id}>
                          <TableCell className="font-medium">
                            {request.passholder_first_name} {request.passholder_last_name}
                          </TableCell>
                          <TableCell>{getPassTypeName(request)}</TableCell>
                          <TableCell>
                            {request.renewal_order_number ? (
                              <div className="flex items-center gap-2">
                                <span className="font-mono text-sm">{request.renewal_order_number}</span>
                                <Button
                                  variant="ghost"
                                  size="sm"
                                  className="h-6 w-6 p-0"
                                  onClick={() => handleRemoveRenewal(request.id)}
                                  title="Remove renewal order"
                                >
                                  <X className="h-3 w-3" />
                                </Button>
                              </div>
                            ) : (
                              <span className="text-muted-foreground">—</span>
                            )}
                          </TableCell>
                          <TableCell>
                            {request.redemption_date ? (
                              <Badge variant="default">Redeemed</Badge>
                            ) : request.promo_code ? (
                              <Badge variant="secondary">Code Assigned</Badge>
                            ) : (
                              <Badge variant="outline">Pending</Badge>
                            )}
                          </TableCell>
                          <TableCell>
                            <div className="flex items-center gap-2">
                              <Button
                                variant="outline"
                                size="sm"
                                onClick={() => handleOpenRenewalModal(request)}
                              >
                                <FileEdit className="w-4 h-4 mr-1" />
                                {request.renewal_order_number ? 'Edit' : 'Report'} Renewal
                              </Button>
                              {!request.promo_code && (
                                cancelConfirmId === request.id ? (
                                  <div className="flex items-center gap-1">
                                    <Button
                                      variant="destructive"
                                      size="sm"
                                      onClick={() => handleCancelRequest(request.id)}
                                    >
                                      Confirm
                                    </Button>
                                    <Button
                                      variant="outline"
                                      size="sm"
                                      onClick={() => setCancelConfirmId(null)}
                                    >
                                      No
                                    </Button>
                                  </div>
                                ) : (
                                  <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => setCancelConfirmId(request.id)}
                                    className="text-destructive hover:text-destructive"
                                  >
                                    <Trash2 className="w-4 h-4 mr-1" />
                                    Cancel
                                  </Button>
                                )
                              )}
                            </div>
                          </TableCell>
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

      {/* Renewal Order Modal */}
      <Dialog open={renewalModalOpen} onOpenChange={setRenewalModalOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Enter Renewal Order Number</DialogTitle>
            <DialogDescription>
              If you renewed your pass, please enter the order number from your
              receipt so that our group can get credit for the renewal. This will
              benefit the program in the future and has no cost to you. ❤️ Please
              update for EACH pass renewed, even if the order number is the same.
            </DialogDescription>
          </DialogHeader>
          <form onSubmit={handleSubmitRenewal}>
            <div className="grid gap-4 py-4">
              <div className="space-y-2">
                <Label htmlFor="renewal-order">Order Number</Label>
                <Input
                  id="renewal-order"
                  placeholder="Enter your renewal order number for this pass"
                  value={renewalOrderNumber}
                  onChange={(e) => setRenewalOrderNumber(e.target.value)}
                  maxLength={30}
                  required
                />
              </div>
            </div>
            <DialogFooter>
              <Button type="button" variant="outline" onClick={() => setRenewalModalOpen(false)}>
                Cancel
              </Button>
              <Button type="submit" disabled={renewalSubmitting}>
                {renewalSubmitting ? 'Saving...' : 'Save Order Number'}
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>
    </div>
  );
}

const dashboardElement = document.getElementById('dashboard');
if (dashboardElement) {
  createRoot(dashboardElement).render(<Dashboard />);
}
