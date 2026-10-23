import { fireEvent, render, screen } from '@testing-library/react';
import React, { useState } from 'react';

import { MarkdownEditor } from '@/components/MarkdownEditor';

jest.mock('@uiw/react-md-editor', () => {
  throw new Error('Editor chunk is unavailable');
});

function DraftEditor() {
  const [draft, setDraft] = useState('Initial draft');
  return <MarkdownEditor value={draft} onChange={setDraft} label="Question content" />;
}

it('keeps a usable controlled textarea when the editor chunk cannot load', async () => {
  render(<DraftEditor />);
  const editor = await screen.findByRole('textbox', { name: 'Question content' });
  fireEvent.change(editor, { target: { value: 'Continue writing without the editor chunk' } });

  expect(await screen.findByRole('textbox', { name: 'Question content' })).toHaveValue('Continue writing without the editor chunk');
});
