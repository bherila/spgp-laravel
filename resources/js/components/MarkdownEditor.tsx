import React, { lazy, Suspense } from 'react';

import { Textarea } from '@/components/ui/textarea';

interface MarkdownEditorProps {
  value: string;
  onChange: (value: string) => void;
  label: string;
}

function PlainEditor({ value, onChange, label }: MarkdownEditorProps) {
  return <Textarea aria-label={label} value={value} onChange={(event) => onChange(event.target.value)} className="min-h-48" />;
}

const RichEditor = lazy(() => import('@uiw/react-md-editor').then(({ default: Editor }) => ({
  default: function RichMarkdownEditor({ value, onChange, label }: MarkdownEditorProps) {
    return <Editor value={value} onChange={(next) => onChange(next ?? '')} height={200} textareaProps={{ 'aria-label': label }} />;
  },
})).catch(() => ({ default: PlainEditor })));

export function MarkdownEditor(props: MarkdownEditorProps) {
  return (
    <Suspense fallback={<PlainEditor {...props} />}>
      <RichEditor {...props} />
    </Suspense>
  );
}
