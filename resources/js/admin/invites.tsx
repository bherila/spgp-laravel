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
import { Pencil, Archive, RotateCcw, Plus } from 'lucide-react';

interface InviteCode {
  id: number;
  invite_code: string;
  max_number_of_uses: number;
  usage_count: number;
  deleted_at: string | null;
  created_at: string;
  updated_at: string;
}

function AdminInvites() {
  const mount = document.getElementById('admin-invites');
  const csrfToken = mount?.getAttribute('data-csrf-token') || '';
  
  const [inviteCodes, setInviteCodes] = useState<InviteCode[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [includeArchived, setIncludeArchived] = useState(false);
  
  // Modal states
  const [createModalOpen, setCreateModalOpen] = useState(false);
  const [editModalOpen, setEditModalOpen] = useState(false);
  const [archiveModalOpen, setArchiveModalOpen] = useState(false);
  const [selectedCode, setSelectedCode] = useState<InviteCode | null>(null);
  
  // Form states
  const [formInviteCode, setFormInviteCode] = useState('');
  const [formMaxUses, setFormMaxUses] = useState('1');
  const [formSubmitting, setFormSubmitting] = useState(false);
  const [formError, setFormError] = useState<string | null>(null);

  const fetchInviteCodes = async () => {
    try {
      setLoading(true);
      const response = await fetch(`/admin/invites/list?include_archived=${includeArchived}`, {
        headers: {
          'Accept': 'application/json',
        },
      });
      if (!response.ok) throw new Error('Failed to fetch invite codes');
      const data = await response.json();
      setInviteCodes(data);
      setError(null);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'An error occurred');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchInviteCodes();
  }, [includeArchived]);

  const handleCreate = async (e: React.FormEvent) => {
    e.preventDefault();
    setFormSubmitting(true);
    setFormError(null);
    
    try {
      const response = await fetch('/admin/invites', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': csrfToken,
        },
        body: JSON.stringify({
          invite_code: formInviteCode,
          max_number_of_uses: parseInt(formMaxUses, 10),
        }),
      });
      
      if (!response.ok) {
        const data = await response.json();
        throw new Error(data.message || 'Failed to create invite code');
      }
      
      setCreateModalOpen(false);
      setFormInviteCode('');
      setFormMaxUses('1');
      fetchInviteCodes();
    } catch (err) {
      setFormError(err instanceof Error ? err.message : 'An error occurred');
    } finally {
      setFormSubmitting(false);
    }
  };

  const handleEdit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedCode) return;
    
    setFormSubmitting(true);
    setFormError(null);
    
    try {
      const response = await fetch(`/admin/invites/${selectedCode.id}`, {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': csrfToken,
        },
        body: JSON.stringify({
          invite_code: formInviteCode,
          max_number_of_uses: parseInt(formMaxUses, 10),
        }),
      });
      
      if (!response.ok) {
        const data = await response.json();
        throw new Error(data.message || 'Failed to update invite code');
      }
      
      setEditModalOpen(false);
      setSelectedCode(null);
      fetchInviteCodes();
    } catch (err) {
      setFormError(err instanceof Error ? err.message : 'An error occurred');
    } finally {
      setFormSubmitting(false);
    }
  };

  const handleArchive = async () => {
    if (!selectedCode) return;
    
    setFormSubmitting(true);
    setFormError(null);
    
    try {
      const response = await fetch(`/admin/invites/${selectedCode.id}`, {
        method: 'DELETE',
        headers: {
          'Accept': 'application/json',
          'X-CSRF-TOKEN': csrfToken,
        },
      });
      
      if (!response.ok) {
        const data = await response.json();
        throw new Error(data.message || 'Failed to archive invite code');
      }
      
      setArchiveModalOpen(false);
      setSelectedCode(null);
      fetchInviteCodes();
    } catch (err) {
      setFormError(err instanceof Error ? err.message : 'An error occurred');
    } finally {
      setFormSubmitting(false);
    }
  };

  const handleRestore = async (code: InviteCode) => {
    try {
      const response = await fetch(`/admin/invites/${code.id}/restore`, {
        method: 'POST',
        headers: {
          'Accept': 'application/json',
          'X-CSRF-TOKEN': csrfToken,
        },
      });
      
      if (!response.ok) {
        const data = await response.json();
        throw new Error(data.message || 'Failed to restore invite code');
      }
      
      fetchInviteCodes();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'An error occurred');
    }
  };

  const openEditModal = (code: InviteCode) => {
    setSelectedCode(code);
    setFormInviteCode(code.invite_code);
    setFormMaxUses(code.max_number_of_uses.toString());
    setFormError(null);
    setEditModalOpen(true);
  };

  const openArchiveModal = (code: InviteCode) => {
    setSelectedCode(code);
    setFormError(null);
    setArchiveModalOpen(true);
  };

  const openCreateModal = () => {
    setFormInviteCode('');
    setFormMaxUses('1');
    setFormError(null);
    setCreateModalOpen(true);
  };

  return (
    <div className="max-w-6xl mx-auto px-4 py-8">
      <div className="flex items-center justify-between mb-8">
        <div>
          <MainTitle>Invite Codes</MainTitle>
          <p className="text-muted-foreground mt-2">
            Manage invite codes for user registration.
          </p>
        </div>
        <Button onClick={openCreateModal}>
          <Plus className="w-4 h-4 mr-2" />
          Create Invite Code
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
          Show archived codes
        </label>
      </div>

      {error && (
        <div className="mb-4 p-4 bg-destructive/10 border border-destructive rounded-lg text-destructive">
          {error}
        </div>
      )}

      {loading ? (
        <div className="text-center py-8">Loading...</div>
      ) : (
        <div className="border rounded-lg">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Code</TableHead>
                <TableHead>Max Uses</TableHead>
                <TableHead>Current Uses</TableHead>
                <TableHead>Status</TableHead>
                <TableHead>Created</TableHead>
                <TableHead className="text-right">Actions</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {inviteCodes.length === 0 ? (
                <TableRow>
                  <TableCell colSpan={6} className="text-center py-8 text-muted-foreground">
                    No invite codes found.
                  </TableCell>
                </TableRow>
              ) : (
                inviteCodes.map((code) => (
                  <TableRow key={code.id} className={code.deleted_at ? 'opacity-60' : ''}>
                    <TableCell className="font-mono">{code.invite_code}</TableCell>
                    <TableCell>{code.max_number_of_uses}</TableCell>
                    <TableCell>{code.usage_count}</TableCell>
                    <TableCell>
                      {code.deleted_at ? (
                        <Badge variant="secondary">Archived</Badge>
                      ) : code.usage_count >= code.max_number_of_uses ? (
                        <Badge variant="destructive">Exhausted</Badge>
                      ) : (
                        <Badge variant="default">Active</Badge>
                      )}
                    </TableCell>
                    <TableCell>
                      {new Date(code.created_at).toLocaleDateString()}
                    </TableCell>
                    <TableCell className="text-right">
                      <div className="flex items-center justify-end gap-2">
                        {code.deleted_at ? (
                          <Button
                            variant="outline"
                            size="sm"
                            onClick={() => handleRestore(code)}
                          >
                            <RotateCcw className="w-4 h-4 mr-1" />
                            Restore
                          </Button>
                        ) : (
                          <>
                            <Button
                              variant="outline"
                              size="sm"
                              onClick={() => openEditModal(code)}
                            >
                              <Pencil className="w-4 h-4" />
                            </Button>
                            <Button
                              variant="outline"
                              size="sm"
                              onClick={() => openArchiveModal(code)}
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
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Create Invite Code</DialogTitle>
            <DialogDescription>
              Create a new invite code for user registration.
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
                <Label htmlFor="create-invite-code">Invite Code</Label>
                <Input
                  id="create-invite-code"
                  value={formInviteCode}
                  onChange={(e) => setFormInviteCode(e.target.value)}
                  placeholder="WELCOME2026"
                  required
                  maxLength={255}
                />
              </div>
              <div className="space-y-2">
                <Label htmlFor="create-max-uses">Maximum Uses</Label>
                <Input
                  id="create-max-uses"
                  type="number"
                  value={formMaxUses}
                  onChange={(e) => setFormMaxUses(e.target.value)}
                  min="1"
                  required
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
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Edit Invite Code</DialogTitle>
            <DialogDescription>
              Update the invite code details.
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
                <Label htmlFor="edit-invite-code">Invite Code</Label>
                <Input
                  id="edit-invite-code"
                  value={formInviteCode}
                  onChange={(e) => setFormInviteCode(e.target.value)}
                  required
                  maxLength={255}
                />
              </div>
              <div className="space-y-2">
                <Label htmlFor="edit-max-uses">Maximum Uses</Label>
                <Input
                  id="edit-max-uses"
                  type="number"
                  value={formMaxUses}
                  onChange={(e) => setFormMaxUses(e.target.value)}
                  min="1"
                  required
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
            <DialogTitle>Archive Invite Code</DialogTitle>
            <DialogDescription>
              Are you sure you want to archive the invite code "{selectedCode?.invite_code}"?
              This will prevent new users from using it, but it can be restored later.
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

const adminInvitesElement = document.getElementById('admin-invites');
if (adminInvitesElement) {
  createRoot(adminInvitesElement).render(<AdminInvites />);
}
