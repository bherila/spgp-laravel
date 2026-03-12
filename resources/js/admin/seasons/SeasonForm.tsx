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
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Switch } from "@/components/ui/switch";
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
import currency from 'currency.js';
import type { Season, SeasonPassType } from './types';
import { formatDateTimeLocal, toIsoString } from './utils';
import { toast } from 'sonner';

interface SeasonFormProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  season: Season | null;
  csrfToken: string;
  onSuccess: () => void;
}

function formatPrice(price: number | null): string {
  if (price === null) return '$ TBA';
  return currency(price).format();
}

export function SeasonForm({ open, onOpenChange, season, csrfToken, onSuccess }: SeasonFormProps) {
  const [activeTab, setActiveTab] = useState('info');
  
  // Season Info State
  const [passName, setPassName] = useState('');
  const [passYear, setPassYear] = useState(new Date().getFullYear().toString());
  const [startDate, setStartDate] = useState('');
  const [earlySpringDeadline, setEarlySpringDeadline] = useState('');
  const [finalDeadline, setFinalDeadline] = useState('');
  const [spreadsheetUrl, setSpreadsheetUrl] = useState('');
  const [allowRenewals, setAllowRenewals] = useState(true);
  const [submittingSeason, setSubmittingSeason] = useState(false);
  const [seasonError, setSeasonError] = useState<string | null>(null);

  // Pass Types State
  const [passTypes, setPassTypes] = useState<SeasonPassType[]>([]);
  const [loadingPassTypes, setLoadingPassTypes] = useState(false);
  const [ptFormOpen, setPtFormOpen] = useState(false);
  const [editingPt, setEditingPt] = useState<SeasonPassType | null>(null);
  const [submittingPt, setSubmittingPt] = useState(false);
  const [ptError, setPtError] = useState<string | null>(null);

  // Pass Type Form states
  const [formTypeName, setFormTypeName] = useState('');
  const [formRegularEarlyPrice, setFormRegularEarlyPrice] = useState('');
  const [formRegularRegularPrice, setFormRegularRegularPrice] = useState('');
  const [formRenewalEarlyPrice, setFormRenewalEarlyPrice] = useState('');
  const [formRenewalRegularPrice, setFormRenewalRegularPrice] = useState('');
  const [formGroupEarlyPrice, setFormGroupEarlyPrice] = useState('');
  const [formGroupRegularPrice, setFormGroupRegularPrice] = useState('');
  const [formSortOrder, setFormSortOrder] = useState('0');

  useEffect(() => {
    if (season) {
      setPassName(season.pass_name);
      setPassYear(season.pass_year.toString());
      setStartDate(formatDateTimeLocal(season.start_date));
      setEarlySpringDeadline(formatDateTimeLocal(season.early_spring_deadline));
      setFinalDeadline(formatDateTimeLocal(season.final_deadline));
      setSpreadsheetUrl(season.spreadsheet_url || '');
      setAllowRenewals(season.allow_renewals);
      fetchPassTypes();
    } else {
      setPassName('');
      setPassYear(new Date().getFullYear().toString());
      setStartDate('');
      setEarlySpringDeadline('');
      setFinalDeadline('');
      setSpreadsheetUrl('');
      setAllowRenewals(true);
      setPassTypes([]);
    }
    setSeasonError(null);
    setActiveTab('info');
  }, [season, open]);

  const fetchPassTypes = async () => {
    if (!season) return;
    try {
      setLoadingPassTypes(true);
      const response = await fetch(`/api/admin/seasons/${season.id}/pass-types`, {
        headers: { 'Accept': 'application/json' },
      });
      if (!response.ok) throw new Error('Failed to fetch pass types');
      const data = await response.json();
      setPassTypes(data);
    } catch (err) {
      console.error(err);
    } finally {
      setLoadingPassTypes(false);
    }
  };

  const handleSeasonSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setSubmittingSeason(true);
    setSeasonError(null);
    
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
          allow_renewals: allowRenewals,
        }),
      });
      
      if (!response.ok) {
        const data = await response.json();
        throw new Error(data.message || `Failed to ${season ? 'update' : 'create'} season`);
      }
      
      toast.success(`Season ${season ? 'updated' : 'created'} successfully`);
      if (!season) {
         onOpenChange(false);
      }
      onSuccess();
    } catch (err) {
      setSeasonError(err instanceof Error ? err.message : 'An error occurred');
    } finally {
      setSubmittingSeason(false);
    }
  };

  const resetPtForm = () => {
    setFormTypeName('');
    setFormRegularEarlyPrice('');
    setFormRegularRegularPrice('');
    setFormRenewalEarlyPrice('');
    setFormRenewalRegularPrice('');
    setFormGroupEarlyPrice('');
    setFormGroupRegularPrice('');
    setFormSortOrder('0');
    setEditingPt(null);
    setPtError(null);
  };

  const handlePtSave = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!season) return;

    setSubmittingPt(true);
    setPtError(null);
    
    const parsePrice = (val: string) => val === '' ? null : parseFloat(val);

    try {
      const url = editingPt 
        ? `/api/admin/pass-types/${editingPt.id}`
        : `/api/admin/seasons/${season.id}/pass-types`;
      
      const method = editingPt ? 'PUT' : 'POST';

      const response = await fetch(url, {
        method,
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': csrfToken,
        },
        body: JSON.stringify({
          pass_type_name: formTypeName,
          regular_early_price: parsePrice(formRegularEarlyPrice),
          regular_regular_price: parsePrice(formRegularRegularPrice),
          renewal_early_price: parsePrice(formRenewalEarlyPrice),
          renewal_regular_price: parsePrice(formRenewalRegularPrice),
          group_early_price: parsePrice(formGroupEarlyPrice),
          group_regular_price: parsePrice(formGroupRegularPrice),
          sort_order: parseInt(formSortOrder, 10),
        }),
      });

      if (!response.ok) {
        const data = await response.json();
        throw new Error(data.message || 'Failed to save pass type');
      }

      toast.success(`Pass type ${editingPt ? 'updated' : 'added'} successfully`);
      setPtFormOpen(false);
      resetPtForm();
      fetchPassTypes();
    } catch (err) {
      setPtError(err instanceof Error ? err.message : 'An error occurred');
    } finally {
      setSubmittingPt(false);
    }
  };

  const handleDeletePt = async (id: number) => {
    if (!confirm('Are you sure you want to delete this pass type?')) return;

    try {
      const response = await fetch(`/api/admin/pass-types/${id}`, {
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

      toast.success('Pass type deleted');
      fetchPassTypes();
    } catch (err) {
      alert(err instanceof Error ? err.message : 'An error occurred');
    }
  };

  const openEditPt = (pt: SeasonPassType) => {
    setEditingPt(pt);
    setFormTypeName(pt.pass_type_name);
    setFormRegularEarlyPrice(pt.regular_early_price?.toString() ?? '');
    setFormRegularRegularPrice(pt.regular_regular_price?.toString() ?? '');
    setFormRenewalEarlyPrice(pt.renewal_early_price?.toString() ?? '');
    setFormRenewalRegularPrice(pt.renewal_regular_price?.toString() ?? '');
    setFormGroupEarlyPrice(pt.group_early_price?.toString() ?? '');
    setFormGroupRegularPrice(pt.group_regular_price?.toString() ?? '');
    setFormSortOrder(pt.sort_order.toString());
    setPtFormOpen(true);
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className={season ? "max-w-6xl max-h-[95vh] overflow-y-auto" : "max-w-md"}>
        <DialogHeader>
          <DialogTitle>{season ? 'Edit' : 'Create'} Season</DialogTitle>
          <DialogDescription>
            {season ? `Manage "${season.pass_name} ${season.pass_year}" details and pass types.` : 'Create a new pass season.'}
          </DialogDescription>
        </DialogHeader>

        {season ? (
          <Tabs value={activeTab} onValueChange={setActiveTab}>
            <TabsList className="grid w-full grid-cols-2">
              <TabsTrigger value="info">Season Info</TabsTrigger>
              <TabsTrigger value="pass-types">Pass Types</TabsTrigger>
            </TabsList>
            
            <TabsContent value="info">
              <form onSubmit={handleSeasonSubmit} className="space-y-4 py-4">
                {seasonError && (
                  <div className="p-3 bg-destructive/10 border border-destructive rounded text-destructive text-sm">
                    {seasonError}
                  </div>
                )}
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div className="space-y-2">
                    <Label htmlFor="pass-name">Pass Name</Label>
                    <Input id="pass-name" value={passName} onChange={e => setPassName(e.target.value)} required />
                  </div>
                  <div className="space-y-2">
                    <Label htmlFor="pass-year">Year</Label>
                    <Input id="pass-year" type="number" value={passYear} onChange={e => setPassYear(e.target.value)} required />
                  </div>
                  <div className="space-y-2">
                    <Label htmlFor="start-date">Start Date</Label>
                    <Input id="start-date" type="datetime-local" value={startDate} onChange={e => setStartDate(e.target.value)} required />
                  </div>
                  <div className="space-y-2">
                    <Label htmlFor="early-spring">Early Spring Deadline</Label>
                    <Input id="early-spring" type="datetime-local" value={earlySpringDeadline} onChange={e => setEarlySpringDeadline(e.target.value)} required />
                  </div>
                  <div className="space-y-2">
                    <Label htmlFor="final">Final Deadline</Label>
                    <Input id="final" type="datetime-local" value={finalDeadline} onChange={e => setFinalDeadline(e.target.value)} required />
                  </div>
                  <div className="space-y-2">
                    <Label htmlFor="spreadsheet">Spreadsheet URL</Label>
                    <Input id="spreadsheet" type="url" value={spreadsheetUrl} onChange={e => setSpreadsheetUrl(e.target.value)} />
                  </div>
                </div>
                <div className="flex items-center space-x-2 py-2">
                  <Switch id="allow-renewals" checked={allowRenewals} onCheckedChange={setAllowRenewals} />
                  <Label htmlFor="allow-renewals">Allow Renewals</Label>
                </div>
                <div className="flex justify-end gap-2">
                  <Button type="submit" disabled={submittingSeason}>
                    {submittingSeason ? 'Saving...' : 'Save Changes'}
                  </Button>
                </div>
              </form>
            </TabsContent>

            <TabsContent value="pass-types">
              <div className="py-4">
                <div className="flex justify-between items-center mb-4">
                  <h3 className="text-lg font-medium">Available Pass Types</h3>
                  {!ptFormOpen && (
                    <Button size="sm" onClick={() => { resetPtForm(); setPtFormOpen(true); }}>
                      <Plus className="w-4 h-4 mr-2" />
                      Add Pass Type
                    </Button>
                  )}
                </div>

                {ptFormOpen && (
                  <div className="border rounded-lg p-4 mb-6 bg-muted/30">
                    <h4 className="font-medium mb-4">{editingPt ? 'Edit' : 'Add'} Pass Type</h4>
                    <form onSubmit={handlePtSave}>
                      {ptError && <div className="mb-4 p-3 bg-destructive/10 border border-destructive rounded text-destructive text-sm">{ptError}</div>}
                      <div className="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                        <div className="space-y-2">
                          <Label htmlFor="pt-name">Type Name</Label>
                          <Input id="pt-name" value={formTypeName} onChange={e => setFormTypeName(e.target.value)} required />
                        </div>
                        <div className="space-y-2">
                          <Label htmlFor="pt-sort">Sort Order</Label>
                          <Input id="pt-sort" type="number" value={formSortOrder} onChange={e => setFormSortOrder(e.target.value)} required />
                        </div>
                      </div>

                      <div className="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                        <div className="border p-3 rounded-md space-y-3 bg-background">
                          <h5 className="text-sm font-semibold border-b pb-1">Non-Group (New)</h5>
                          <div className="grid grid-cols-2 gap-3">
                            <div className="space-y-1">
                              <Label htmlFor="pt-reg-early" className="text-xs">Early Spring</Label>
                              <Input id="pt-reg-early" type="number" step="0.01" value={formRegularEarlyPrice} onChange={e => setFormRegularEarlyPrice(e.target.value)} />
                            </div>
                            <div className="space-y-1">
                              <Label htmlFor="pt-reg-reg" className="text-xs">Regular</Label>
                              <Input id="pt-reg-reg" type="number" step="0.01" value={formRegularRegularPrice} onChange={e => setFormRegularRegularPrice(e.target.value)} />
                            </div>
                          </div>
                        </div>

                        {allowRenewals && (
                          <div className="border p-3 rounded-md space-y-3 bg-background">
                            <h5 className="text-sm font-semibold border-b pb-1">Non-Group (Renewal)</h5>
                            <div className="grid grid-cols-2 gap-3">
                              <div className="space-y-1">
                                <Label htmlFor="pt-renew-early" className="text-xs">Early Spring</Label>
                                <Input id="pt-renew-early" type="number" step="0.01" value={formRenewalEarlyPrice} onChange={e => setFormRenewalEarlyPrice(e.target.value)} />
                              </div>
                              <div className="space-y-1">
                                <Label htmlFor="pt-renew-reg" className="text-xs">Regular</Label>
                                <Input id="pt-renew-reg" type="number" step="0.01" value={formRenewalRegularPrice} onChange={e => setFormRenewalRegularPrice(e.target.value)} />
                              </div>
                            </div>
                          </div>
                        )}

                        <div className="border p-3 rounded-md space-y-3 bg-background">
                          <h5 className="text-sm font-semibold border-b pb-1 text-primary">Group Pricing</h5>
                          <div className="grid grid-cols-2 gap-3">
                            <div className="space-y-1">
                              <Label htmlFor="pt-grp-early" className="text-xs">Early Spring</Label>
                              <Input id="pt-grp-early" type="number" step="0.01" value={formGroupEarlyPrice} onChange={e => setFormGroupEarlyPrice(e.target.value)} />
                            </div>
                            <div className="space-y-1">
                              <Label htmlFor="pt-grp-reg" className="text-xs">Regular</Label>
                              <Input id="pt-grp-reg" type="number" step="0.01" value={formGroupRegularPrice} onChange={e => setFormGroupRegularPrice(e.target.value)} />
                            </div>
                          </div>
                        </div>
                      </div>

                      <div className="flex justify-end gap-2">
                        <Button type="button" variant="ghost" size="sm" onClick={() => setPtFormOpen(false)}>Cancel</Button>
                        <Button type="submit" size="sm" disabled={submittingPt}>
                          {submittingPt ? 'Saving...' : (editingPt ? 'Update' : 'Add')}
                        </Button>
                      </div>
                    </form>
                  </div>
                )}

                <div className="border rounded-lg overflow-x-auto">
                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead className="w-12">Sort</TableHead>
                        <TableHead>Name</TableHead>
                        <TableHead className="text-center bg-muted/20">New Early</TableHead>
                        <TableHead className="text-center bg-muted/20">New Reg</TableHead>
                        {allowRenewals && (
                          <>
                            <TableHead className="text-center bg-muted/40">Renew Early</TableHead>
                            <TableHead className="text-center bg-muted/40">Renew Reg</TableHead>
                          </>
                        )}
                        <TableHead className="text-center bg-primary/5">Grp Early</TableHead>
                        <TableHead className="text-center bg-primary/5">Grp Reg</TableHead>
                        <TableHead className="text-right">Actions</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {loadingPassTypes ? (
                        <TableRow><TableCell colSpan={allowRenewals ? 9 : 7} className="text-center py-4"><Skeleton className="h-8 w-full" /></TableCell></TableRow>
                      ) : passTypes.length === 0 ? (
                        <TableRow><TableCell colSpan={allowRenewals ? 9 : 7} className="text-center py-4 text-muted-foreground">No pass types defined yet.</TableCell></TableRow>
                      ) : (
                        passTypes.map(pt => (
                          <TableRow key={pt.id}>
                            <TableCell>{pt.sort_order}</TableCell>
                            <TableCell className="font-medium">{pt.pass_type_name}</TableCell>
                            <TableCell className="text-center">{formatPrice(pt.regular_early_price)}</TableCell>
                            <TableCell className="text-center">{formatPrice(pt.regular_regular_price)}</TableCell>
                            {allowRenewals && (
                              <>
                                <TableCell className="text-center italic">{formatPrice(pt.renewal_early_price)}</TableCell>
                                <TableCell className="text-center italic">{formatPrice(pt.renewal_regular_price)}</TableCell>
                              </>
                            )}
                            <TableCell className="text-center font-semibold text-primary">{formatPrice(pt.group_early_price)}</TableCell>
                            <TableCell className="text-center font-semibold text-primary">{formatPrice(pt.group_regular_price)}</TableCell>
                            <TableCell className="text-right">
                              <div className="flex justify-end gap-1">
                                <Button variant="ghost" size="sm" onClick={() => openEditPt(pt)}><Pencil className="w-3 h-3" /></Button>
                                <Button variant="ghost" size="sm" onClick={() => handleDeletePt(pt.id)} className="text-destructive"><Archive className="w-3 h-3" /></Button>
                              </div>
                            </TableCell>
                          </TableRow>
                        ))
                      )}
                    </TableBody>
                  </Table>
                </div>
              </div>
            </TabsContent>
          </Tabs>
        ) : (
          <form onSubmit={handleSeasonSubmit}>
            <div className="space-y-4 py-4">
              {seasonError && <div className="p-3 bg-destructive/10 border border-destructive rounded text-destructive text-sm">{seasonError}</div>}
              <div className="space-y-2">
                <Label htmlFor="pass-name">Pass Name</Label>
                <Input id="pass-name" value={passName} onChange={e => setPassName(e.target.value)} required />
              </div>
              <div className="space-y-2">
                <Label htmlFor="pass-year">Year</Label>
                <Input id="pass-year" type="number" value={passYear} onChange={e => setPassYear(e.target.value)} required />
              </div>
              <div className="grid grid-cols-2 gap-4">
                <div className="space-y-2">
                  <Label htmlFor="start-date">Start Date</Label>
                  <Input id="start-date" type="datetime-local" value={startDate} onChange={e => setStartDate(e.target.value)} required />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="early-spring">Early Spring Deadline</Label>
                  <Input id="early-spring" type="datetime-local" value={earlySpringDeadline} onChange={e => setEarlySpringDeadline(e.target.value)} required />
                </div>
              </div>
              <div className="space-y-2">
                <Label htmlFor="final">Final Deadline</Label>
                <Input id="final" type="datetime-local" value={finalDeadline} onChange={e => setFinalDeadline(e.target.value)} required />
              </div>
              <div className="flex items-center space-x-2 py-2">
                <Switch id="allow-renewals" checked={allowRenewals} onCheckedChange={setAllowRenewals} />
                <Label htmlFor="allow-renewals">Allow Renewals</Label>
              </div>
            </div>
            <DialogFooter>
              <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>Cancel</Button>
              <Button type="submit" disabled={submittingSeason}>
                {submittingSeason ? 'Creating...' : 'Create Season'}
              </Button>
            </DialogFooter>
          </form>
        )}
      </DialogContent>
    </Dialog>
  );
}
