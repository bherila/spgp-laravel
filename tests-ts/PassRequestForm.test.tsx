import { fireEvent, render, screen } from '@testing-library/react';
import React from 'react';

import { PassRequestForm } from '@/request';

import { mockJsonResponse } from './helpers/http';

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

  it('keeps the draft visible after a validation failure without retrying the submission', async () => {
    document.getElementById('page-data')!.textContent = JSON.stringify({ seasons: [{
      id: 1, pass_name: 'Mountain Pass', pass_year: 2027,
      early_spring_deadline: '2027-04-01T00:00:00+00:00',
      pass_types: [{ id: 2, pass_type_name: 'Adult', group_early_price: '100', group_regular_price: '100' }],
      user_previous_pass_type_id: 2,
    }] });
    jest.mocked(fetch).mockResolvedValue(mockJsonResponse({
      message: 'This passholder already has a request.',
      errors: { passholder_email: ['Already requested.'] },
    }, 422));
    render(<PassRequestForm />);

    fireEvent.click(screen.getByRole('button', { name: 'Continue' }));
    const name = screen.getByLabelText('First Name *');
    fireEvent.change(name, { target: { value: 'Alex' } });
    fireEvent.submit(name.closest('form')!);

    expect(await screen.findByText('This passholder already has a request.')).toBeInTheDocument();
    expect(name).toHaveValue('Alex');
    expect(fetch).toHaveBeenCalledTimes(1);
    const [url, options] = jest.mocked(fetch).mock.calls[0]!;
    expect(url).toBe('/api/pass-requests');
    expect(options?.method).toBe('POST');
    expect(JSON.parse(options?.body as string)).toEqual(expect.objectContaining({
      season_id: 1, season_pass_type_id: 2, passholder_first_name: 'Alex',
    }));
  });
});
