import React, { useState, useEffect } from 'react';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { Season } from './types';
import { formatDateTimeLocal, toIsoString } from './utils';

interface SeasonFormProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  season: Season | null;
  csrfToken: string;
  onSuccess: () => void;
}

export function SeasonForm({ open, onOpenChange, season, csrfToken, onSuccess }: SeasonFormProps) {
  const [passName, setPassName] = useState('');
  const [passYear, setPassYear] = useState(new Date().getFullYear().toString());
  const [startDate, setStartDate] = useState('');
  const [earlySpringDeadline, setEarlySpringDeadline] = useState('');
  const [finalDeadline, setFinalDeadline] = useState('');
  const [spreadsheetUrl, setSpreadsheetUrl] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (season) {
      setPassName(season.pass_name);
      setPassYear(season.pass_year.toString());
      setStartDate(formatDateTimeLocal(season.start_date));
      setEarlySpringDeadline(formatDateTimeLocal(season.early_spring_deadline));
      setFinalDeadline(formatDateTimeLocal(season.final_deadline));
      setSpreadsheetUrl(season.spreadsheet_url || '');
    } else {
      setPassName('');
      setPassYear(new Date().getFullYear().toString());
      setStartDate('');
      setEarlySpringDeadline('');
      setFinalDeadline('');
      setSpreadsheetUrl('');
    }
    setError(null);
  }, [season, open]);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setSubmitting(true);
    setError(null);
    
    try {
      const url = season ? `/api/admin/seasons/${season.id}` : '/api/admin/seasons';
      const method = season ? 'PUT' : 'POST';
      
      const response = await fetch(url, {
        method,
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': csrfToken,
        },
        body: JSON.stringify({
          pass_name: passName,
          pass_year: parseInt(passYear, 10),
          start_date: toIsoString(startDate),
          early_spring_deadline: toIsoString(earlySpringDeadline),
          final_deadline: toIsoString(finalDeadline),
          spreadsheet_url: spreadsheetUrl || null,
        }),
      });
      
      if (!response.ok) {
        const data = await response.json();
        throw new Error(data.message || `Failed to ${season ? 'update' : 'create'} season`);
      }
      
      onOpenChange(false);
      onSuccess();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'An error occurred');
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-md">
        <DialogHeader>
          <DialogTitle>{season ? 'Edit' : 'Create'} Season</DialogTitle>
          <DialogDescription>
            {season ? 'Update the season details.' : 'Create a new pass season with deadlines.'}
          </DialogDescription>
        </DialogHeader>
        <form onSubmit={handleSubmit}>
          <div className="space-y-4 py-4">
            {error && (
              <div className="p-3 bg-destructive/10 border border-destructive rounded text-destructive text-sm">
                {error}
              </div>
            )}
            <div className="space-y-2">
              <Label htmlFor="pass-name">Pass Name</Label>
              <Input
                id="pass-name"
                value={passName}
                onChange={(e) => setPassName(e.target.value)}
                placeholder="TBD Pass"
                required
                maxLength={100}
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="pass-year">Year</Label>
              <Input
                id="pass-year"
                type="number"
                value={passYear}
                onChange={(e) => setPassYear(e.target.value)}
                min="2000"
                max="2100"
                required
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="start-date">Start Date</Label>
              <Input
                id="start-date"
                type="datetime-local"
                value={startDate}
                onChange={(e) => setStartDate(e.target.value)}
                required
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="early-spring">Early Spring Deadline</Label>
              <Input
                id="early-spring"
                type="datetime-local"
                value={earlySpringDeadline}
                onChange={(e) => setEarlySpringDeadline(e.target.value)}
                required
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="final">Final Deadline</Label>
              <Input
                id="final"
                type="datetime-local"
                value={finalDeadline}
                onChange={(e) => setFinalDeadline(e.target.value)}
                required
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="spreadsheet">Spreadsheet URL (Optional)</Label>
              <Input
                id="spreadsheet"
                type="url"
                value={spreadsheetUrl}
                onChange={(e) => setSpreadsheetUrl(e.target.value)}
                placeholder="https://..."
                maxLength={500}
              />
            </div>
          </div>
          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
              Cancel
            </Button>
            <Button type="submit" disabled={submitting}>
              {submitting ? (season ? 'Saving...' : 'Creating...') : (season ? 'Save Changes' : 'Create')}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}
