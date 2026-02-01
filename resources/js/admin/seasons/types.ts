export interface SeasonPassType {
  id: number;
  season_id: number;
  pass_type_name: string;
  regular_price: number;
  group_early_price: number;
  group_price: number;
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
  pass_request_count: number;
  deleted_at: string | null;
  created_at: string;
  updated_at: string;
}
