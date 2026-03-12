import '../bootstrap';
import { createRoot } from 'react-dom/client';
import React, { useEffect, useState } from 'react';
import MainTitle from '@/components/MainTitle';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Skeleton } from '@/components/ui/skeleton';
import { Checkbox } from '@/components/ui/checkbox';
import { ArrowLeft, Send, Trash2, XCircle, FileSpreadsheet } from 'lucide-react';

interface User {
  id: number;
  name: string;
  email: string;
}

interface SeasonPassType {
  id: number;
  pass_type_name: string;
}

interface PassRequest {
  id: string;
  user_id: number;
  user: User;
  passholder_email: string;
  pass_type: string;
  season_pass_type: SeasonPassType | null;
  passholder_first_name: string;
  passholder_last_name: string;
  passholder_birth_date: string;
  is_renewal: boolean;
  renewal_pass_id: string | null;
  renewal_order_number: string | null;
  promo_code: string | null;
  assign_code_date: string | null;
  email_notify_time: string | null;
  created_at: string;
}

interface Season {
  id: number;
  pass_name: string;
  pass_year: number;
  spreadsheet_url: string | null;
}

function formatDate(dateStr: string | null): string {
  if (!dateStr) return '—';
  return new Date(dateStr).toLocaleDateString();
}

function SeasonPassRequestsAdmin() {
  const mount = document.getElementById('admin-season-pass-requests');
  const csrfToken = mount?.getAttribute('data-csrf-token') || '';
  const seasonId = mount?.getAttribute('data-season-id') || '';
  const seasonName = mount?.getAttribute('data-season-name') || '';
  const seasonYear = mount?.getAttribute('data-season-year') || '';

  const [season, setSeason] = useState<Season | null>(null);
  const [passRequests, setPassRequests] = useState<PassRequest[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [selectedIds, setSelectedIds] = useState<Set<string>>(new Set());

  // Filters
  const [hideExtraColumns, setHideExtraColumns] = useState(false);
  const [showRecentOnly, setShowRecentOnly] = useState(false);

  // Modal states
  const [assignCodesModalOpen, setAssignCodesModalOpen] = useState(false);
  const [sendEmailsModalOpen, setSendEmailsModalOpen] = useState(false);
  const [clearCodesModalOpen, setClearCodesModalOpen] = useState(false);
  const [codes, setCodes] = useState('');
  const [actionLoading, setActionLoading] = useState(false);
  const [actionMessage, setActionMessage] = useState<string | null>(null);

  // Send emails state
  const [forceSend, setForceSend] = useState(false);
  const [sendProgress, setSendProgress] = useState<{ current: number; total: number } | null>(null);
  const [sendResultModalOpen, setSendResultModalOpen] = useState(false);
  const [sendResult, setSendResult] = useState<{ succeeded: number; failed: number } | null>(null);

  const fetchData = async () => {
    try {
      setLoading(true);
      const params = new URLSearchParams();
      if (showRecentOnly) params.set('recent_only', 'true');

      const response = await fetch(`/api/admin/seasons/${seasonId}/pass-requests/list?${params}`, {
        headers: { 'Accept': 'application/json' },
      });
      if (!response.ok) throw new Error('Failed to fetch data');
      const data = await response.json();
      setSeason(data.season);
      setPassRequests(data.pass_requests);
      setError(null);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'An error occurred');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchData();
  }, [showRecentOnly]);

  const toggleSelect = (id: string) => {
    const newSelected = new Set(selectedIds);
    if (newSelected.has(id)) {
      newSelected.delete(id);
    } else {
      newSelected.add(id);
    }
    setSelectedIds(newSelected);
  };

  const selectAll = (requests: PassRequest[]) => {
    const allIds = new Set(requests.map((r) => r.id));
    setSelectedIds(allIds);
  };

  const deselectAll = () => {
    setSelectedIds(new Set());
  };

  const handleAssignCodes = async () => {
    setActionLoading(true);
    setActionMessage(null);

    try {
      const response = await fetch(`/api/admin/seasons/${seasonId}/pass-requests/assign-codes`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': csrfToken,
        },
        body: JSON.stringify({
          pass_request_ids: Array.from(selectedIds),
          codes,
        }),
      });

      const data = await response.json();
      if (!response.ok) throw new Error(data.message || 'Failed to assign codes');

      setActionMessage(data.message);
      setCodes('');
      setAssignCodesModalOpen(false);
      fetchData();
    } catch (err) {
      setActionMessage(err instanceof Error ? err.message : 'An error occurred');
    } finally {
      setActionLoading(false);
    }
  };

  const handleClearCodes = async () => {
    setActionLoading(true);
    setActionMessage(null);

    try {
      const response = await fetch(`/api/admin/seasons/${seasonId}/pass-requests/clear-codes`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': csrfToken,
        },
        body: JSON.stringify({
          pass_request_ids: Array.from(selectedIds),
        }),
      });

      const data = await response.json();
      if (!response.ok) throw new Error(data.message || 'Failed to clear codes');

      setActionMessage(data.message);
      setClearCodesModalOpen(false);
      fetchData();
    } catch (err) {
      setActionMessage(err instanceof Error ? err.message : 'An error occurred');
    } finally {
      setActionLoading(false);
    }
  };

  const handleSendEmails = async () => {
    const ids = Array.from(selectedIds);
    const total = ids.length;

    setActionLoading(true);
    setSendProgress({ current: 0, total });
    setSendEmailsModalOpen(false);

    let succeeded = 0;
    let failed = 0;

    for (let i = 0; i < ids.length; i++) {
      setSendProgress({ current: i + 1, total });
      try {
        const response = await fetch(`/api/admin/seasons/${seasonId}/pass-requests/send-emails`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
          },
          body: JSON.stringify({
            pass_request_ids: [ids[i]],
            force_send: forceSend,
          }),
        });

        const data = await response.json();
        if (!response.ok) {
          failed++;
        } else {
          const sent: number = data.sent ?? 0;
          succeeded += sent;
          if (sent === 0) {
            // No email sent (already emailed and not force-sending, or no promo code)
            failed++;
          }
        }
      } catch {
        failed++;
      }
    }

    setActionLoading(false);
    setSendProgress(null);
    setSendResult({ succeeded, failed });
    setSendResultModalOpen(true);
    fetchData();
  };

  const handleDelete = async (id: string) => {
    if (!confirm('Are you sure you want to delete this pass request?')) return;

    try {
      const response = await fetch(`/api/admin/pass-requests/${id}/admin`, {
        method: 'DELETE',
        headers: {
          'Accept': 'application/json',
          'X-CSRF-TOKEN': csrfToken,
        },
      });

      if (!response.ok) {
        const data = await response.json();
        throw new Error(data.message || 'Failed to delete');
      }

      fetchData();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'An error occurred');
    }
  };

  // Filter requests
  const newRequests = passRequests
    .filter((r) => !r.is_renewal)
    .sort((a, b) => (a.promo_code ? 1 : 0) - (b.promo_code ? 1 : 0));

  const renewalRequests = passRequests
    .filter((r) => r.is_renewal)
    .sort((a, b) => (a.promo_code ? 1 : 0) - (b.promo_code ? 1 : 0));

  const renderTable = (requests: PassRequest[]) => (
    <Table>
      <TableHeader>
        <TableRow>
          <TableHead className="w-12">
            <Checkbox
              checked={requests.length > 0 && requests.every((r) => selectedIds.has(r.id))}
              onCheckedChange={(checked) => {
                if (checked) {
                  selectAll(requests);
                } else {
                  deselectAll();
                }
              }}
            />
          </TableHead>
          <TableHead>Passholder</TableHead>
          <TableHead>Email</TableHead>
          <TableHead>Pass Type</TableHead>
          {!hideExtraColumns && <TableHead>Birth Date</TableHead>}
          {!hideExtraColumns && <TableHead>Requester</TableHead>}
          <TableHead>Promo Code</TableHead>
          <TableHead>Assigned</TableHead>
          <TableHead>Emailed</TableHead>
          <TableHead className="text-right">Actions</TableHead>
        </TableRow>
      </TableHeader>
      <TableBody>
        {requests.length === 0 ? (
          <TableRow>
            <TableCell colSpan={hideExtraColumns ? 8 : 10} className="text-center py-8 text-muted-foreground">
              No pass requests found.
            </TableCell>
          </TableRow>
        ) : (
          requests.map((request) => (
            <TableRow key={request.id} className={request.promo_code ? 'bg-primary/5' : ''}>
              <TableCell>
                <Checkbox
                  checked={selectedIds.has(request.id)}
                  onCheckedChange={() => toggleSelect(request.id)}
                />
              </TableCell>
              <TableCell className="font-medium">
                {request.passholder_first_name} {request.passholder_last_name}
                {request.is_renewal && (
                  <Badge variant="outline" className="ml-2">Renewal</Badge>
                )}
              </TableCell>
              <TableCell>{request.passholder_email}</TableCell>
              <TableCell>{request.season_pass_type?.pass_type_name ?? request.pass_type}</TableCell>
              {!hideExtraColumns && <TableCell>{formatDate(request.passholder_birth_date)}</TableCell>}
              {!hideExtraColumns && <TableCell>{request.user?.name || '—'}</TableCell>}
              <TableCell>
                {request.promo_code ? (
                  <code className="px-1.5 py-0.5 bg-muted text-foreground border rounded text-xs font-bold">
                    {request.promo_code}
                  </code>
                ) : (
                  <span className="text-muted-foreground">—</span>
                )}
              </TableCell>
              <TableCell>{formatDate(request.assign_code_date)}</TableCell>
              <TableCell>
                {request.email_notify_time ? (
                  <Badge variant="default">Sent</Badge>
                ) : request.promo_code ? (
                  <Badge variant="secondary">Pending</Badge>
                ) : (
                  <span className="text-muted-foreground">—</span>
                )}
              </TableCell>
              <TableCell className="text-right">
                <Button
                  variant="ghost"
                  size="sm"
                  onClick={() => handleDelete(request.id)}
                >
                  <Trash2 className="w-4 h-4 text-destructive" />
                </Button>
              </TableCell>
            </TableRow>
          ))
        )}
      </TableBody>
    </Table>
  );

  return (
    <div className="max-w-7xl mx-auto px-4 py-8">
      <div className="flex items-center gap-4 mb-8">
        <Button variant="ghost" size="sm" asChild>
          <a href="/admin/seasons">
            <ArrowLeft className="w-4 h-4 mr-2" />
            Back to Seasons
          </a>
        </Button>
      </div>

      <div className="flex items-center justify-between mb-8">
        <div>
          <MainTitle>
            {seasonName} {seasonYear} Pass Requests
          </MainTitle>
          <p className="text-muted-foreground mt-2">
            Manage pass requests for this season. ({passRequests.length} total)
          </p>
        </div>
        {season?.spreadsheet_url && (
          <Button variant="outline" asChild>
            <a href={season.spreadsheet_url} target="_blank" rel="noopener noreferrer">
              <FileSpreadsheet className="w-4 h-4 mr-2" />
              Spreadsheet
            </a>
          </Button>
        )}
      </div>

      {actionMessage && (
        <div className="mb-4 p-4 bg-primary/10 border border-primary rounded-lg text-primary">
          {actionMessage}
        </div>
      )}

      {error && (
        <div className="mb-4 p-4 bg-destructive/10 border border-destructive rounded-lg text-destructive">
          {error}
        </div>
      )}

      {/* Send progress bar */}
      {sendProgress && (
        <div className="mb-4 p-4 bg-primary/10 border border-primary rounded-lg">
          <p className="text-sm text-primary mb-2">
            Sending emails… {sendProgress.current} / {sendProgress.total}
          </p>
          <div className="w-full bg-primary/20 rounded-full h-2">
            <div
              className="bg-primary h-2 rounded-full transition-all duration-300"
              style={{ width: `${(sendProgress.current / sendProgress.total) * 100}%` }}
            />
          </div>
        </div>
      )}

      {/* Actions Bar */}
      <div className="mb-4 flex flex-wrap items-center gap-4">
        <div className="flex items-center gap-4">
          <label className="flex items-center gap-2 text-sm">
            <input
              type="checkbox"
              checked={hideExtraColumns}
              onChange={(e) => setHideExtraColumns(e.target.checked)}
              className="h-4 w-4 rounded border-input"
            />
            Hide Extra Columns
          </label>
          <label className="flex items-center gap-2 text-sm">
            <input
              type="checkbox"
              checked={showRecentOnly}
              onChange={(e) => setShowRecentOnly(e.target.checked)}
              className="h-4 w-4 rounded border-input"
            />
            Show Recently Assigned Only
          </label>
        </div>

        <div className="flex-1" />

        <div className="flex items-center gap-2">
          <span className="text-sm text-muted-foreground">
            {selectedIds.size} selected
          </span>
          <Button
            variant="outline"
            size="sm"
            onClick={() => setAssignCodesModalOpen(true)}
            disabled={selectedIds.size === 0}
          >
            Assign Codes
          </Button>
          <Button
            variant="outline"
            size="sm"
            onClick={() => setClearCodesModalOpen(true)}
            disabled={selectedIds.size === 0}
          >
            <XCircle className="w-4 h-4 mr-1" />
            Clear Codes
          </Button>
          <Button
            variant="outline"
            size="sm"
            onClick={() => setSendEmailsModalOpen(true)}
            disabled={selectedIds.size === 0 || actionLoading}
          >
            <Send className="w-4 h-4 mr-1" />
            Send Emails
          </Button>
        </div>
      </div>

      {loading ? (
        <div className="space-y-4">
          <Skeleton className="h-10 w-full" />
          <Skeleton className="h-64 w-full" />
        </div>
      ) : (
        <Tabs defaultValue="new" className="mt-6">
          <TabsList>
            <TabsTrigger value="new">New ({newRequests.length})</TabsTrigger>
            <TabsTrigger value="renewal">Renewal ({renewalRequests.length})</TabsTrigger>
          </TabsList>

          <TabsContent value="new" className="border rounded-lg mt-4">
            {renderTable(newRequests)}
          </TabsContent>
          <TabsContent value="renewal" className="border rounded-lg mt-4">
            {renderTable(renewalRequests)}
          </TabsContent>
        </Tabs>
      )}

      {/* Assign Codes Modal */}
      <Dialog open={assignCodesModalOpen} onOpenChange={setAssignCodesModalOpen}>
        <DialogContent className="max-w-lg">
          <DialogHeader>
            <DialogTitle>Assign Promo Codes</DialogTitle>
            <DialogDescription>
              Paste promo codes (one per line) to assign to {selectedIds.size} selected pass requests.
            </DialogDescription>
          </DialogHeader>
          <div className="space-y-4 py-4">
            <div className="space-y-2">
              <Label htmlFor="codes">Promo Codes (one per line)</Label>
              <textarea
                id="codes"
                value={codes}
                onChange={(e) => setCodes(e.target.value)}
                className="w-full h-48 rounded-md border border-input bg-background px-3 py-2 text-sm font-mono"
                placeholder="CODE1&#10;CODE2&#10;CODE3"
              />
              <p className="text-sm text-muted-foreground">
                {codes.split('\n').filter((c) => c.trim()).length} codes entered,{' '}
                {selectedIds.size} selected
              </p>
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setAssignCodesModalOpen(false)}>
              Cancel
            </Button>
            <Button onClick={handleAssignCodes} disabled={actionLoading}>
              {actionLoading ? 'Assigning...' : 'Assign Codes'}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Clear Codes Modal */}
      <Dialog open={clearCodesModalOpen} onOpenChange={setClearCodesModalOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Clear Promo Codes</DialogTitle>
            <DialogDescription>
              Are you sure you want to clear promo codes from {selectedIds.size} selected pass requests?
              This will also reset the assign date.
            </DialogDescription>
          </DialogHeader>
          <DialogFooter>
            <Button variant="outline" onClick={() => setClearCodesModalOpen(false)}>
              Cancel
            </Button>
            <Button variant="destructive" onClick={handleClearCodes} disabled={actionLoading}>
              {actionLoading ? 'Clearing...' : 'Clear Codes'}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Send Emails Modal */}
      <Dialog open={sendEmailsModalOpen} onOpenChange={setSendEmailsModalOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Send Email Notifications</DialogTitle>
            <DialogDescription>
              Send promo code notification emails to {selectedIds.size} selected passholders.
              Each email will be sent to the passholder and to your account email.
            </DialogDescription>
          </DialogHeader>
          <div className="py-4">
            <label className="flex items-center gap-2 text-sm cursor-pointer">
              <Checkbox
                checked={forceSend}
                onCheckedChange={(checked) => setForceSend(checked === true)}
              />
              Force-send even if already emailed
            </label>
            {!forceSend && (
              <p className="mt-2 text-xs text-muted-foreground">
                Only requests with promo codes that haven&apos;t been emailed yet will receive emails.
              </p>
            )}
            {forceSend && (
              <p className="mt-2 text-xs text-amber-600 dark:text-amber-400">
                Warning: emails will be sent even to passholders who already received one.
              </p>
            )}
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setSendEmailsModalOpen(false)}>
              Cancel
            </Button>
            <Button onClick={handleSendEmails} disabled={actionLoading}>
              <Send className="w-4 h-4 mr-2" />
              Send Emails
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Send Result Modal */}
      <Dialog open={sendResultModalOpen} onOpenChange={setSendResultModalOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Email Send Complete</DialogTitle>
            <DialogDescription>
              The email send operation has finished.
            </DialogDescription>
          </DialogHeader>
          {sendResult && (
            <div className="py-4 space-y-2">
              <p className="text-sm">
                <span className="font-semibold text-green-600 dark:text-green-400">{sendResult.succeeded}</span>{' '}
                email{sendResult.succeeded !== 1 ? 's' : ''} sent successfully.
              </p>
              {sendResult.failed > 0 && (
                <p className="text-sm">
                  <span className="font-semibold text-destructive">{sendResult.failed}</span>{' '}
                  request{sendResult.failed !== 1 ? 's' : ''} skipped (already emailed without force-send) or failed.
                </p>
              )}
            </div>
          )}
          <DialogFooter>
            <Button onClick={() => setSendResultModalOpen(false)}>OK</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
}

const adminSeasonPassRequestsElement = document.getElementById('admin-season-pass-requests');
if (adminSeasonPassRequestsElement) {
  createRoot(adminSeasonPassRequestsElement).render(<SeasonPassRequestsAdmin />);
}
