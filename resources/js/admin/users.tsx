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
import { Pencil, Trash2, Key } from 'lucide-react';

interface User {
  id: number;
  name: string;
  email: string;
  is_admin: boolean;
  pass_request_count: number;
  invite_code?: {
    id: number;
    code: string;
  } | null;
  last_login_at: string | null;
  created_at: string;
  updated_at: string;
}

function formatDate(dateStr: string | null): string {
  if (!dateStr) return 'Never';
  return new Date(dateStr).toLocaleDateString();
}

function AdminUsers() {
  const mount = document.getElementById('admin-users');
  const csrfToken = mount?.getAttribute('data-csrf-token') || '';
  const currentUserId = parseInt(mount?.getAttribute('data-current-user-id') || '0', 10);
  
  const [users, setUsers] = useState<User[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  
  // Modal states
  const [editModalOpen, setEditModalOpen] = useState(false);
  const [deleteModalOpen, setDeleteModalOpen] = useState(false);
  const [selectedUser, setSelectedUser] = useState<User | null>(null);
  
  // Form states
  const [formName, setFormName] = useState('');
  const [formEmail, setFormEmail] = useState('');
  const [formIsAdmin, setFormIsAdmin] = useState(false);
  const [formPassword, setFormPassword] = useState('');
  const [formSubmitting, setFormSubmitting] = useState(false);
  const [formError, setFormError] = useState<string | null>(null);

  const fetchUsers = async () => {
    try {
      setLoading(true);
      const response = await fetch('/api/admin/users/list', {
        headers: { 'Accept': 'application/json' },
      });
      if (!response.ok) throw new Error('Failed to fetch users');
      const data = await response.json();
      setUsers(data);
      setError(null);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'An error occurred');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchUsers();
  }, []);

  const handleEdit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedUser) return;
    
    setFormSubmitting(true);
    setFormError(null);
    
    try {
      const body: Record<string, unknown> = {
        name: formName,
        email: formEmail,
        is_admin: formIsAdmin,
      };
      
      if (formPassword) {
        body.password = formPassword;
      }
      
      const response = await fetch(`/api/admin/users/${selectedUser.id}`, {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': csrfToken,
        },
        body: JSON.stringify(body),
      });
      
      if (!response.ok) {
        const data = await response.json();
        throw new Error(data.message || 'Failed to update user');
      }
      
      setEditModalOpen(false);
      setSelectedUser(null);
      fetchUsers();
    } catch (err) {
      setFormError(err instanceof Error ? err.message : 'An error occurred');
    } finally {
      setFormSubmitting(false);
    }
  };

  const handleDelete = async () => {
    if (!selectedUser) return;
    
    setFormSubmitting(true);
    
    try {
      const response = await fetch(`/api/admin/users/${selectedUser.id}`, {
        method: 'DELETE',
        headers: {
          'Accept': 'application/json',
      });
      
      if (!response.ok) {
        const data = await response.json();
        throw new Error(data.message || 'Failed to delete user');
      }
      
      setDeleteModalOpen(false);
      setSelectedUser(null);
      fetchUsers();
    } catch (err) {
      setFormError(err instanceof Error ? err.message : 'An error occurred');
    } finally {
      setFormSubmitting(false);
    }
  };

  const openEditModal = (user: User) => {
    setSelectedUser(user);
    setFormName(user.name);
    setFormEmail(user.email);
    setFormIsAdmin(user.is_admin);
    setFormPassword('');
    setFormError(null);
    setEditModalOpen(true);
  };

  const openDeleteModal = (user: User) => {
    setSelectedUser(user);
    setFormError(null);
    setDeleteModalOpen(true);
  };

  const handleImpersonate = async (user: User) => {
    if (!confirm(`Login as ${user.name}? You will need to log out and back in to return to your admin account.`)) {
      return;
    }

    try {
      const response = await fetch(`/api/admin/users/${user.id}/impersonate`, {
        method: 'POST',
        headers: {
          'Accept': 'application/json',
          'X-CSRF-TOKEN': csrfToken,
        },
      });

      const data = await response.json();
      if (!response.ok) {
        throw new Error(data.message || 'Failed to impersonate user');
      }

      // Redirect to dashboard as the impersonated user
      window.location.href = data.redirect || '/dashboard';
    } catch (err) {
      setError(err instanceof Error ? err.message : 'An error occurred');
    }
  };

  return (
    <div className="max-w-6xl mx-auto px-4 py-8">
      <div className="flex items-center justify-between mb-8">
        <div>
          <MainTitle>Users</MainTitle>
          <p className="text-muted-foreground mt-2">
            Manage user accounts and permissions.
          </p>
        </div>
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
                <TableHead>Email</TableHead>
                <TableHead>Role</TableHead>
                <TableHead>Invite Code</TableHead>
                <TableHead>Pass Requests</TableHead>
                <TableHead>Last Login</TableHead>
                <TableHead>Registered</TableHead>
                <TableHead className="text-right">Actions</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {users.length === 0 ? (
                <TableRow>
                  <TableCell colSpan={8} className="text-center py-8 text-muted-foreground">
                    No users found.
                  </TableCell>
                </TableRow>
              ) : (
                users.map((user) => (
                  <TableRow key={user.id}>
                    <TableCell className="font-medium">{user.name}</TableCell>
                    <TableCell>{user.email}</TableCell>
                    <TableCell>
                      {user.is_admin ? (
                        <Badge variant="default">Admin</Badge>
                      ) : (
                        <Badge variant="secondary">User</Badge>
                      )}
                    </TableCell>
                    <TableCell>
                      {user.invite_code ? (
                        <code className="px-1.5 py-0.5 bg-muted rounded text-xs">
                          {user.invite_code.code}
                        </code>
                      ) : (
                        <span className="text-muted-foreground">—</span>
                      )}
                    </TableCell>
                    <TableCell>{user.pass_request_count}</TableCell>
                    <TableCell>{formatDate(user.last_login_at)}</TableCell>
                    <TableCell>{formatDate(user.created_at)}</TableCell>
                    <TableCell className="text-right">
                      <div className="flex items-center justify-end gap-2">
                        <Button
                          variant="outline"
                          size="sm"
                          onClick={() => openEditModal(user)}
                        >
                          <Pencil className="w-4 h-4" />
                        </Button>
                        {user.id !== currentUserId && (
                          <>
                            <Button
                              variant="outline"
                              size="sm"
                              onClick={() => handleImpersonate(user)}
                              title="Login as this user"
                            >
                              <Key className="w-4 h-4" />
                            </Button>
                            <Button
                              variant="outline"
                              size="sm"
                              onClick={() => openDeleteModal(user)}
                            >
                              <Trash2 className="w-4 h-4" />
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

      {/* Edit Modal */}
      <Dialog open={editModalOpen} onOpenChange={setEditModalOpen}>
        <DialogContent className="max-w-md">
          <DialogHeader>
            <DialogTitle>Edit User</DialogTitle>
            <DialogDescription>
              Update user details and permissions.
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
                <Label htmlFor="edit-name">Name</Label>
                <Input
                  id="edit-name"
                  value={formName}
                  onChange={(e) => setFormName(e.target.value)}
                  required
                />
              </div>
              <div className="space-y-2">
                <Label htmlFor="edit-email">Email</Label>
                <Input
                  id="edit-email"
                  type="email"
                  value={formEmail}
                  onChange={(e) => setFormEmail(e.target.value)}
                  required
                />
              </div>
              <div className="space-y-2">
                <Label htmlFor="edit-password">New Password (leave blank to keep current)</Label>
                <Input
                  id="edit-password"
                  type="password"
                  value={formPassword}
                  onChange={(e) => setFormPassword(e.target.value)}
                  minLength={8}
                  placeholder="••••••••"
                />
              </div>
              <div className="flex items-center gap-2">
                <input
                  type="checkbox"
                  id="edit-is-admin"
                  checked={formIsAdmin}
                  onChange={(e) => setFormIsAdmin(e.target.checked)}
                  className="h-4 w-4 rounded border-gray-300"
                  disabled={selectedUser?.id === currentUserId}
                />
                <Label htmlFor="edit-is-admin">
                  Administrator
                  {selectedUser?.id === currentUserId && (
                    <span className="text-muted-foreground text-sm ml-2">(cannot change your own role)</span>
                  )}
                </Label>
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

      {/* Delete Confirmation Modal */}
      <Dialog open={deleteModalOpen} onOpenChange={setDeleteModalOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Delete User</DialogTitle>
            <DialogDescription>
              Are you sure you want to delete "{selectedUser?.name}"?
              This action cannot be undone.
              {selectedUser && selectedUser.pass_request_count > 0 && (
                <span className="block mt-2 text-amber-600">
                  Warning: This user has {selectedUser.pass_request_count} pass request(s) which will also be deleted.
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
            <Button type="button" variant="outline" onClick={() => setDeleteModalOpen(false)}>
              Cancel
            </Button>
            <Button
              type="button"
              variant="destructive"
              onClick={handleDelete}
              disabled={formSubmitting}
            >
              {formSubmitting ? 'Deleting...' : 'Delete'}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
}

const adminUsersElement = document.getElementById('admin-users');
if (adminUsersElement) {
  createRoot(adminUsersElement).render(<AdminUsers />);
}
