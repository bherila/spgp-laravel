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
import { FileEdit, Trash2, X, Plus, Copy, Check, HelpCircle } from 'lucide-react';
import currency from 'currency.js';
import { RenewalInfo } from '@/components/RenewalInfo';
import {
  Tooltip,
  TooltipContent,
  TooltipProvider,
  TooltipTrigger,
} from '@/components/ui/tooltip';
import Questions from './components/Questions';

interface SeasonPassType {
  id: number;
  pass_type_name: string;
  regular_early_price: number | null;
  regular_regular_price: number | null;
  renewal_early_price: number | null;
  renewal_regular_price: number | null;
  group_early_price: number | null;
  group_regular_price: number | null;
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
  questions_count: number;
  deleted_at: string | null;
}

function formatDate(dateStr: string | null): string {
  if (!dateStr) return '—';
  const date = new Date(dateStr);
  return date.toLocaleDateString(undefined, { 
    year: 'numeric', 
    month: 'long', 
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  });
}

function getPassTypeName(request: PassRequest): string {
  return request.season_pass_type?.pass_type_name ?? request.pass_type ?? 'Unknown';
}

const formatPrice = (value: number | string | null | undefined): string => {
  if (value === null || value === undefined) return '$ TBA';
  return currency(value).format();
};

function Dashboard() {
  const mount = document.getElementById('dashboard');
  const userName = mount?.getAttribute('data-user-name') || 'User';
  const isAdmin = mount?.getAttribute('data-is-admin') === '1';
  const seasonId = mount?.getAttribute('data-season-id');
  const isQuestionsView = mount?.getAttribute('data-is-questions-view') === '1';
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

  const [seasons, setSeasons] = useState<Season[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [renewalModalOpen, setRenewalModalOpen] = useState(false);
  const [renewalRequest, setRenewalRequest] = useState<PassRequest | null>(null);
  const [infoRequest, setInfoRequest] = useState<(PassRequest & { season: Season }) | null>(null);
  const [renewalOrderNumber, setRenewalOrderNumber] = useState('');
  const [renewalSubmitting, setRenewalSubmitting] = useState(false);
  const [cancelConfirmId, setCancelConfirmId] = useState<string | null>(null);
  const [copiedId, setCopiedId] = useState<string | null>(null);
  const [inviteCode, setInviteCode] = useState('');
  const [redeeming, setRedeeming] = useState(false);
  const [redeemSuccess, setRedeemSuccess] = useState<string | null>(null);

  const fetchPassRequests = async () => {
    try {
      setLoading(true);
      const response = await fetch('/api/dashboard/pass-requests', {
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
    if (!isQuestionsView) {
      fetchPassRequests();
    }
  }, [isQuestionsView]);

  if (isQuestionsView && seasonId) {
    return <Questions seasonId={parseInt(seasonId)} isAdmin={isAdmin} csrfToken={csrfToken} />;
  }

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
      const response = await fetch(`/api/pass-requests/${renewalRequest.id}/renewal-order`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': csrfToken,
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
      const response = await fetch(`/api/pass-requests/${requestId}/renewal-order`, {
        method: 'DELETE',
        headers: { 
          'Accept': 'application/json',
          'X-CSRF-TOKEN': csrfToken,
        },
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
      const response = await fetch(`/api/pass-requests/${requestId}`, {
        method: 'DELETE',
        headers: { 
          'Accept': 'application/json',
          'X-CSRF-TOKEN': csrfToken,
        },
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

  const handleCopyPromoCode = (code: string, requestId: string) => {
    navigator.clipboard.writeText(code);
    setCopiedId(requestId);
    setTimeout(() => setCopiedId(null), 2000);
  };

  const handleRedeemInvite = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!inviteCode.trim()) return;

    setRedeeming(true);
    setError(null);
    setRedeemSuccess(null);

    try {
      const response = await fetch('/api/dashboard/redeem-invite', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': csrfToken,
        },
        body: JSON.stringify({ invite_code: inviteCode }),
      });

      const data = await response.json();

      if (!response.ok) {
        throw new Error(data.message || 'Failed to redeem invite code');
      }

      setRedeemSuccess(data.message);
      setInviteCode('');
      fetchPassRequests();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'An error occurred');
    } finally {
      setRedeeming(false);
    }
  };

  // Filter to seasons that are current or upcoming
  const now = new Date();
  const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());

  const availableSeasons = seasons.filter(s => {
    // A season is available if its final deadline is today or in the future
    const finalDeadline = new Date(s.final_deadline);
    const deadlineDay = new Date(finalDeadline.getFullYear(), finalDeadline.getMonth(), finalDeadline.getDate());
    return deadlineDay >= today && s.deleted_at === null;
  });
  
  // Filter to seasons with pass requests
  const seasonsWithRequests = seasons.filter(s => s.pass_requests && s.pass_requests.length > 0);

  // Find pending renewal requests for instruction display
  const pendingRenewals = seasonsWithRequests.flatMap(s => 
    (s.pass_requests || []).filter(r => 
      r.is_renewal && !r.redemption_date && !r.promo_code && !r.renewal_order_number &&
      new Date(s.early_spring_deadline) > now
    ).map(r => ({ ...r, season: s }))
  );

  return (
    <TooltipProvider>
      <div className="max-w-6xl mx-auto px-4 py-8">
        <div className="mb-8">
          <MainTitle>Dashboard</MainTitle>
          <p className="text-muted-foreground mt-2">
            Welcome back, {userName}!
          </p>
        </div>

        {loading ? (
          <div className="grid gap-6 mb-12">
            <Skeleton className="h-[120px] w-full rounded-xl" />
          </div>
        ) : availableSeasons.length > 0 ? (
          <div className="grid gap-6 mb-12">
            {availableSeasons.map(season => {
              const startDate = new Date(season.start_date);
              const isStarted = startDate <= now;
              const canRequest = isStarted || isAdmin;
              const openDateStr = startDate.toLocaleDateString(undefined, { month: 'long', day: 'numeric' });

              return (
                <div key={season.id} className="rounded-xl border bg-card text-card-foreground shadow-sm p-6">
                  <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div>
                      <h2 className="text-2xl font-bold">{season.pass_name}</h2>
                      <p className="text-muted-foreground mt-1">
                        Final deadline: {formatDate(season.final_deadline)}
                      </p>
                      {season.early_spring_deadline && (
                        (() => {
                          const early = new Date(season.early_spring_deadline);
                          const passed = early < now;
                          return (
                            <p className={`mt-1 ${passed ? 'text-red-600 line-through' : ''}`}>Early spring deadline: {formatDate(season.early_spring_deadline)}</p>
                          );
                        })()
                      )}
                    </div>
                    <div className="flex items-center gap-2">
                      <Button asChild variant="secondary" size="sm" className="h-8 px-2">
                        <a href={`/season/${season.id}/questions`}>                        
                          <HelpCircle className="w-4 h-4 mr-1.5" />
                          View {season.questions_count} questions
                        </a>
                      </Button>
                      <Button asChild size="lg" disabled={!canRequest} className={!canRequest ? 'opacity-50 pointer-events-none' : ''}>
                        {canRequest ? (
                          <a href={`/request/${season.id}`}>
                            <Plus className="w-5 h-5 mr-2" />
                            Request New Pass
                          </a>
                        ) : (
                          <span>Opens on {openDateStr}</span>
                        )}
                      </Button>
                    </div>
                  </div>
                </div>
              );
            })}
          </div>
        ) : (
          <div className="rounded-xl border bg-card/50 text-card-foreground p-12 mb-12 text-center text-muted-foreground">
            <div className="flex justify-center mb-4">
              <div className="p-3 rounded-full bg-muted">
                <Plus className="w-8 h-8 opacity-20" />
              </div>
            </div>
            <h3 className="text-lg font-medium text-foreground mb-1">No Active Seasons</h3>
            <p className="max-w-xs mx-auto mb-6">There are currently no seasons available for new pass requests.</p>
            
            <div className="max-w-sm mx-auto border-t pt-6">
              <p className="text-sm mb-4 text-foreground">Have an invite code? Enter it to get access to next season.</p>
              <form onSubmit={handleRedeemInvite} className="flex gap-2">
                <Input 
                  placeholder="Season Invite Code" 
                  value={inviteCode}
                  onChange={(e) => setInviteCode(e.target.value)}
                  className="bg-background"
                  disabled={redeeming}
                />
                <Button type="submit" disabled={redeeming || !inviteCode.trim()}>
                  {redeeming ? '...' : 'Redeem'}
                </Button>
              </form>
              {redeemSuccess && (
                <p className="text-xs text-green-600 mt-2 font-medium">{redeemSuccess}</p>
              )}
            </div>
          </div>
        )}

        {error && (
          <div className="mb-4 p-4 bg-destructive/10 border border-destructive rounded-lg text-destructive">
            {error}
          </div>
        )}

        <div className="mb-6">
          <h2 className="text-xl font-semibold mb-4">Your Pass Requests</h2>

          {loading ? (
            <div className="space-y-4">
              {[...Array(2)].map((_, i) => (
                <div key={i} className="border rounded-lg p-4">
                  <div className="flex justify-between items-center mb-4">
                    <Skeleton className="h-6 w-48" />
                    <Skeleton className="h-6 w-20" />
                  </div>
                  <div className="space-y-3">
                    <div className="border rounded overflow-hidden">
                      <div className="bg-muted h-10 w-full" />
                      <div className="p-3 space-y-3">
                        <Skeleton className="h-8 w-full" />
                        <Skeleton className="h-8 w-full" />
                      </div>
                    </div>
                  </div>
                </div>
              ))}
            </div>
          ) : seasonsWithRequests.length === 0 ? (
            <div className="rounded-xl border bg-card/50 p-12 text-center text-muted-foreground">
              <div className="flex justify-center mb-4">
                <div className="p-3 rounded-full bg-muted">
                  <FileEdit className="w-8 h-8 opacity-20" />
                </div>
              </div>
              <h3 className="text-lg font-medium text-foreground mb-1">No Pass Requests</h3>
              <p className="max-w-xs mx-auto">
                You haven't submitted any pass requests yet. When you do, they will appear here.
              </p>
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
                          <TableHead>Promo Code</TableHead>
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
                              {request.promo_code ? (
                                <div className="flex items-center gap-2">
                                  <code className="px-1.5 py-0.5 bg-muted rounded text-xs font-mono">
                                    {request.promo_code}
                                  </code>
                                  <Tooltip>
                                    <TooltipTrigger asChild>
                                      <Button
                                        variant="ghost"
                                        size="sm"
                                        className="h-6 w-6 p-0"
                                        onClick={() => handleCopyPromoCode(request.promo_code!, request.id)}
                                      >
                                        {copiedId === request.id ? (
                                          <Check className="h-3.5 w-3.5 text-green-600" />
                                        ) : (
                                          <Copy className="h-3.5 w-3.5" />
                                        )}
                                      </Button>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                      <p>{copiedId === request.id ? 'Copied!' : 'Copy promo code to clipboard'}</p>
                                    </TooltipContent>
                                  </Tooltip>
                                </div>
                              ) : request.is_renewal && !request.renewal_order_number && new Date(season.early_spring_deadline) > now ? (
                                <div className="flex items-center gap-1.5">
                                  <span className="text-muted-foreground text-xs">
                                    Not needed until {new Date(season.early_spring_deadline).toLocaleDateString(undefined, { month: 'short', day: 'numeric' })}
                                  </span>
                                  <Button 
                                    variant="ghost" 
                                    size="sm" 
                                    className="h-5 w-5 p-0 text-muted-foreground hover:text-foreground"
                                    onClick={() => setInfoRequest({ ...request, season })}
                                  >
                                    <HelpCircle className="h-3.5 w-3.5" />
                                  </Button>
                                </div>
                              ) : (
                                <span className="text-muted-foreground text-xs">TBA</span>
                              )}
                            </TableCell>
                                                      <TableCell>
                                                        {request.redemption_date ? (
                                                          <Badge variant="default">Redeemed</Badge>
                                                        ) : request.renewal_order_number ? (
                                                          <Badge variant="default" className="bg-green-600 hover:bg-green-700 text-white border-transparent">Renewed</Badge>
                                                        ) : request.promo_code ? (
                                                          <Badge variant="secondary">Code Assigned</Badge>
                                                        ) : (
                                                          <Badge variant="outline">Pending</Badge>
                                                        )}
                                                      </TableCell>                            <TableCell>
                              <div className="flex items-center gap-2">
                                {request.is_renewal && (
                                  <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => handleOpenRenewalModal(request)}
                                  >
                                    <FileEdit className="w-4 h-4 mr-1" />
                                    {request.renewal_order_number ? 'Edit' : 'Report'} Renewal
                                  </Button>
                                )}
                                {!request.promo_code && !request.renewal_order_number && (
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

        {/* Renewal Information Modal */}
        <Dialog open={!!infoRequest} onOpenChange={(open) => !open && setInfoRequest(null)}>
          <DialogContent className="max-w-md">
            <DialogHeader>
              <DialogTitle>Renewal Information</DialogTitle>
            </DialogHeader>
            {infoRequest && (
              <RenewalInfo 
                request={infoRequest} 
                season={infoRequest.season} 
                formatDate={formatDate} 
              />
            )}
            <DialogFooter>
              <Button type="button" onClick={() => setInfoRequest(null)}>
                Got it
              </Button>
            </DialogFooter>
          </DialogContent>
        </Dialog>
      </div>
    </TooltipProvider>
  );
}

const dashboardElement = document.getElementById('dashboard');
if (dashboardElement) {
  createRoot(dashboardElement).render(<Dashboard />);
}
