import '../bootstrap';

import { ArrowLeft, Download, Upload } from 'lucide-react';
import React, { useEffect, useState } from 'react';
import { createRoot } from 'react-dom/client';

import MainTitle from '@/components/MainTitle';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';

interface PromoCode {
  promo_code: string;
  season_id: number;
  start_date: string;
  expiration_date: string;
  country: 'USA' | 'Canada' | null;
  is_suspended: boolean;
  is_assigned: boolean;
  created_at: string;
}

function formatDate(dateStr: string | null): string {
  if (!dateStr) return '—';
  return new Date(dateStr).toLocaleDateString();
}

function PromoCodeRepositoryAdmin() {
  const mount = document.getElementById('admin-promo-code-repository');
  const csrfToken = mount?.getAttribute('data-csrf-token') || '';
  const seasonId = mount?.getAttribute('data-season-id') || '';
  const seasonName = mount?.getAttribute('data-season-name') || '';
  const seasonYear = mount?.getAttribute('data-season-year') || '';

  const [promoCodes, setPromoCodes] = useState<PromoCode[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);

  // Import modal state
  const [importOpen, setImportOpen] = useState(false);
  const [importTsv, setImportTsv] = useState('');
  const [importCountry, setImportCountry] = useState<'USA' | 'Canada'>('USA');
  const [importing, setImporting] = useState(false);
  const [importErrors, setImportErrors] = useState<string[]>([]);

  const fetchData = async () => {
    try {
      setLoading(true);
      const response = await fetch(`/api/admin/seasons/${seasonId}/promo-codes/list`, {
        headers: { 'Accept': 'application/json' },
      });
      if (!response.ok) throw new Error('Failed to fetch promo codes');
      const data = await response.json();
      setPromoCodes(data.promo_codes || []);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'An error occurred');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchData();
  }, []);

  const handleImport = async () => {
    if (!importTsv.trim()) return;

    setImporting(true);
    setImportErrors([]);
    setError(null);
    setSuccess(null);

    try {
      const response = await fetch(`/api/admin/seasons/${seasonId}/promo-codes/import`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': csrfToken,
        },
        body: JSON.stringify({ tsv: importTsv, country: importCountry }),
      });

      const data = await response.json();

      if (!response.ok) {
        throw new Error(data.message || 'Import failed');
      }

      setSuccess(data.message);
      setImportErrors(data.errors || []);
      setImportTsv('');
      setImportOpen(false);
      fetchData();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'An error occurred');
    } finally {
      setImporting(false);
    }
  };

  const usaCount = promoCodes.filter(c => c.country === 'USA' && !c.is_suspended && !c.is_assigned).length;
  const canadaCount = promoCodes.filter(c => c.country === 'Canada' && !c.is_suspended && !c.is_assigned).length;

  return (
    <div className="max-w-6xl mx-auto px-4 py-8">
      <div className="mb-6">
        <Button variant="ghost" size="sm" asChild className="mb-4">
          <a href="/admin/seasons">
            <ArrowLeft className="w-4 h-4 mr-1" />
            Back to Seasons
          </a>
        </Button>
        <MainTitle>Promo Code Repository</MainTitle>
        <p className="text-muted-foreground mt-1">
          {seasonName} {seasonYear} — Manage unassigned promo codes
        </p>
      </div>

      {error && (
        <Alert variant="destructive" className="mb-4">
          <AlertDescription>{error}</AlertDescription>
        </Alert>
      )}

      {success && (
        <Alert className="mb-4">
          <AlertDescription>{success}</AlertDescription>
        </Alert>
      )}

      {importErrors.length > 0 && (
        <Alert variant="destructive" className="mb-4">
          <AlertDescription>
            <p className="font-semibold mb-1">Import warnings:</p>
            <ul className="list-disc list-inside">
              {importErrors.map((e, i) => <li key={i}>{e}</li>)}
            </ul>
          </AlertDescription>
        </Alert>
      )}

      <div className="flex items-center justify-between mb-4">
        <div className="flex items-center gap-4 text-sm text-muted-foreground">
          <span>USA: <strong>{usaCount}</strong> available</span>
          <span>Canada: <strong>{canadaCount}</strong> available</span>
          <span>Total: <strong>{promoCodes.length}</strong></span>
        </div>
        <div className="flex items-center gap-2">
          <Button variant="outline" asChild>
            <a href={`/admin/seasons/${seasonId}/pass-requests`}>
              View Pass Requests
            </a>
          </Button>
          <Button onClick={() => setImportOpen(true)}>
            <Upload className="w-4 h-4 mr-2" />
            Import Codes
          </Button>
        </div>
      </div>

      <div className="border rounded-lg">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Promo Code</TableHead>
              <TableHead>Country</TableHead>
              <TableHead>Start Date</TableHead>
              <TableHead>Expiration Date</TableHead>
              <TableHead>Status</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {loading ? (
              [...Array(5)].map((_, i) => (
                <TableRow key={i}>
                  <TableCell><Skeleton className="h-4 w-32" /></TableCell>
                  <TableCell><Skeleton className="h-4 w-16" /></TableCell>
                  <TableCell><Skeleton className="h-4 w-24" /></TableCell>
                  <TableCell><Skeleton className="h-4 w-24" /></TableCell>
                  <TableCell><Skeleton className="h-6 w-20" /></TableCell>
                </TableRow>
              ))
            ) : promoCodes.length === 0 ? (
              <TableRow>
                <TableCell colSpan={5} className="text-center py-8 text-muted-foreground">
                  No promo codes found. Import some codes to get started.
                </TableCell>
              </TableRow>
            ) : (
              promoCodes.map(code => (
                <TableRow key={code.promo_code}>
                  <TableCell className="font-mono text-sm">{code.promo_code}</TableCell>
                  <TableCell>{code.country ?? '—'}</TableCell>
                  <TableCell>{formatDate(code.start_date)}</TableCell>
                  <TableCell>{formatDate(code.expiration_date)}</TableCell>
                  <TableCell>
                    {code.is_suspended ? (
                      <Badge variant="destructive">Suspended</Badge>
                    ) : code.is_assigned ? (
                      <Badge variant="default">Assigned</Badge>
                    ) : (
                      <Badge variant="secondary">Available</Badge>
                    )}
                  </TableCell>
                </TableRow>
              ))
            )}
          </TableBody>
        </Table>
      </div>

      {/* Import Modal */}
      <Dialog open={importOpen} onOpenChange={setImportOpen}>
        <DialogContent className="max-w-lg">
          <DialogHeader>
            <DialogTitle>Import Promo Codes</DialogTitle>
            <DialogDescription>
              Paste TSV data from Excel with columns: <strong>Code</strong> and optionally <strong>Date Added</strong>.
              If Date Added is omitted or blank, the current server date will be used.
              The expiration date will be set to September 1st following the start date.
            </DialogDescription>
          </DialogHeader>

          <div className="space-y-4 py-2">
            <div className="space-y-1">
              <Label>Country</Label>
              <Select value={importCountry} onValueChange={(v) => setImportCountry(v as 'USA' | 'Canada')}>
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="USA">USA</SelectItem>
                  <SelectItem value="Canada">Canada</SelectItem>
                </SelectContent>
              </Select>
            </div>

            <div className="space-y-1">
              <Label>TSV Data (paste from Excel)</Label>
              <Textarea
                className="font-mono text-xs min-h-[200px]"
                placeholder={"Code\tDate Added\nABCD1234\t1/15/2026\nEFGH5678"}
                value={importTsv}
                onChange={(e) => setImportTsv(e.target.value)}
              />
              <p className="text-xs text-muted-foreground">
                Include or exclude the header row — it will be detected automatically.
              </p>
            </div>
          </div>

          <DialogFooter>
            <Button variant="outline" onClick={() => setImportOpen(false)}>
              Cancel
            </Button>
            <Button onClick={handleImport} disabled={importing || !importTsv.trim()}>
              {importing ? 'Importing...' : (
                <>
                  <Download className="w-4 h-4 mr-2" />
                  Import
                </>
              )}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
}

const mountEl = document.getElementById('admin-promo-code-repository');
if (mountEl) {
  createRoot(mountEl).render(<PromoCodeRepositoryAdmin />);
}
