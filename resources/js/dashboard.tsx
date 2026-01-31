import './bootstrap';
import { createRoot } from 'react-dom/client';
import React from 'react';
import MainTitle from '@/components/MainTitle';

function Dashboard() {
  const mount = document.getElementById('dashboard');
  const userName = mount?.getAttribute('data-user-name') || 'User';

  return (
    <div className="max-w-6xl mx-auto px-4 py-8">
      <div className="mb-8">
        <MainTitle>Dashboard</MainTitle>
        <p className="text-muted-foreground mt-2">
          Welcome back, {userName}!
        </p>
      </div>
      
      <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
        <div className="rounded-lg border p-6">
          <h3 className="font-semibold">Quick Start</h3>
          <p className="text-muted-foreground text-sm mt-2">
            Get started by exploring your dashboard features.
          </p>
        </div>
      </div>
    </div>
  );
}

const dashboardElement = document.getElementById('dashboard');
if (dashboardElement) {
  createRoot(dashboardElement).render(<Dashboard />);
}
