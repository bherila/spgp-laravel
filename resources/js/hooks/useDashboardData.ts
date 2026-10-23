import { useState } from 'react';

import { readPageData } from '@/lib/pageData';
import type { Season } from '@/types/dashboard';

export function useDashboardData() {
  const [seasons, setSeasons] = useState<Season[]>(() => readPageData<Season[]>('dashboard-data'));
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const refresh = async () => {
    try {
      setLoading(true);
      const response = await fetch('/dashboard', {
        headers: { Accept: 'application/json' },
      });
      if (!response.ok) throw new Error('Failed to refresh dashboard data');
      setSeasons((await response.json()) as Season[]);
      setError(null);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'An error occurred');
    } finally {
      setLoading(false);
    }
  };

  return { seasons, loading, error, refresh };
}
