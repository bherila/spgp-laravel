import { fireEvent, render, screen } from '@testing-library/react';
import React from 'react';

import {
  AssignCodesDialog,
  DeletePassRequestsDialog,
  SendEmailsDialog,
  SendResultDialog,
  UnassignCodesDialog,
} from '@/admin/PassRequestsAdminDialogs';

// Radix Dialog uses portals; jsdom needs this
Object.defineProperty(globalThis, 'ResizeObserver', {
  writable: true,
  value: class ResizeObserver {
    observe() {}
    unobserve() {}
    disconnect() {}
  },
});

Object.defineProperty(globalThis, 'PointerEvent', {
  writable: true,
  value: class PointerEvent extends MouseEvent {},
});

describe('AssignCodesDialog', () => {
  const defaultProps = {
    open: true,
    onOpenChange: jest.fn(),
    selectedCount: 3,
    codes: '',
    onCodesChange: jest.fn(),
    onConfirm: jest.fn(),
    loading: false,
  };

  it('renders with selected count in description', () => {
    render(<AssignCodesDialog {...defaultProps} />);
    expect(screen.getByText(/3 selected pass requests/)).toBeTruthy();
  });

  it('calls onCodesChange when textarea value changes', () => {
    const onCodesChange = jest.fn();
    render(<AssignCodesDialog {...defaultProps} onCodesChange={onCodesChange} />);
    const textarea = screen.getByRole('textbox');
    fireEvent.change(textarea, { target: { value: 'CODE1\nCODE2' } });
    expect(onCodesChange).toHaveBeenCalledWith('CODE1\nCODE2');
  });

  it('calls onConfirm when Assign Codes button is clicked', () => {
    const onConfirm = jest.fn();
    render(<AssignCodesDialog {...defaultProps} onConfirm={onConfirm} />);
    fireEvent.click(screen.getByRole('button', { name: /assign codes/i }));
    expect(onConfirm).toHaveBeenCalled();
  });

  it('disables confirm button while loading', () => {
    render(<AssignCodesDialog {...defaultProps} loading={true} />);
    const btn = screen.getByRole('button', { name: /assigning/i });
    expect(btn).toBeDisabled();
  });

  it('calls onOpenChange(false) when Cancel is clicked', () => {
    const onOpenChange = jest.fn();
    render(<AssignCodesDialog {...defaultProps} onOpenChange={onOpenChange} />);
    fireEvent.click(screen.getByRole('button', { name: /cancel/i }));
    expect(onOpenChange).toHaveBeenCalledWith(false);
  });
});

describe('UnassignCodesDialog', () => {
  const defaultProps = {
    open: true,
    onOpenChange: jest.fn(),
    selectedCount: 2,
    onConfirm: jest.fn(),
    loading: false,
  };

  it('renders with selected count', () => {
    render(<UnassignCodesDialog {...defaultProps} />);
    expect(screen.getByText(/2 selected pass/)).toBeTruthy();
  });

  it('calls onConfirm when Unassign Codes is clicked', () => {
    const onConfirm = jest.fn();
    render(<UnassignCodesDialog {...defaultProps} onConfirm={onConfirm} />);
    fireEvent.click(screen.getByRole('button', { name: /unassign codes/i }));
    expect(onConfirm).toHaveBeenCalled();
  });

  it('disables confirm button while loading', () => {
    render(<UnassignCodesDialog {...defaultProps} loading={true} />);
    const btn = screen.getByRole('button', { name: /unassigning/i });
    expect(btn).toBeDisabled();
  });
});

describe('DeletePassRequestsDialog', () => {
  const defaultProps = {
    open: true,
    onOpenChange: jest.fn(),
    selectedCount: 1,
    onConfirm: jest.fn(),
    loading: false,
  };

  it('renders singular "request" for count of 1', () => {
    render(<DeletePassRequestsDialog {...defaultProps} selectedCount={1} />);
    expect(screen.getByText(/1 selected pass request\b/)).toBeTruthy();
  });

  it('renders plural "requests" for count > 1', () => {
    render(<DeletePassRequestsDialog {...defaultProps} selectedCount={5} />);
    expect(screen.getByText(/5 selected pass requests/)).toBeTruthy();
  });

  it('calls onConfirm when Delete is clicked', () => {
    const onConfirm = jest.fn();
    render(<DeletePassRequestsDialog {...defaultProps} onConfirm={onConfirm} />);
    fireEvent.click(screen.getByRole('button', { name: /^delete$/i }));
    expect(onConfirm).toHaveBeenCalled();
  });

  it('disables confirm button while loading', () => {
    render(<DeletePassRequestsDialog {...defaultProps} loading={true} />);
    const btn = screen.getByRole('button', { name: /deleting/i });
    expect(btn).toBeDisabled();
  });
});

describe('SendEmailsDialog', () => {
  const defaultProps = {
    open: true,
    onOpenChange: jest.fn(),
    selectedCount: 4,
    forceSend: false,
    onForceSendChange: jest.fn(),
    onConfirm: jest.fn(),
    loading: false,
  };

  it('renders with selected count', () => {
    render(<SendEmailsDialog {...defaultProps} />);
    expect(screen.getByText(/4 selected passholders/)).toBeTruthy();
  });

  it('calls onConfirm when Send Emails is clicked', () => {
    const onConfirm = jest.fn();
    render(<SendEmailsDialog {...defaultProps} onConfirm={onConfirm} />);
    fireEvent.click(screen.getByRole('button', { name: /send emails/i }));
    expect(onConfirm).toHaveBeenCalled();
  });

  it('shows warning when forceSend is true', () => {
    render(<SendEmailsDialog {...defaultProps} forceSend={true} />);
    expect(screen.getByText(/Warning: emails will be sent even/)).toBeTruthy();
  });

  it('shows normal info text when forceSend is false', () => {
    render(<SendEmailsDialog {...defaultProps} forceSend={false} />);
    expect(screen.getByText(/haven't been emailed yet/)).toBeTruthy();
  });
});

describe('SendResultDialog', () => {
  it('renders succeeded count', () => {
    render(
      <SendResultDialog
        open={true}
        onOpenChange={jest.fn()}
        result={{ succeeded: 5, failed: 0 }}
      />,
    );
    expect(screen.getByText('5')).toBeTruthy();
  });

  it('renders failed count when > 0', () => {
    render(
      <SendResultDialog
        open={true}
        onOpenChange={jest.fn()}
        result={{ succeeded: 3, failed: 2 }}
      />,
    );
    expect(screen.getByText('2')).toBeTruthy();
  });

  it('does not render result section when result is null', () => {
    render(
      <SendResultDialog
        open={true}
        onOpenChange={jest.fn()}
        result={null}
      />,
    );
    expect(screen.queryByText(/sent successfully/)).toBeNull();
  });

  it('calls onOpenChange(false) when OK is clicked', () => {
    const onOpenChange = jest.fn();
    render(
      <SendResultDialog
        open={true}
        onOpenChange={onOpenChange}
        result={{ succeeded: 1, failed: 0 }}
      />,
    );
    fireEvent.click(screen.getByRole('button', { name: /ok/i }));
    expect(onOpenChange).toHaveBeenCalledWith(false);
  });
});
