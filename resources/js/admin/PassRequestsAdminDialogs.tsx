import { Send, Trash2, XCircle } from 'lucide-react';
import React from 'react';

import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';

// ---------------------------------------------------------------------------
// AssignCodesDialog
// ---------------------------------------------------------------------------
export interface AssignCodesDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  selectedCount: number;
  codes: string;
  onCodesChange: (codes: string) => void;
  onConfirm: () => void;
  loading: boolean;
}

export function AssignCodesDialog({
  open,
  onOpenChange,
  selectedCount,
  codes,
  onCodesChange,
  onConfirm,
  loading,
}: AssignCodesDialogProps) {
  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-lg">
        <DialogHeader>
          <DialogTitle>Assign Promo Codes</DialogTitle>
          <DialogDescription>
            Paste promo codes (one per line) to assign to {selectedCount} selected pass requests.
          </DialogDescription>
        </DialogHeader>
        <div className="space-y-4 py-4">
          <div className="space-y-2">
            <Label htmlFor="codes">Promo Codes (one per line)</Label>
            <textarea
              id="codes"
              value={codes}
              onChange={(e) => onCodesChange(e.target.value)}
              className="w-full h-48 rounded-md border border-input bg-background px-3 py-2 text-sm font-mono"
              placeholder={"CODE1\nCODE2\nCODE3"}
            />
            <p className="text-sm text-muted-foreground">
              {codes.split('\n').filter((c) => c.trim()).length} codes entered,{' '}
              {selectedCount} selected
            </p>
          </div>
        </div>
        <DialogFooter>
          <Button variant="outline" onClick={() => onOpenChange(false)}>
            Cancel
          </Button>
          <Button onClick={onConfirm} disabled={loading}>
            {loading ? 'Assigning...' : 'Assign Codes'}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

// ---------------------------------------------------------------------------
// UnassignCodesDialog
// ---------------------------------------------------------------------------
export interface UnassignCodesDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  selectedCount: number;
  onConfirm: () => void;
  loading: boolean;
}

export function UnassignCodesDialog({
  open,
  onOpenChange,
  selectedCount,
  onConfirm,
  loading,
}: UnassignCodesDialogProps) {
  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Unassign Promo Codes</DialogTitle>
          <DialogDescription>
            Are you sure you want to unassign promo codes from {selectedCount} selected pass
            requests? This will also reset the assign date and email status.
          </DialogDescription>
        </DialogHeader>
        <DialogFooter>
          <Button variant="outline" onClick={() => onOpenChange(false)}>
            Cancel
          </Button>
          <Button variant="destructive" onClick={onConfirm} disabled={loading}>
            <XCircle className="w-4 h-4 mr-2" />
            {loading ? 'Unassigning...' : 'Unassign Codes'}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

// ---------------------------------------------------------------------------
// DeletePassRequestsDialog
// ---------------------------------------------------------------------------
export interface DeletePassRequestsDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  selectedCount: number;
  onConfirm: () => void;
  loading: boolean;
}

export function DeletePassRequestsDialog({
  open,
  onOpenChange,
  selectedCount,
  onConfirm,
  loading,
}: DeletePassRequestsDialogProps) {
  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Delete Pass Requests</DialogTitle>
          <DialogDescription>
            Are you sure you want to permanently delete {selectedCount} selected pass{' '}
            {selectedCount === 1 ? 'request' : 'requests'}? This action cannot be undone. Pass
            requests with an assigned promo code cannot be deleted — please unassign first.
          </DialogDescription>
        </DialogHeader>
        <DialogFooter>
          <Button variant="outline" onClick={() => onOpenChange(false)}>
            Cancel
          </Button>
          <Button variant="destructive" onClick={onConfirm} disabled={loading}>
            <Trash2 className="w-4 h-4 mr-2" />
            {loading ? 'Deleting...' : 'Delete'}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

// ---------------------------------------------------------------------------
// SendEmailsDialog
// ---------------------------------------------------------------------------
export interface SendEmailsDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  selectedCount: number;
  forceSend: boolean;
  onForceSendChange: (value: boolean) => void;
  onConfirm: () => void;
  loading: boolean;
}

export function SendEmailsDialog({
  open,
  onOpenChange,
  selectedCount,
  forceSend,
  onForceSendChange,
  onConfirm,
  loading,
}: SendEmailsDialogProps) {
  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Send Email Notifications</DialogTitle>
          <DialogDescription>
            Send promo code notification emails to {selectedCount} selected passholders. Each
            email will be sent to the passholder and to your account email.
          </DialogDescription>
        </DialogHeader>
        <div className="py-4">
          <label className="flex items-center gap-2 text-sm cursor-pointer">
            <Checkbox
              checked={forceSend}
              onCheckedChange={(checked) => onForceSendChange(checked === true)}
            />
            Force-send even if already emailed
          </label>
          {!forceSend && (
            <p className="mt-2 text-xs text-muted-foreground">
              Only requests with promo codes that haven&apos;t been emailed yet will receive
              emails.
            </p>
          )}
          {forceSend && (
            <p className="mt-2 text-xs text-amber-600 dark:text-amber-400">
              Warning: emails will be sent even to passholders who already received one.
            </p>
          )}
        </div>
        <DialogFooter>
          <Button variant="outline" onClick={() => onOpenChange(false)}>
            Cancel
          </Button>
          <Button onClick={onConfirm} disabled={loading}>
            <Send className="w-4 h-4 mr-2" />
            Send Emails
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

// ---------------------------------------------------------------------------
// SendResultDialog
// ---------------------------------------------------------------------------
export interface SendResultDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  result: { succeeded: number; failed: number } | null;
}

export function SendResultDialog({ open, onOpenChange, result }: SendResultDialogProps) {
  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Email Send Complete</DialogTitle>
          <DialogDescription>The email send operation has finished.</DialogDescription>
        </DialogHeader>
        {result && (
          <div className="py-4 space-y-2">
            <p className="text-sm">
              <span className="font-semibold text-green-600 dark:text-green-400">
                {result.succeeded}
              </span>{' '}
              email{result.succeeded !== 1 ? 's' : ''} sent successfully.
            </p>
            {result.failed > 0 && (
              <p className="text-sm">
                <span className="font-semibold text-destructive">{result.failed}</span>{' '}
                request{result.failed !== 1 ? 's' : ''} skipped (already emailed without
                force-send) or failed.
              </p>
            )}
          </div>
        )}
        <DialogFooter>
          <Button onClick={() => onOpenChange(false)}>OK</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
