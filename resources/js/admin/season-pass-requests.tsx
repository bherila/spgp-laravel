import '../bootstrap';

import { ArrowLeft, Copy, FileSpreadsheet, Send, Tag, Trash2, XCircle } from 'lucide-react';
import React, { useEffect, useState } from 'react';
import { createRoot } from 'react-dom/client';

import MainTitle from '@/components/MainTitle';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Skeleton } from '@/components/ui/skeleton';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { formatDateOnly, formatDateTime } from '@/lib/dateHelpers';
import { formatBirthDateUTC, getAgeGroup } from '@/lib/passRequestHelpers';

import {
  assignCodes,
  autoAssign,
  bulkDeletePassRequests,
  clearCodes,
  fetchPassRequests,
  fetchRepoCount,
  type PassRequest,
  type Season,
  sendEmail,
} from './passRequestsAdminApi';
import {
  AssignCodesDialog,
  DeletePassRequestsDialog,
  SendEmailsDialog,
  SendResultDialog,
  UnassignCodesDialog,
} from './PassRequestsAdminDialogs';

function formatBirthDateLocal(dateStr: string | null): string {
  if (!dateStr) return '—';
  return formatBirthDateUTC(dateStr) || '—';
}

function SeasonPassRequestsAdmin() {
  const mount = document.getElementById('admin-season-pass-requests');
  const csrfToken = mount?.getAttribute('data-csrf-token') ?? '';
  const seasonId = mount?.getAttribute('data-season-id') ?? '';
  const seasonName = mount?.getAttribute('data-season-name') ?? '';
  const seasonYear = mount?.getAttribute('data-season-year') ?? '';

  const [season, setSeason] = useState<Season | null>(null);
  const [passRequests, setPassRequests] = useState<PassRequest[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [selectedIds, setSelectedIds] = useState<Set<string>>(new Set());
  const [lastSelectedIndex, setLastSelectedIndex] = useState<number | null>(null);

  // Filters
  const [hideExtraColumns, setHideExtraColumns] = useState(false);
  const [showRecentOnly, setShowRecentOnly] = useState(false);
  const [filterMode, setFilterMode] = useState<'new' | 'renewal' | 'both'>('new');

  // Modal states
  const [assignCodesModalOpen, setAssignCodesModalOpen] = useState(false);
  const [sendEmailsModalOpen, setSendEmailsModalOpen] = useState(false);
  const [clearCodesModalOpen, setClearCodesModalOpen] = useState(false);
  const [deleteModalOpen, setDeleteModalOpen] = useState(false);
  const [codes, setCodes] = useState('');
  const [actionLoading, setActionLoading] = useState(false);
  const [actionMessage, setActionMessage] = useState<string | null>(null);
  const [repoCount, setRepoCount] = useState<number | null>(null);

  // Send emails state
  const [forceSend, setForceSend] = useState(false);
  const [sendProgress, setSendProgress] = useState<{ current: number; total: number } | null>(null);
  const [sendResultModalOpen, setSendResultModalOpen] = useState(false);
  const [sendResult, setSendResult] = useState<{ succeeded: number; failed: number } | null>(null);

  const loadData = async (): Promise<PassRequest[]> => {
    try {
      setLoading(true);
      const [data, count] = await Promise.all([
        fetchPassRequests(seasonId, showRecentOnly),
        fetchRepoCount(seasonId),
      ]);
      setSeason(data.season);
      setPassRequests(data.pass_requests);
      setRepoCount(count);
      setError(null);
      return data.pass_requests;
    } catch (err) {
      setError(err instanceof Error ? err.message : 'An error occurred');
      return [];
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    void loadData();
  }, [showRecentOnly]);

  const shiftKeyRef = React.useRef(false);

  const toggleSelect = (id: string, shiftKey: boolean, allRequests: PassRequest[]) => {
    const currentIndex = allRequests.findIndex((r) => r.id === id);
    const newSelected = new Set(selectedIds);

    if (shiftKey && lastSelectedIndex !== null && currentIndex !== -1) {
      const start = Math.min(lastSelectedIndex, currentIndex);
      const end = Math.max(lastSelectedIndex, currentIndex);
      for (let i = start; i <= end; i++) {
        newSelected.add(allRequests[i]?.id ?? '');
      }
    } else {
      if (newSelected.has(id)) {
        newSelected.delete(id);
      } else {
        newSelected.add(id);
      }
      setLastSelectedIndex(currentIndex);
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
      const data = await assignCodes(seasonId, Array.from(selectedIds), codes, csrfToken);
      setActionMessage(data.message);
      setCodes('');
      setAssignCodesModalOpen(false);
      void loadData();
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
      const data = await clearCodes(seasonId, Array.from(selectedIds), csrfToken);
      setActionMessage(data.message);
      setClearCodesModalOpen(false);
      void loadData();
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
        const data = await sendEmail(seasonId, ids[i]!, forceSend, csrfToken);
        const sent: number = data.sent ?? 0;
        succeeded += sent;
        if (sent === 0) {
          failed++;
        }
      } catch {
        failed++;
      }
    }

    setActionLoading(false);
    setSendProgress(null);
    setSendResult({ succeeded, failed });
    setSendResultModalOpen(true);
    void loadData();
  };

  const handleBulkDelete = async () => {
    setActionLoading(true);
    setActionMessage(null);
    try {
      const data = await bulkDeletePassRequests(seasonId, Array.from(selectedIds), csrfToken);
      setActionMessage(data.message);
      setDeleteModalOpen(false);
      setSelectedIds(new Set());
      void loadData();
    } catch (err) {
      setActionMessage(err instanceof Error ? err.message : 'An error occurred');
      setDeleteModalOpen(false);
    } finally {
      setActionLoading(false);
    }
  };

  const handleAutoAssign = async () => {
    setActionLoading(true);
    setActionMessage(null);
    const preAssignedIds = new Set(passRequests.filter((r) => r.assign_code_date).map((r) => r.id));
    try {
      const data = await autoAssign(seasonId, csrfToken);
      setActionMessage(data.message);
      const updated = await loadData();
      const newlyAssigned = new Set(
        updated.filter((r) => r.assign_code_date && !preAssignedIds.has(r.id)).map((r) => r.id),
      );
      if (newlyAssigned.size > 0) {
        setSelectedIds(newlyAssigned);
      }
    } catch (err) {
      setActionMessage(err instanceof Error ? err.message : 'An error occurred');
    } finally {
      setActionLoading(false);
    }
  };

  // Filter requests
  const newRequests = passRequests
    .filter((r) => !r.is_renewal)
    .sort((a, b) => (a.promo_code ? 1 : 0) - (b.promo_code ? 1 : 0));

  const renewalRequests = passRequests
    .filter((r) => r.is_renewal)
    .sort((a, b) => (a.promo_code ? 1 : 0) - (b.promo_code ? 1 : 0));

  const filteredRequests =
    filterMode === 'new'
      ? newRequests
      : filterMode === 'renewal'
        ? renewalRequests
        : [...passRequests].sort((a, b) => (a.promo_code ? 1 : 0) - (b.promo_code ? 1 : 0));

  // Quick-selection helpers (operate on filtered/visible requests)
  const selectWithCode = () =>
    setSelectedIds(new Set(filteredRequests.filter((r) => r.promo_code).map((r) => r.id)));
  const selectWithoutCode = () =>
    setSelectedIds(new Set(filteredRequests.filter((r) => !r.promo_code).map((r) => r.id)));
  const selectWithCodeEmailed = () =>
    setSelectedIds(
      new Set(filteredRequests.filter((r) => r.promo_code && r.email_notify_time).map((r) => r.id)),
    );
  const selectWithCodeNotEmailed = () =>
    setSelectedIds(
      new Set(filteredRequests.filter((r) => r.promo_code && !r.email_notify_time).map((r) => r.id)),
    );

  const escapeTSVCell = (value: string | null | undefined) => {
    const normalized = String(value ?? '').replace(/[\t\r\n]+/g, ' ');
    return /^[=+\-@\t\r\n]/.test(normalized) ? `'${normalized}` : normalized;
  };

  const handleCopyTSV = async () => {
    // Sort selected requests oldest-first (by created_at asc) for Excel paste appending
    const selectedRequests = passRequests
      .filter((r) => selectedIds.has(r.id))
      .sort((a, b) => new Date(a.created_at).getTime() - new Date(b.created_at).getTime());
    const rows = selectedRequests.map((r) => [
      formatDateTime(r.created_at),
      r.passholder_first_name,
      r.passholder_last_name,
      formatBirthDateUTC(r.passholder_birth_date),
      r.passholder_email,
      r.season_pass_type?.pass_type_name ?? r.pass_type,
      getAgeGroup(r.passholder_birth_date),
      r.promo_code ?? '',
    ]);
    const tsv = rows.map((row) => row.map((cell) => escapeTSVCell(cell)).join('\t')).join('\n');
    try {
      await navigator.clipboard.writeText(tsv);
      setActionMessage(`Copied ${selectedRequests.length} row${selectedRequests.length !== 1 ? 's' : ''} to clipboard as TSV.`);
    } catch {
      setError('Failed to copy to clipboard. Please check browser permissions.');
    }
  };

  const renderTable = (requests: PassRequest[]) => (
    <Table>
      <TableHeader>
        <TableRow>
          <TableHead className="w-10 py-2">
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
          <TableHead className="py-2">Date</TableHead>
          <TableHead className="py-2">First Name</TableHead>
          <TableHead className="py-2">Last Name</TableHead>
          <TableHead className="py-2">Birthday</TableHead>
          <TableHead className="py-2">Email Address</TableHead>
          <TableHead className="py-2">Type of Pass</TableHead>
          <TableHead className="py-2">Age Group</TableHead>
          <TableHead className="py-2">Promo Code</TableHead>
          <TableHead className="py-2">Country</TableHead>
          {!hideExtraColumns && <TableHead className="py-2">Requester</TableHead>}
          {!hideExtraColumns && <TableHead className="py-2">Assigned</TableHead>}
          {!hideExtraColumns && <TableHead className="py-2">Emailed</TableHead>}
        </TableRow>
      </TableHeader>
      <TableBody>
        {requests.length === 0 ? (
          <TableRow>
            <TableCell colSpan={hideExtraColumns ? 10 : 13} className="text-center py-6 text-muted-foreground">
              No pass requests found.
            </TableCell>
          </TableRow>
        ) : (
          requests.map((request) => (
            <TableRow key={request.id} className={request.promo_code ? 'bg-primary/5' : ''}>
              <TableCell className="py-1">
                <Checkbox
                  checked={selectedIds.has(request.id)}
                  onMouseDown={(e: React.MouseEvent) => { shiftKeyRef.current = e.shiftKey; }}
                  onCheckedChange={() => toggleSelect(request.id, shiftKeyRef.current, requests)}
                />
              </TableCell>
              <TableCell className="py-1 text-sm text-muted-foreground whitespace-nowrap">
                {formatDateTime(request.created_at)}
                {request.is_renewal && (
                  <Badge variant="outline" className="ml-2">Renewal</Badge>
                )}
              </TableCell>
              <TableCell className="py-1 font-medium">{request.passholder_first_name}</TableCell>
              <TableCell className="py-1 font-medium">{request.passholder_last_name}</TableCell>
              <TableCell className="py-1">{formatBirthDateLocal(request.passholder_birth_date)}</TableCell>
              <TableCell className="py-1">{request.passholder_email}</TableCell>
              <TableCell className="py-1">{request.season_pass_type?.pass_type_name ?? request.pass_type}</TableCell>
              <TableCell className="py-1">{getAgeGroup(request.passholder_birth_date)}</TableCell>
              <TableCell className="py-1">
                {request.promo_code ? (
                  <code className="px-1.5 py-0.5 bg-muted text-foreground border rounded text-xs font-bold">
                    {request.promo_code}
                  </code>
                ) : (
                  <span className="text-muted-foreground">—</span>
                )}
              </TableCell>
              <TableCell className="py-1">
                {request.country ? (
                  <span className="text-sm">{request.country}</span>
                ) : (
                  <span className="text-muted-foreground text-xs">—</span>
                )}
              </TableCell>
              {!hideExtraColumns && <TableCell className="py-1">{request.user?.name || '—'}</TableCell>}
              {!hideExtraColumns && <TableCell className="py-1">{formatDateOnly(request.assign_code_date)}</TableCell>}
              {!hideExtraColumns && (
                <TableCell className="py-1">
                  {request.email_notify_time ? (
                    <Badge variant="success">Sent</Badge>
                  ) : request.promo_code ? (
                    <Badge variant="secondary">Pending</Badge>
                  ) : (
                    <span className="text-muted-foreground">—</span>
                  )}
                </TableCell>
              )}
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
        <Button variant="outline" asChild>
          <a href={`/admin/seasons/${seasonId}/promo-codes`}>
            <Tag className="w-4 h-4 mr-2" />
            Promo Code Repository
          </a>
        </Button>
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
            <Tag className="w-4 h-4 mr-1" />
            Assign Codes
          </Button>
          <Tooltip>
            <TooltipTrigger asChild>
              <span tabIndex={repoCount === 0 ? 0 : undefined}>
                <Button
                  variant="outline"
                  size="sm"
                  onClick={handleAutoAssign}
                  disabled={actionLoading || repoCount === 0}
                >
                  <Tag className="w-4 h-4 mr-1" />
                  Auto Assign from Repository
                </Button>
              </span>
            </TooltipTrigger>
            {repoCount === 0 && (
              <TooltipContent>No available promo codes in repository</TooltipContent>
            )}
          </Tooltip>
          <Button
            variant="outline"
            size="sm"
            onClick={() => setClearCodesModalOpen(true)}
            disabled={selectedIds.size === 0}
          >
            <XCircle className="w-4 h-4 mr-1" />
            Unassign Codes
          </Button>
          <Tooltip>
            <TooltipTrigger asChild>
              <span tabIndex={selectedIds.size === 0 ? 0 : undefined}>
                <Button
                  variant="outline"
                  size="sm"
                  onClick={handleCopyTSV}
                  disabled={selectedIds.size === 0}
                >
                  <Copy className="w-4 h-4 mr-1" />
                  Copy TSV
                </Button>
              </span>
            </TooltipTrigger>
            {selectedIds.size === 0 && (
              <TooltipContent>Select rows to copy TSV</TooltipContent>
            )}
          </Tooltip>
          <Button
            variant="outline"
            size="sm"
            onClick={() => setSendEmailsModalOpen(true)}
            disabled={selectedIds.size === 0 || actionLoading}
          >
            <Send className="w-4 h-4 mr-1" />
            Send Emails
          </Button>
          <Button
            variant="outline"
            size="sm"
            onClick={() => setDeleteModalOpen(true)}
            disabled={selectedIds.size === 0 || actionLoading}
          >
            <Trash2 className="w-4 h-4 mr-1 text-destructive" />
            Delete
          </Button>
        </div>
      </div>

      {loading ? (
        <div className="space-y-4">
          <Skeleton className="h-10 w-full" />
          <Skeleton className="h-64 w-full" />
        </div>
      ) : (
        <div className="mt-6">
          {/* New / Renewal / Both filter ButtonGroup */}
          <div className="flex items-center gap-0 mb-4">
            <Button
              variant={filterMode === 'new' ? 'default' : 'outline'}
              size="sm"
              className="rounded-r-none"
              onClick={() => setFilterMode('new')}
            >
              New ({newRequests.length})
            </Button>
            <Button
              variant={filterMode === 'renewal' ? 'default' : 'outline'}
              size="sm"
              className="rounded-none border-l-0"
              onClick={() => setFilterMode('renewal')}
            >
              Renewal ({renewalRequests.length})
            </Button>
            <Button
              variant={filterMode === 'both' ? 'default' : 'outline'}
              size="sm"
              className="rounded-l-none border-l-0"
              onClick={() => setFilterMode('both')}
            >
              Both ({passRequests.length})
            </Button>
          </div>

          {/* Quick-selection buttons */}
          <div className="flex flex-wrap items-center gap-2 mb-3">
            <span className="text-xs text-muted-foreground mr-1">Quick select:</span>
            <Button variant="ghost" size="sm" className="h-7 text-xs" onClick={deselectAll}>
              Unselect All
            </Button>
            <Button variant="ghost" size="sm" className="h-7 text-xs" onClick={selectWithCode}>
              Has Code
            </Button>
            <Button variant="ghost" size="sm" className="h-7 text-xs" onClick={selectWithoutCode}>
              No Code
            </Button>
            <Button variant="ghost" size="sm" className="h-7 text-xs" onClick={selectWithCodeEmailed}>
              Has Code + Emailed
            </Button>
            <Button variant="ghost" size="sm" className="h-7 text-xs" onClick={selectWithCodeNotEmailed}>
              Has Code + Not Emailed
            </Button>
          </div>

          <div className="border rounded-lg">
            {renderTable(filteredRequests)}
          </div>
        </div>
      )}

      {/* Dialogs */}
      <AssignCodesDialog
        open={assignCodesModalOpen}
        onOpenChange={setAssignCodesModalOpen}
        selectedCount={selectedIds.size}
        codes={codes}
        onCodesChange={setCodes}
        onConfirm={handleAssignCodes}
        loading={actionLoading}
      />

      <UnassignCodesDialog
        open={clearCodesModalOpen}
        onOpenChange={setClearCodesModalOpen}
        selectedCount={selectedIds.size}
        onConfirm={handleClearCodes}
        loading={actionLoading}
      />

      <DeletePassRequestsDialog
        open={deleteModalOpen}
        onOpenChange={setDeleteModalOpen}
        selectedCount={selectedIds.size}
        onConfirm={handleBulkDelete}
        loading={actionLoading}
      />

      <SendEmailsDialog
        open={sendEmailsModalOpen}
        onOpenChange={setSendEmailsModalOpen}
        selectedCount={selectedIds.size}
        forceSend={forceSend}
        onForceSendChange={setForceSend}
        onConfirm={handleSendEmails}
        loading={actionLoading}
      />

      <SendResultDialog
        open={sendResultModalOpen}
        onOpenChange={setSendResultModalOpen}
        result={sendResult}
      />
    </div>
  );
}

const adminSeasonPassRequestsElement = document.getElementById('admin-season-pass-requests');
if (adminSeasonPassRequestsElement) {
  createRoot(adminSeasonPassRequestsElement).render(<SeasonPassRequestsAdmin />);
}
