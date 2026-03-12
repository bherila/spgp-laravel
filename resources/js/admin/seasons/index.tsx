import '../../bootstrap';
import { createRoot } from 'react-dom/client';
import React, { useEffect, useState } from 'react';
import MainTitle from '@/components/MainTitle';
import { Button } from '@/components/ui/button';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Plus } from 'lucide-react';
import { Skeleton } from '@/components/ui/skeleton';
import type { Season } from './types';
import { SeasonTable } from './SeasonTable';
import { SeasonForm } from './SeasonForm';
import { Toaster } from '@/components/ui/sonner';

function AdminSeasons() {
  const mount = document.getElementById('admin-seasons');
  const csrfToken = mount?.getAttribute('data-csrf-token') || '';
  
  const [seasons, setSeasons] = useState<Season[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [includeArchived, setIncludeArchived] = useState(false);
  
  // Modal states
  const [seasonFormOpen, setSeasonFormOpen] = useState(false);
  const [archiveModalOpen, setArchiveModalOpen] = useState(false);
  const [selectedSeason, setSelectedSeason] = useState<Season | null>(null);
  const [submitting, setSubmitting] = useState(false);

  const fetchSeasons = async () => {
    try {
      setLoading(true);
      const response = await fetch(`/api/admin/seasons/list?include_archived=${includeArchived}`, {
        headers: { 'Accept': 'application/json' },
      });
      if (!response.ok) throw new Error('Failed to fetch seasons');
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
    fetchSeasons();
  }, [includeArchived]);

  const handleArchive = async () => {
    if (!selectedSeason) return;
    
    setSubmitting(true);
    try {
      const response = await fetch(`/api/admin/seasons/${selectedSeason.id}`, {
        method: 'DELETE',
        headers: {
          'Accept': 'application/json',
          'X-CSRF-TOKEN': csrfToken,
        },
      });
      
      if (!response.ok) {
        const data = await response.json();
        throw new Error(data.message || 'Failed to archive season');
      }
      
      setArchiveModalOpen(false);
      setSelectedSeason(null);
      fetchSeasons();
    } catch (err) {
      alert(err instanceof Error ? err.message : 'An error occurred');
    } finally {
      setSubmitting(false);
    }
  };

  const handleRestore = async (season: Season) => {
    try {
      const response = await fetch(`/api/admin/seasons/${season.id}/restore`, {
        method: 'POST',
        headers: {
          'Accept': 'application/json',
          'X-CSRF-TOKEN': csrfToken,
        },
      });
      
      if (!response.ok) {
        const data = await response.json();
        throw new Error(data.message || 'Failed to restore season');
      }
      
      fetchSeasons();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'An error occurred');
    }
  };

  return (
    <div className="max-w-6xl mx-auto px-4 py-8">
      <div className="flex items-center justify-between mb-8">
        <div>
          <MainTitle>Seasons</MainTitle>
          <p className="text-muted-foreground mt-2">
            Manage pass seasons and their deadlines.
          </p>
        </div>
        <Button onClick={() => { setSelectedSeason(null); setSeasonFormOpen(true); }}>
          <Plus className="w-4 h-4 mr-2" />
          Create Season
        </Button>
      </div>

      <div className="mb-4 flex items-center gap-2">
        {loading ? (
          <Skeleton className="h-5 w-48" />
        ) : (
          <>
            <input
              type="checkbox"
              id="includeArchived"
              checked={includeArchived}
              onChange={(e) => setIncludeArchived(e.target.checked)}
              className="h-4 w-4 rounded border-gray-300"
            />
            <label htmlFor="includeArchived" className="text-sm">
              Show archived seasons
            </label>
          </>
        )}
      </div>

      {error && (
        <div className="mb-4 p-4 bg-destructive/10 border border-destructive rounded-lg text-destructive">
          {error}
        </div>
      )}

      <SeasonTable
        seasons={seasons}
        loading={loading}
        onEdit={(season) => { setSelectedSeason(season); setSeasonFormOpen(true); }}
        onArchive={(season) => { setSelectedSeason(season); setArchiveModalOpen(true); }}
        onRestore={handleRestore}
      />

      <SeasonForm
        open={seasonFormOpen}
        onOpenChange={setSeasonFormOpen}
        season={selectedSeason}
        csrfToken={csrfToken}
        onSuccess={fetchSeasons}
      />

      <Toaster />

      {/* Archive Confirmation Modal */}
      <Dialog open={archiveModalOpen} onOpenChange={setArchiveModalOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Archive Season</DialogTitle>
            <DialogDescription>
              Are you sure you want to archive "{selectedSeason?.pass_name} {selectedSeason?.pass_year}"?
              This will hide it from users but it can be restored later.
              {selectedSeason && selectedSeason.pass_request_count > 0 && (
                <span className="block mt-2 text-amber-600">
                  Warning: This season has {selectedSeason.pass_request_count} pass request(s).
                </span>
              )}
            </DialogDescription>
          </DialogHeader>
          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => setArchiveModalOpen(false)}>
              Cancel
            </Button>
            <Button
              type="button"
              variant="destructive"
              onClick={handleArchive}
              disabled={submitting}
            >
              {submitting ? 'Archiving...' : 'Archive'}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
}

const adminSeasonsElement = document.getElementById('admin-seasons');
if (adminSeasonsElement) {
  createRoot(adminSeasonsElement).render(<AdminSeasons />);
}
