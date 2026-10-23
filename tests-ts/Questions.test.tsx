import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import React from 'react';

import Questions from '@/components/Questions';
import type { Question } from '@/types/questions';

jest.mock('@uiw/react-md-editor', () => ({
  __esModule: true,
  default: Object.assign(() => null, {
    Markdown: ({ source }: { source: string }) => <span>{source}</span>,
  }),
}));

const question: Question = {
  id: 1,
  user_id: 1,
  user: { id: 1, first_name: 'Alex', last_name: 'Example', name: 'Alex Example' },
  content: 'When does the season begin?',
  answer: null,
  answered_by: null,
  answered_at: null,
  created_at: '2026-10-23T00:00:00+00:00',
  upvotes_count: 0,
  user_has_upvoted: false,
};

describe('Questions initial page data', () => {
  const originalFetch = globalThis.fetch;

  beforeEach(() => {
    globalThis.fetch = jest.fn();
  });

  afterEach(() => {
    globalThis.fetch = originalFetch;
  });

  it('renders supplied questions without an initial network request', () => {
    render(<Questions seasonId={1} isAdmin={false} csrfToken="csrf" initialQuestions={[question]} />);

    expect(screen.getByText(question.content)).toBeInTheDocument();
    expect(globalThis.fetch).not.toHaveBeenCalled();
  });

  it('renders an empty initial list without fetching', () => {
    render(<Questions seasonId={1} isAdmin={false} csrfToken="csrf" initialQuestions={[]} />);

    expect(screen.getByText(/No questions yet/)).toBeInTheDocument();
    expect(globalThis.fetch).not.toHaveBeenCalled();
  });

  it('still sends an upvote when the user interacts', async () => {
    (globalThis.fetch as jest.Mock).mockResolvedValue({ ok: true });
    render(<Questions seasonId={1} isAdmin={false} csrfToken="csrf" initialQuestions={[question]} />);

    fireEvent.click(screen.getByRole('button', { name: '0' }));

    await waitFor(() => expect(screen.getByRole('button', { name: '1' })).toBeInTheDocument());
    expect(globalThis.fetch).toHaveBeenCalledWith('/api/questions/1/upvote', expect.objectContaining({
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': 'csrf', Accept: 'application/json' },
    }));
  });
});
