import { act, fireEvent, render, screen, waitFor, within } from '@testing-library/react';
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

  it('coalesces rapid duplicate votes while leaving other questions available', async () => {
    let resolveVote!: (response: Response) => void;
    (globalThis.fetch as jest.Mock).mockReturnValue(new Promise<Response>((resolve) => { resolveVote = resolve; }));
    render(<Questions seasonId={1} isAdmin={false} csrfToken="csrf" initialQuestions={[question, { ...question, id: 2 }]} />);
    const [vote, otherVote] = screen.getAllByRole('button', { name: '0' });

    act(() => {
      fireEvent.click(vote!);
      fireEvent.click(vote!);
    });

    expect(globalThis.fetch).toHaveBeenCalledTimes(1);
    expect(vote).toBeDisabled();
    expect(otherVote).toBeEnabled();
    await act(async () => { resolveVote(mockJsonResponse({ success: true })); });

    expect(screen.getByRole('button', { name: '1' })).toBeEnabled();
    expect(screen.getByRole('button', { name: '1' })).toHaveAttribute('aria-pressed', 'true');
    expect(screen.getByRole('button', { name: '0' })).toHaveAttribute('aria-pressed', 'false');
  });

  it('coalesces rapid duplicate unvotes and restores the button after failure', async () => {
    let resolveVote!: (response: Response) => void;
    (globalThis.fetch as jest.Mock)
      .mockReturnValueOnce(new Promise<Response>((resolve) => { resolveVote = resolve; }))
      .mockResolvedValueOnce(mockJsonResponse({ success: true }));
    render(<Questions seasonId={1} isAdmin={false} csrfToken="csrf" initialQuestions={[{ ...question, upvotes_count: 1, user_has_upvoted: true }]} />);
    const vote = screen.getByRole('button', { name: '1' });

    act(() => {
      fireEvent.click(vote);
      fireEvent.click(vote);
    });
    expect(globalThis.fetch).toHaveBeenCalledTimes(1);
    expect(vote).toBeDisabled();
    await act(async () => { resolveVote(mockJsonResponse({ message: 'Vote unavailable.' }, 503)); });

    expect(await screen.findByRole('alert')).toHaveTextContent('Vote unavailable.');
    expect(vote).toBeEnabled();
    expect(vote).toHaveAttribute('aria-pressed', 'true');
    fireEvent.click(vote);
    await waitFor(() => expect(screen.getByRole('button', { name: '0' })).toBeEnabled());
    expect(globalThis.fetch).toHaveBeenCalledTimes(2);
    expect(screen.getByRole('button', { name: '0' })).toHaveAttribute('aria-pressed', 'false');
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

  it('clears a vote error when opening Ask', async () => {
    (globalThis.fetch as jest.Mock).mockResolvedValue(mockJsonResponse({ message: 'Vote unavailable.' }, 503));
    render(<Questions seasonId={1} isAdmin={false} csrfToken="csrf" initialQuestions={[question]} />);
    fireEvent.click(screen.getByRole('button', { name: '0' }));
    expect(await screen.findByRole('alert')).toHaveTextContent('Vote unavailable.');

    fireEvent.click(screen.getByRole('button', { name: 'Ask a Question' }));

    expect(screen.getByRole('dialog')).toBeInTheDocument();
    expect(screen.queryByRole('alert')).not.toBeInTheDocument();
  });

  it('clears failed dialog errors across closing and opening Ask, Edit, and Answer while retaining the Ask draft', async () => {
    const mount = document.createElement('div');
    mount.id = 'dashboard';
    mount.dataset.userId = '1';
    document.body.appendChild(mount);
    (globalThis.fetch as jest.Mock).mockResolvedValue(mockJsonResponse({ message: 'Please revise this content.' }, 422));
    render(<Questions seasonId={1} isAdmin csrfToken="csrf" initialQuestions={[question]} />);
    fireEvent.click(screen.getByRole('button', { name: 'Ask a Question' }));
    fireEvent.change(await screen.findByRole('textbox', { name: 'Question content' }), { target: { value: 'Retain my draft' } });
    fireEvent.click(screen.getByRole('button', { name: 'Post Question' }));
    expect(await screen.findByRole('alert')).toHaveTextContent('Please revise this content.');
    fireEvent.click(screen.getByRole('button', { name: 'Cancel' }));
    expect(screen.queryByRole('alert')).not.toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: 'Edit question' }));
    expect(screen.queryByRole('alert')).not.toBeInTheDocument();
    fireEvent.click(screen.getByRole('button', { name: 'Save Changes' }));
    expect(await screen.findByRole('alert')).toHaveTextContent('Please revise this content.');
    fireEvent.click(screen.getByRole('button', { name: 'Cancel' }));

    fireEvent.click(screen.getByRole('button', { name: 'Post Answer' }));
    expect(screen.queryByRole('alert')).not.toBeInTheDocument();
    fireEvent.change(screen.getByRole('textbox', { name: 'Answer content' }), { target: { value: 'An answer draft' } });
    fireEvent.click(within(screen.getByRole('dialog')).getByRole('button', { name: 'Post Answer' }));
    expect(await screen.findByRole('alert')).toHaveTextContent('Please revise this content.');
    fireEvent.keyDown(screen.getByRole('dialog'), { key: 'Escape' });
    expect(screen.queryByRole('alert')).not.toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: 'Ask a Question' }));
    expect(screen.queryByRole('alert')).not.toBeInTheDocument();
    expect(screen.getByRole('textbox', { name: 'Question content' })).toHaveValue('Retain my draft');
    mount.remove();
  });

});
