import './bootstrap';

import { AlertCircle, AlertTriangle, Ticket } from 'lucide-react';
import React from 'react';
import { createRoot } from 'react-dom/client';

import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';

interface UnassignedRequest {
  season_id: number;
  season_name: string;
  count: number;
}

interface UnansweredQuestion {
  season_id: number;
  season_name: string;
  count: number;
}

interface LowPromoCode {
  season_id: number;
  season_name: string;
  available: number;
}

interface AdminDashboardData {
  unassigned_requests: UnassignedRequest[];
  unanswered_questions: UnansweredQuestion[];
  low_promo_codes: LowPromoCode[];
}

function AdminDashboard({ data }: { data: AdminDashboardData }) {
  const hasAlerts =
    data.unassigned_requests.length > 0 ||
    data.unanswered_questions.length > 0 ||
    data.low_promo_codes.length > 0;

  if (!hasAlerts) return null;

  return (
    <div className="space-y-3 mb-6 max-w-3xl">
      {data.unassigned_requests.map((item) => (
        <Alert key={`unassigned-${item.season_id}`} variant="warning">
          <AlertTriangle className="h-4 w-4" />
          <AlertTitle>Unassigned pass requests — {item.season_name}</AlertTitle>
          <AlertDescription className="flex items-center justify-between gap-4">
            <span>
              <strong>{item.count}</strong> pass {item.count === 1 ? 'request' : 'requests'}{' '}
              {item.count === 1 ? 'has' : 'have'} no promo code assigned.
            </span>
            <Button asChild size="sm" variant="outline" className="shrink-0">
              <a href={`/admin/seasons/${item.season_id}/pass-requests`}>View requests</a>
            </Button>
          </AlertDescription>
        </Alert>
      ))}

      {data.unanswered_questions.map((item) => (
        <Alert key={`questions-${item.season_id}`}>
          <AlertCircle className="h-4 w-4" />
          <AlertTitle>Unanswered questions — {item.season_name}</AlertTitle>
          <AlertDescription className="flex items-center justify-between gap-4">
            <span>
              <strong>{item.count}</strong> unanswered{' '}
              {item.count === 1 ? 'question' : 'questions'} waiting for a response.
            </span>
            <Button asChild size="sm" variant="outline" className="shrink-0">
              <a href={`/season/${item.season_id}/questions`}>View questions</a>
            </Button>
          </AlertDescription>
        </Alert>
      ))}

      {data.low_promo_codes.map((item) => (
        <Alert key={`promo-${item.season_id}`}>
          <Ticket className="h-4 w-4" />
          <AlertTitle>Low promo code inventory — {item.season_name}</AlertTitle>
          <AlertDescription className="flex items-center justify-between gap-4">
            <span>
              Only <strong>{item.available}</strong> unassigned promo{' '}
              {item.available === 1 ? 'code' : 'codes'} remaining.
            </span>
            <Button asChild size="sm" variant="outline" className="shrink-0">
              <a href={`/admin/seasons/${item.season_id}/promo-codes`}>View codes</a>
            </Button>
          </AlertDescription>
        </Alert>
      ))}
    </div>
  );
}

const container = document.getElementById('admin-dashboard');
if (container) {
  const dataScript = document.getElementById('admin-dashboard-data');
  if (dataScript) {
    try {
      const data: AdminDashboardData = JSON.parse(dataScript.textContent ?? '{}');
      createRoot(container).render(<AdminDashboard data={data} />);
    } catch (e) {
      console.error('Failed to parse admin dashboard data', e);
    }
  }
}
