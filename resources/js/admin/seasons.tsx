import '../bootstrap';
import { createRoot } from 'react-dom/client';
import React, { useEffect, useState } from 'react';
import MainTitle from '@/components/MainTitle';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
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
import { Skeleton } from '@/components/ui/skeleton';
import { Pencil, Archive, RotateCcw, Plus, Users } from 'lucide-react';

interface Season {
  id: number;
  pass_name: string;
  pass_year: number;
  start_date: string;
  early_spring_deadline: string;
  final_deadline: string;
  spreadsheet_url: string | null;
  pass_request_count: number;
  deleted_at: string | null;
  created_at: string;
  updated_at: string;
}

function formatDate(dateStr: string): string {
  return new Date(dateStr).toLocaleDateString();
}

function formatDateTimeLocal(dateStr: string): string {
  const date = new Date(dateStr);
  return date.toISOString().slice(0, 16);
}

function AdminSeasons() {
  const mount = document.getElementById('admin-seasons');
  const csrfToken = mount?.getAttribute('data-csrf-token') || '';
  
  const [seasons, setSeasons] = useState<Season[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [includeArchived, setIncludeArchived] = useState(false);
  
  // Modal states
  const [createModalOpen, setCreateModalOpen] = useState(false);
  const [editModalOpen, setEditModalOpen] = useState(false);
  const [archiveModalOpen, setArchiveModalOpen] = useState(false);
  const [selectedSeason, setSelectedSeason] = useState<Season | null>(null);
  
  // Form states
  const [formPassName, setFormPassName] = useState('');
  const [formPassYear, setFormPassYear] = useState(new Date().getFullYear().toString());
  const [formStartDate, setFormStartDate] = useState('');
  const [formEarlySpringDeadline, setFormEarlySpringDeadline] = useState('');
  const [formFinalDeadline, setFormFinalDeadline] = useState('');
  const [formSpreadsheetUrl, setFormSpreadsheetUrl] = useState('');
  const [formSubmitting, setFormSubmitting] = useState(false);
  const [formError, setFormError] = useState<string | null>(null);

  const fetchSeasons = async () => {
    try {
      setLoading(true);
      const response = await fetch(`/admin/seasons/list?include_archived=${includeArchived}`, {
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

  const handleCreate = async (e: React.FormEvent) => {
    e.preventDefault();
    setFormSubmitting(true);
    setFormError(null);
    
    try {
      const response = await fetch('/admin/seasons', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': csrfToken,
        },
        body: JSON.stringify({
          pass_name: formPassName,
          pass_year: parseInt(formPassYear, 10),
          start_date: formStartDate,
          early_spring_deadline: formEarlySpringDeadline,
          final_deadline: formFinalDeadline,
          spreadsheet_url: formSpreadsheetUrl || null,
        }),
      });
      
      if (!response.ok) {
        const data = await response.json();
        throw new Error(data.message || 'Failed to create season');
      }
      
      setCreateModalOpen(false);
      resetForm();
      fetchSeasons();
    } catch (err) {
      setFormError(err instanceof Error ? err.message : 'An error occurred');
    } finally {
      setFormSubmitting(false);
    }
  };

  const handleEdit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedSeason) return;
    
    setFormSubmitting(true);
    setFormError(null);
    
    try {
      const response = await fetch(`/admin/seasons/${selectedSeason.id}`, {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': csrfToken,
        },
        body: JSON.stringify({
          pass_name: formPassName,
          pass_year: parseInt(formPassYear, 10),
          start_date: formStartDate,
          early_spring_deadline: formEarlySpringDeadline,
          final_deadline: formFinalDeadline,
          spreadsheet_url: formSpreadsheetUrl || null,
        }),
      });
      
      if (!response.ok) {
        const data = await response.json();
        throw new Error(data.message || 'Failed to update season');
      }
      
      setEditModalOpen(false);
      setSelectedSeason(null);
      fetchSeasons();
    } catch (err) {
      setFormError(err instanceof Error ? err.message : 'An error occurred');
    } finally {
      setFormSubmitting(false);
    }
  };

  const handleArchive = async () => {
    if (!selectedSeason) return;
    
    setFormSubmitting(true);
    setFormError(null);
    
    try {
      const response = await fetch(`/admin/seasons/${selectedSeason.id}`, {
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
      setFormError(err instanceof Error ? err.message : 'An error occurred');
    } finally {
      setFormSubmitting(false);
    }
  };

  const handleRestore = async (season: Season) => {
    try {
      const response = await fetch(`/admin/seasons/${season.id}/restore`, {
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

  const resetForm = () => {
    setFormPassName('');
    setFormPassYear(new Date().getFullYear().toString());
    setFormStartDate('');
    setFormEarlySpringDeadline('');
    setFormFinalDeadline('');
    setFormSpreadsheetUrl('');
    setFormError(null);
  };

  const openEditModal = (season: Season) => {
    setSelectedSeason(season);
    setFormPassName(season.pass_name);
    setFormPassYear(season.pass_year.toString());
    setFormStartDate(formatDateTimeLocal(season.start_date));
    setFormEarlySpringDeadline(formatDateTimeLocal(season.early_spring_deadline));
    setFormFinalDeadline(formatDateTimeLocal(season.final_deadline));
    setFormSpreadsheetUrl(season.spreadsheet_url || '');
    setFormError(null);
    setEditModalOpen(true);
  };

  const openArchiveModal = (season: Season) => {
    setSelectedSeason(season);
    setFormError(null);
    setArchiveModalOpen(true);
  };

  const openCreateModal = () => {
    resetForm();
    setCreateModalOpen(true);
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
        <Button onClick={openCreateModal}>
          <Plus className="w-4 h-4 mr-2" />
          Create Season
        </Button>
      </div>

      <div className="mb-4 flex items-center gap-2">
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
      </div>

      {error && (
        <div className="mb-4 p-4 bg-destructive/10 border border-destructive rounded-lg text-destructive">
          {error}
        </div>
      )}

      {loading ? (
        <div className="space-y-4">
          <Skeleton className="h-10 w-full" />
          <Skeleton className="h-64 w-full" />
        </div>
      ) : (
        <div className="border rounded-lg">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Name</TableHead>
                <TableHead>Year</TableHead>
                <TableHead>Start Date</TableHead>
                <TableHead>Early Spring Deadline</TableHead>
                <TableHead>Final Deadline</TableHead>
                <TableHead>Requests</TableHead>
                <TableHead>Status</TableHead>
                <TableHead className="text-right">Actions</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {seasons.length === 0 ? (
                <TableRow>
                  <TableCell colSpan={8} className="text-center py-8 text-muted-foreground">
                    No seasons found.
                  </TableCell>
                </TableRow>
              ) : (
                seasons.map((season) => (
                  <TableRow key={season.id} className={season.deleted_at ? 'opacity-60' : ''}>
                    <TableCell className="font-medium">{season.pass_name}</TableCell>
                    <TableCell>{season.pass_year}</TableCell>
                    <TableCell>{formatDate(season.start_date)}</TableCell>
                    <TableCell>{formatDate(season.early_spring_deadline)}</TableCell>
                    <TableCell>{formatDate(season.final_deadline)}</TableCell>
                    <TableCell>
                      <a 
                        href={`/admin/seasons/${season.id}/pass-requests`}
                        className="text-primary hover:underline"
                      >
                        {season.pass_request_count}
                      </a>
                    </TableCell>
                    <TableCell>
                      {season.deleted_at ? (
                        <Badge variant="secondary">Archived</Badge>
                      ) : new Date(season.final_deadline) < new Date() ? (
                        <Badge variant="destructive">Ended</Badge>
                      ) : (
                        <Badge variant="default">Active</Badge>
                      )}
                    </TableCell>
                    <TableCell className="text-right">
                      <div className="flex items-center justify-end gap-2">
                        {season.deleted_at ? (
                          <Button
                            variant="outline"
                            size="sm"
                            onClick={() => handleRestore(season)}
                          >
                            <RotateCcw className="w-4 h-4 mr-1" />
                            Restore
                          </Button>
                        ) : (
                          <>
                            <Button
                              variant="outline"
                              size="sm"
                              asChild
                            >
                              <a href={`/admin/seasons/${season.id}/pass-requests`}>
                                <Users className="w-4 h-4" />
                              </a>
                            </Button>
                            <Button
                              variant="outline"
                              size="sm"
                              onClick={() => openEditModal(season)}
                            >
                              <Pencil className="w-4 h-4" />
                            </Button>
                            <Button
                              variant="outline"
                              size="sm"
                              onClick={() => openArchiveModal(season)}
                            >
                              <Archive className="w-4 h-4" />
                            </Button>
                          </>
                        )}
                      </div>
                    </TableCell>
                  </TableRow>
                ))
              )}
            </TableBody>
          </Table>
        </div>
      )}

      {/* Create Modal */}
      <Dialog open={createModalOpen} onOpenChange={setCreateModalOpen}>
        <DialogContent className="max-w-md">
          <DialogHeader>
            <DialogTitle>Create Season</DialogTitle>
            <DialogDescription>
              Create a new pass season with deadlines.
            </DialogDescription>
          </DialogHeader>
          <form onSubmit={handleCreate}>
            <div className="space-y-4 py-4">
              {formError && (
                <div className="p-3 bg-destructive/10 border border-destructive rounded text-destructive text-sm">
                  {formError}
                </div>
              )}
              <div className="space-y-2">
                <Label htmlFor="create-pass-name">Pass Name</Label>
                <Input
                  id="create-pass-name"
                  value={formPassName}
                  onChange={(e) => setFormPassName(e.target.value)}
                  placeholder="Ikon Pass"
                  required
                  maxLength={100}
                />
              </div>
              <div className="space-y-2">
                <Label htmlFor="create-pass-year">Year</Label>
                <Input
                  id="create-pass-year"
                  type="number"
                  value={formPassYear}
                  onChange={(e) => setFormPassYear(e.target.value)}
                  min="2000"
                  max="2100"
                  required
                />
              </div>
              <div className="space-y-2">
                <Label htmlFor="create-start-date">Start Date</Label>
                <Input
                  id="create-start-date"
                  type="datetime-local"
                  value={formStartDate}
                  onChange={(e) => setFormStartDate(e.target.value)}
                  required
                />
              </div>
              <div className="space-y-2">
                <Label htmlFor="create-early-spring">Early Spring Deadline</Label>
                <Input
                  id="create-early-spring"
                  type="datetime-local"
                  value={formEarlySpringDeadline}
                  onChange={(e) => setFormEarlySpringDeadline(e.target.value)}
                  required
                />
              </div>
              <div className="space-y-2">
                <Label htmlFor="create-final">Final Deadline</Label>
                <Input
                  id="create-final"
                  type="datetime-local"
                  value={formFinalDeadline}
                  onChange={(e) => setFormFinalDeadline(e.target.value)}
                  required
                />
              </div>
              <div className="space-y-2">
                <Label htmlFor="create-spreadsheet">Alterra Spreadsheet URL (Optional)</Label>
                <Input
                  id="create-spreadsheet"
                  type="url"
                  value={formSpreadsheetUrl}
                  onChange={(e) => setFormSpreadsheetUrl(e.target.value)}
                  placeholder="https://..."
                  maxLength={500}
                />
              </div>
            </div>
            <DialogFooter>
              <Button type="button" variant="outline" onClick={() => setCreateModalOpen(false)}>
                Cancel
              </Button>
              <Button type="submit" disabled={formSubmitting}>
                {formSubmitting ? 'Creating...' : 'Create'}
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>

      {/* Edit Modal */}
      <Dialog open={editModalOpen} onOpenChange={setEditModalOpen}>
        <DialogContent className="max-w-md">
          <DialogHeader>
            <DialogTitle>Edit Season</DialogTitle>
            <DialogDescription>
              Update the season details.
            </DialogDescription>
          </DialogHeader>
          <form onSubmit={handleEdit}>
            <div className="space-y-4 py-4">
              {formError && (
                <div className="p-3 bg-destructive/10 border border-destructive rounded text-destructive text-sm">
                  {formError}
                </div>
              )}
              <div className="space-y-2">
                <Label htmlFor="edit-pass-name">Pass Name</Label>
                <Input
                  id="edit-pass-name"
                  value={formPassName}
                  onChange={(e) => setFormPassName(e.target.value)}
                  required
                  maxLength={100}
                />
              </div>
              <div className="space-y-2">
                <Label htmlFor="edit-pass-year">Year</Label>
                <Input
                  id="edit-pass-year"
                  type="number"
                  value={formPassYear}
                  onChange={(e) => setFormPassYear(e.target.value)}
                  min="2000"
                  max="2100"
                  required
                />
              </div>
              <div className="space-y-2">
                <Label htmlFor="edit-start-date">Start Date</Label>
                <Input
                  id="edit-start-date"
                  type="datetime-local"
                  value={formStartDate}
                  onChange={(e) => setFormStartDate(e.target.value)}
                  required
                />
              </div>
              <div className="space-y-2">
                <Label htmlFor="edit-early-spring">Early Spring Deadline</Label>
                <Input
                  id="edit-early-spring"
                  type="datetime-local"
                  value={formEarlySpringDeadline}
                  onChange={(e) => setFormEarlySpringDeadline(e.target.value)}
                  required
                />
              </div>
              <div className="space-y-2">
                <Label htmlFor="edit-final">Final Deadline</Label>
                <Input
                  id="edit-final"
                  type="datetime-local"
                  value={formFinalDeadline}
                  onChange={(e) => setFormFinalDeadline(e.target.value)}
                  required
                />
              </div>
              <div className="space-y-2">
                <Label htmlFor="edit-spreadsheet">Alterra Spreadsheet URL (Optional)</Label>
                <Input
                  id="edit-spreadsheet"
                  type="url"
                  value={formSpreadsheetUrl}
                  onChange={(e) => setFormSpreadsheetUrl(e.target.value)}
                  placeholder="https://..."
                  maxLength={500}
                />
              </div>
            </div>
            <DialogFooter>
              <Button type="button" variant="outline" onClick={() => setEditModalOpen(false)}>
                Cancel
              </Button>
              <Button type="submit" disabled={formSubmitting}>
                {formSubmitting ? 'Saving...' : 'Save Changes'}
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>

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
          {formError && (
            <div className="p-3 bg-destructive/10 border border-destructive rounded text-destructive text-sm">
              {formError}
            </div>
          )}
          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => setArchiveModalOpen(false)}>
              Cancel
            </Button>
            <Button
              type="button"
              variant="destructive"
              onClick={handleArchive}
              disabled={formSubmitting}
            >
              {formSubmitting ? 'Archiving...' : 'Archive'}
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
