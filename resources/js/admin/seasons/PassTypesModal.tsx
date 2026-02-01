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
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import { Skeleton } from '@/components/ui/skeleton';
import { Pencil, Archive, Plus } from 'lucide-react';
import type { Season, SeasonPassType } from './types';

interface PassTypesModalProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  season: Season | null;
  csrfToken: string;
}

export function PassTypesModal({ open, onOpenChange, season, csrfToken }: PassTypesModalProps) {
  const [passTypes, setPassTypes] = useState<SeasonPassType[]>([]);
  const [loading, setLoading] = useState(false);
  const [formOpen, setFormOpen] = useState(false);
  const [editingType, setEditingType] = useState<SeasonPassType | null>(null);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  // Form states
  const [formTypeName, setFormTypeName] = useState('');
  const [formRegularEarlyPrice, setFormRegularEarlyPrice] = useState('');
  const [formRegularRegularPrice, setFormRegularRegularPrice] = useState('');
  const [formGroupEarlyPrice, setFormGroupEarlyPrice] = useState('');
  const [formGroupRegularPrice, setFormGroupRegularPrice] = useState('');
  const [formSortOrder, setFormSortOrder] = useState('0');

  const fetchPassTypes = async () => {
    if (!season) return;
    try {
      setLoading(true);
      const response = await fetch(`/admin/seasons/${season.id}/pass-types`, {
        headers: { 'Accept': 'application/json' },
      });
      if (!response.ok) throw new Error('Failed to fetch pass types');
      const data = await response.json();
      setPassTypes(data);
    } catch (err) {
      console.error(err);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    if (open && season) {
      fetchPassTypes();
    }
  }, [open, season]);

  const resetForm = () => {
    setFormTypeName('');
    setFormRegularEarlyPrice('');
    setFormRegularRegularPrice('');
    setFormGroupEarlyPrice('');
    setFormGroupRegularPrice('');
    setFormSortOrder('0');
    setEditingType(null);
    setError(null);
  };

  const handleSave = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!season) return;

    setSubmitting(true);
    setError(null);
    try {
      const url = editingType 
        ? `/admin/pass-types/${editingType.id}`
        : `/admin/seasons/${season.id}/pass-types`;
      
      const method = editingType ? 'PUT' : 'POST';

      const response = await fetch(url, {
        method,
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': csrfToken,
        },
        body: JSON.stringify({
          pass_type_name: formTypeName,
          regular_early_price: parseFloat(formRegularEarlyPrice),
          regular_regular_price: parseFloat(formRegularRegularPrice),
          group_early_price: parseFloat(formGroupEarlyPrice),
          group_regular_price: parseFloat(formGroupRegularPrice),
          sort_order: parseInt(formSortOrder, 10),
        }),
      });

      if (!response.ok) {
        const data = await response.json();
        throw new Error(data.message || 'Failed to save pass type');
      }

      setFormOpen(false);
      resetForm();
      fetchPassTypes();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'An error occurred');
    } finally {
      setSubmitting(false);
    }
  };

  const handleDelete = async (id: number) => {
    if (!confirm('Are you sure you want to delete this pass type?')) return;

    try {
      const response = await fetch(`/admin/pass-types/${id}`, {
        method: 'DELETE',
        headers: {
          'Accept': 'application/json',
          'X-CSRF-TOKEN': csrfToken,
        },
      });

      if (!response.ok) {
        const data = await response.json();
        throw new Error(data.message || 'Failed to delete pass type');
      }

      fetchPassTypes();
    } catch (err) {
      alert(err instanceof Error ? err.message : 'An error occurred');
    }
  };

  const openEdit = (pt: SeasonPassType) => {
    setEditingType(pt);
    setFormTypeName(pt.pass_type_name);
    setFormRegularEarlyPrice(pt.regular_early_price.toString());
    setFormRegularRegularPrice(pt.regular_regular_price.toString());
    setFormGroupEarlyPrice(pt.group_early_price.toString());
    setFormGroupRegularPrice(pt.group_regular_price.toString());
    setFormSortOrder(pt.sort_order.toString());
    setFormOpen(true);
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-5xl max-h-[90vh] overflow-y-auto">
        <DialogHeader>
          <DialogTitle>Manage Pass Types: {season?.pass_name} {season?.pass_year}</DialogTitle>
          <DialogDescription>
            Add or edit the available pass types for this season.
          </DialogDescription>
        </DialogHeader>

        <div className="py-4">
          <div className="flex justify-between items-center mb-4">
            <h3 className="text-lg font-medium">Available Pass Types</h3>
            {!formOpen && (
              <Button onClick={() => { resetForm(); setFormOpen(true); }}>
                <Plus className="w-4 h-4 mr-2" />
                Add Pass Type
              </Button>
            )}
          </div>

          {formOpen && (
            <div className="border rounded-lg p-4 mb-6 bg-muted/30">
              <h4 className="font-medium mb-4">{editingType ? 'Edit' : 'Add'} Pass Type</h4>
              <form onSubmit={handleSave}>
                {error && (
                  <div className="mb-4 p-3 bg-destructive/10 border border-destructive rounded text-destructive text-sm">
                    {error}
                  </div>
                )}
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                  <div className="space-y-2">
                    <Label htmlFor="pt-name">Type Name</Label>
                    <Input id="pt-name" value={formTypeName} onChange={e => setFormTypeName(e.target.value)} placeholder="e.g. Adult (23-64)" required />
                  </div>
                  <div className="space-y-2">
                    <Label htmlFor="pt-sort">Sort Order</Label>
                    <Input id="pt-sort" type="number" value={formSortOrder} onChange={e => setFormSortOrder(e.target.value)} required />
                  </div>
                  
                  <div className="border p-3 rounded-md space-y-3 bg-background">
                    <h5 className="text-sm font-semibold border-b pb-1">Non-Group Pricing</h5>
                    <div className="grid grid-cols-2 gap-3">
                      <div className="space-y-1">
                        <Label htmlFor="pt-reg-early" className="text-xs">Early Spring</Label>
                        <Input id="pt-reg-early" type="number" step="0.01" value={formRegularEarlyPrice} onChange={e => setFormRegularEarlyPrice(e.target.value)} required />
                      </div>
                      <div className="space-y-1">
                        <Label htmlFor="pt-reg-reg" className="text-xs">Late Spring / Summer</Label>
                        <Input id="pt-reg-reg" type="number" step="0.01" value={formRegularRegularPrice} onChange={e => setFormRegularRegularPrice(e.target.value)} required />
                      </div>
                    </div>
                  </div>

                  <div className="border p-3 rounded-md space-y-3 bg-background">
                    <h5 className="text-sm font-semibold border-b pb-1 text-primary">Group Pricing</h5>
                    <div className="grid grid-cols-2 gap-3">
                      <div className="space-y-1">
                        <Label htmlFor="pt-grp-early" className="text-xs">Early Spring</Label>
                        <Input id="pt-grp-early" type="number" step="0.01" value={formGroupEarlyPrice} onChange={e => setFormGroupEarlyPrice(e.target.value)} required />
                      </div>
                      <div className="space-y-1">
                        <Label htmlFor="pt-grp-reg" className="text-xs">Late Spring / Summer</Label>
                        <Input id="pt-grp-reg" type="number" step="0.01" value={formGroupRegularPrice} onChange={e => setFormGroupRegularPrice(e.target.value)} required />
                      </div>
                    </div>
                  </div>
                </div>
                <div className="flex justify-end gap-2">
                  <Button type="button" variant="ghost" onClick={() => setFormOpen(false)}>Cancel</Button>
                  <Button type="submit" disabled={submitting}>
                    {submitting ? 'Saving...' : (editingType ? 'Update' : 'Add')}
                  </Button>
                </div>
              </form>
            </div>
          )}

          <div className="border rounded-lg overflow-hidden">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead className="w-16">Sort</TableHead>
                  <TableHead>Name</TableHead>
                  <TableHead className="text-center bg-muted/20">Non-Group Early</TableHead>
                  <TableHead className="text-center bg-muted/20">Non-Group Reg</TableHead>
                  <TableHead className="text-center bg-primary/5">Group Early</TableHead>
                  <TableHead className="text-center bg-primary/5">Group Reg</TableHead>
                  <TableHead className="text-right">Actions</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {loading ? (
                  <TableRow><TableCell colSpan={7} className="text-center py-4"><Skeleton className="h-8 w-full" /></TableCell></TableRow>
                ) : passTypes.length === 0 ? (
                  <TableRow><TableCell colSpan={7} className="text-center py-4 text-muted-foreground">No pass types defined yet.</TableCell></TableRow>
                ) : (
                  passTypes.map(pt => (
                    <TableRow key={pt.id}>
                      <TableCell>{pt.sort_order}</TableCell>
                      <TableCell className="font-medium">{pt.pass_type_name}</TableCell>
                      <TableCell className="text-center">${pt.regular_early_price}</TableCell>
                      <TableCell className="text-center">${pt.regular_regular_price}</TableCell>
                      <TableCell className="text-center font-semibold text-primary">${pt.group_early_price}</TableCell>
                      <TableCell className="text-center font-semibold text-primary">${pt.group_regular_price}</TableCell>
                      <TableCell className="text-right">
                        <div className="flex justify-end gap-1">
                          <Button variant="ghost" size="sm" onClick={() => openEdit(pt)}><Pencil className="w-3 h-3" /></Button>
                          <Button variant="ghost" size="sm" onClick={() => handleDelete(pt.id)} className="text-destructive"><Archive className="w-3 h-3" /></Button>
                        </div>
                      </TableCell>
                    </TableRow>
                  ))
                )}
              </TableBody>
            </Table>
          </div>
        </div>
        <DialogFooter>
          <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>Close</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}