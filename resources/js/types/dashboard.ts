export interface SeasonPassType {
  id: number;
  pass_type_name: string;
  regular_early_price: number | null;
  regular_regular_price: number | null;
  renewal_early_price: number | null;
  renewal_regular_price: number | null;
  group_early_price: number | null;
  group_regular_price: number | null;
}

export interface PassRequest {
  id: string;
  passholder_email: string;
  pass_type: string;
  season_pass_type?: SeasonPassType | null;
  passholder_first_name: string;
  passholder_last_name: string;
  passholder_birth_date: string;
  is_renewal: boolean;
  renewal_pass_id: string | null;
  renewal_order_number: string | null;
  promo_code: string | null;
  country: string | null;
  redemption_date: string | null;
  assign_code_date: string | null;
  email_notify_time: string | null;
  created_at: string;
  updated_at: string;
}

export interface Season {
  id: number;
  pass_name: string;
  pass_year: number;
  start_date: string;
  early_spring_deadline: string;
  final_deadline: string;
  spreadsheet_url?: string | null;
  pass_requests: PassRequest[];
  pass_requests_count: number;
  questions_count: number;
  deleted_at: string | null;
}
