import { render, screen } from '@testing-library/react';
import React from 'react';

import { PassRequestForm } from '@/request';

jest.mock('@/bootstrap', () => ({}));

describe('pass request page bootstrap', () => {
  beforeEach(() => {
    globalThis.fetch = jest.fn();
    document.body.innerHTML = '<div id="request" data-season-id="1"></div><script id="page-data" type="application/json"></script>';
  });

  afterEach(() => {
    document.body.innerHTML = '';
    jest.restoreAllMocks();
  });

  it('renders the selected season immediately without fetching seasons', () => {
    document.getElementById('page-data')!.textContent = JSON.stringify({ seasons: [{
      id: 1, pass_name: 'Mountain Pass', pass_year: 2027,
      early_spring_deadline: '2027-04-01T00:00:00+00:00',
      pass_types: [], user_previous_pass_type_id: null,
    }] });
    render(<PassRequestForm />);
    expect(screen.getByText('Mountain Pass 2027')).toBeInTheDocument();
    expect(fetch).not.toHaveBeenCalled();
  });

  it('renders an empty season list without a loading request', () => {
    document.getElementById('page-data')!.textContent = JSON.stringify({ seasons: [] });
    render(<PassRequestForm />);
    expect(screen.getByText(/No active seasons available/)).toBeInTheDocument();
    expect(fetch).not.toHaveBeenCalled();
  });
});
