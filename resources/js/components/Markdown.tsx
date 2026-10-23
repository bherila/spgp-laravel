import React from 'react';
import ReactMarkdown from 'react-markdown';
import remarkGfm from 'remark-gfm';

interface MarkdownProps {
  source: string;
}

export function Markdown({ source }: MarkdownProps) {
  return (
    <div className="markdown-content prose prose-sm max-w-none break-words">
      <ReactMarkdown remarkPlugins={[remarkGfm]} skipHtml>{source}</ReactMarkdown>
    </div>
  );
}
