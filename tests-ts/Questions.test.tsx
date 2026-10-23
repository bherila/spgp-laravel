import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import React from 'react';

import Questions from '@/components/Questions';
import type { Question } from '@/types/questions';

import { mockJsonResponse } from './helpers/http';

const mockEditorLoads = jest.fn();

jest.mock('@uiw/react-md-editor', () => {
  mockEditorLoads();
  return {
    __esModule: true,
    default: ({ value, onChange, textareaProps }: { value: string; onChange: (value: string) => void; textareaProps: Record<string, string> }) => (
      <textarea {...textareaProps} data-testid="rich-editor" value={value} onChange={(event) => onChange(event.target.value)} />
    ),
  };
});

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
    expect(mockEditorLoads).not.toHaveBeenCalled();
  });

  it('renders an empty initial list without fetching', () => {
    render(<Questions seasonId={1} isAdmin={false} csrfToken="csrf" initialQuestions={[]} />);

    expect(screen.getByText(/No questions yet/)).toBeInTheDocument();
    expect(globalThis.fetch).not.toHaveBeenCalled();
    expect(mockEditorLoads).not.toHaveBeenCalled();
  });

  it('still sends an upvote when the user interacts', async () => {
    (globalThis.fetch as jest.Mock).mockResolvedValue(mockJsonResponse({ success: true }));
    render(<Questions seasonId={1} isAdmin={false} csrfToken="csrf" initialQuestions={[question]} />);

    fireEvent.click(screen.getByRole('button', { name: '0' }));

    await waitFor(() => expect(screen.getByRole('button', { name: '1' })).toBeInTheDocument());
    expect(globalThis.fetch).toHaveBeenCalledWith('/api/questions/1/upvote', expect.objectContaining({
      method: 'POST',
      headers: expect.any(Headers),
    }));
  });

  it('loads the rich editor only when asking and submits the controlled draft', async () => {
    (globalThis.fetch as jest.Mock)
      .mockResolvedValueOnce(mockJsonResponse(question))
      .mockResolvedValueOnce(mockJsonResponse([question]));
    render(<Questions seasonId={1} isAdmin={false} csrfToken="csrf" initialQuestions={[]} />);

    fireEvent.click(screen.getByRole('button', { name: 'Ask a Question' }));
    fireEvent.change(screen.getByRole('textbox', { name: 'Question content' }), { target: { value: 'A draft **question**' } });
    const editor = await screen.findByTestId('rich-editor');
    expect(mockEditorLoads).toHaveBeenCalledTimes(1);
    expect(editor).toHaveValue('A draft **question**');
    fireEvent.click(screen.getByRole('button', { name: 'Post Question' }));

    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
    expect(globalThis.fetch).toHaveBeenNthCalledWith(1, '/api/season/1/questions', expect.objectContaining({
      method: 'POST', body: JSON.stringify({ content: 'A draft **question**' }),
    }));
    await screen.findByText(question.content);
  });

  it('preserves a failed question draft and shows the server error in the dialog', async () => {
    (globalThis.fetch as jest.Mock).mockResolvedValue(mockJsonResponse({ message: 'Please make the question shorter.' }, 422));
    render(<Questions seasonId={1} isAdmin={false} csrfToken="csrf" initialQuestions={[]} />);
    fireEvent.click(screen.getByRole('button', { name: 'Ask a Question' }));
    fireEvent.change(await screen.findByTestId('rich-editor'), { target: { value: 'Keep this draft' } });
    fireEvent.click(screen.getByRole('button', { name: 'Post Question' }));

    expect(await screen.findByRole('alert')).toHaveTextContent('Please make the question shorter.');
    expect(screen.getByRole('textbox', { name: 'Question content' })).toHaveValue('Keep this draft');
  });

  it('keeps voting failures visible without changing the vote count', async () => {
    (globalThis.fetch as jest.Mock).mockResolvedValue(mockJsonResponse({ message: 'Sign in again.' }, 401));
    render(<Questions seasonId={1} isAdmin={false} csrfToken="csrf" initialQuestions={[question]} />);
    fireEvent.click(screen.getByRole('button', { name: '0' }));

    expect(await screen.findByRole('alert')).toHaveTextContent('Sign in again.');
    expect(screen.getByRole('button', { name: '0' })).toBeInTheDocument();
  });

});
