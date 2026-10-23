import { render, screen } from '@testing-library/react';
import React from 'react';

import { Markdown } from '@/components/Markdown';

describe('Markdown rendering', () => {
  it('renders headings, formatting, code, and GitHub tables and task lists', () => {
    const { container } = render(<Markdown source={'# Season notes\n\n**Bold** and ~~old~~ with `code`.\n\n| Pass | Price |\n| --- | --- |\n| Adult | $99 |\n\n- [x] Requested\n- [ ] Assigned'} />);

    expect(screen.getByRole('heading', { name: 'Season notes' })).toBeInTheDocument();
    expect(container.querySelector('strong')).toHaveTextContent('Bold');
    expect(container.querySelector('del')).toHaveTextContent('old');
    expect(container.querySelector('code')).toHaveTextContent('code');
    expect(screen.getByRole('table')).toHaveTextContent('Adult');
    expect(screen.getAllByRole('checkbox')).toHaveLength(2);
    expect(screen.getAllByRole('checkbox')[0]).toBeChecked();
  });

  it('skips raw HTML and strips unsafe URL protocols while preserving safe links', () => {
    const source = '<script>alert("x")</script>\n\n<img src="x" onerror="alert(1)">\n\n[danger](javascript:alert%281%29) [data](data:text/html,unsafe) [safe](https://example.test/passes)';
    const { container } = render(<Markdown source={source} />);

    expect(container.querySelector('script')).toBeNull();
    expect(container.querySelector('img')).toBeNull();
    expect(screen.getByText('danger').closest('a')).not.toHaveAttribute('href', expect.stringContaining('javascript:'));
    expect(screen.getByText('data').closest('a')).not.toHaveAttribute('href', expect.stringContaining('data:'));
    expect(screen.getByRole('link', { name: 'safe' })).toHaveAttribute('href', 'https://example.test/passes');
  });
});
