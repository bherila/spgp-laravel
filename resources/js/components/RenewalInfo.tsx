import currency from 'currency.js';
import React from 'react';

interface Season {
  id: number;
  pass_name: string;
  pass_year: number;
  early_spring_deadline: string;
}

interface SeasonPassType {
  id: number;
  pass_type_name: string;
  renewal_early_price: number | null;
  group_early_price: number | null;
}

interface PassRequest {
  id: string;
  passholder_first_name: string;
  season_pass_type?: SeasonPassType | null;
}

interface RenewalInfoProps {
  request: PassRequest;
  season: Season;
  formatDate: (date: string) => string;
}

const formatPrice = (value: number | string | null | undefined): string => {
  if (value === null || value === undefined) return '$ TBA';
  return currency(value).format();
};

export function RenewalInfo({ request, season, formatDate }: RenewalInfoProps) {
  const pt = request.season_pass_type;
  
  return (
    <div className="text-sm leading-relaxed space-y-4">
      <div>
        <p className="font-medium text-base mb-1">
          {request.passholder_first_name}'s {pt?.pass_type_name} — {season.pass_name} {season.pass_year}
        </p>
        <p className="text-muted-foreground">
          Before <strong>{formatDate(season.early_spring_deadline)}</strong>, 
          direct renewal is <strong>{formatPrice(pt?.renewal_early_price)}</strong> 
          (vs. <strong>{formatPrice(pt?.group_early_price)}</strong> through the group).
        </p>
      </div>
      
      <p>
        If you renew directly on the pass provider's website, please enter your renewal order number in the dashboard. 
        This helps our group get credit for the renewal, which benefits the program and has no cost to you.
      </p>
      
      <p>
        <strong>Note:</strong> No promo code will be issued for early renewals because the direct renewal price is lower than our group's discount price. 
        After the early renewal deadline passes, we will issue promo codes for any remaining pending renewals.
      </p>
    </div>
  );
}
