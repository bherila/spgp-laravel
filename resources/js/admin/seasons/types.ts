export interface SeasonPassType {
  id: number;
  season_id: number;
  pass_type_name: string;
  regular_early_price: number | null;
  regular_regular_price: number | null;
  renewal_early_price: number | null;
  renewal_regular_price: number | null;
  group_early_price: number | null;
  group_regular_price: number | null;
  sort_order: number;
}

export interface Season {
  id: number;
  pass_name: string;
  pass_year: number;
  start_date: string;
  early_spring_deadline: string;
  final_deadline: string;
  spreadsheet_url: string | null;
  allow_renewals: boolean;
  pass_request_count: number;
  invite_codes?: any[];
  deleted_at: string | null;
  created_at: string;
  updated_at: string;
}