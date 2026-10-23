interface QuestionUser {
  id: number;
  first_name: string | null;
  last_name: string | null;
  name: string;
}

export interface Question {
  id: number;
  user_id: number;
  user: QuestionUser;
  content: string;
  answer: string | null;
  answered_by: QuestionUser | null;
  answered_at: string | null;
  created_at: string;
  upvotes_count: number;
  user_has_upvoted: boolean;
}
