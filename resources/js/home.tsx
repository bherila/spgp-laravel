import './bootstrap';
import MainTitle from '@/components/MainTitle';
import { createRoot } from 'react-dom/client';
import React from 'react';

function Home() {
  return (
    <div className="max-w-6xl mx-auto px-4 py-8">
      <div className="mb-8">
        <MainTitle>K1 Flow</MainTitle>
        <p className="text-muted-foreground mt-2 max-w-2xl">
          Manage your Schedule K-1 forms and track flow-through tax information from partnerships,
          S-corporations, and other pass-through entities. Calculate outside basis, track loss
          limitations, and manage hierarchical ownership structures.
        </p>
      </div>
    </div>
  );
}

const homeElement = document.getElementById('home');
if (homeElement) {
  createRoot(homeElement).render(<Home />);
}
