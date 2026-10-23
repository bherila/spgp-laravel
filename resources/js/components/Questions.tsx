import { ArrowLeft, CheckCircle2, Edit2, HelpCircle, MessageSquare, Send, ThumbsUp, Trash2 } from 'lucide-react';
import React, { useRef, useState } from 'react';

import MainTitle from '@/components/MainTitle';
import { Markdown } from '@/components/Markdown';
import { MarkdownEditor } from '@/components/MarkdownEditor';
import { Button as ShadcnButton } from '@/components/ui/button';
import { Card, CardContent, CardFooter, CardHeader } from '@/components/ui/card';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Skeleton } from '@/components/ui/skeleton';
import { apiRequest } from '@/lib/apiClient';
import type { Question } from '@/types/questions';

interface QuestionsProps {
  seasonId: number;
  isAdmin: boolean;
  csrfToken: string;
  initialQuestions: Question[];
}

export default function Questions({ seasonId, isAdmin, csrfToken, initialQuestions }: QuestionsProps) {
  const [questions, setQuestions] = useState(initialQuestions);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [isAsking, setIsAsking] = useState(false);
  const [newQuestionContent, setNewQuestionContent] = useState('');
  const [editingQuestion, setEditingQuestion] = useState<Question | null>(null);
  const [answeringQuestion, setAnsweringQuestion] = useState<Question | null>(null);
  const [editContent, setEditContent] = useState('');
  const [answerContent, setAnswerContent] = useState('');
  const voteRequests = useRef(new Set<number>());
  const [pendingVotes, setPendingVotes] = useState<Set<number>>(() => new Set());

  const changeAskDialog = (open: boolean) => {
    setError(null);
    setIsAsking(open);
  };

  const changeEditDialog = (question: Question | null) => {
    setError(null);
    setEditingQuestion(question);
  };

  const changeAnswerDialog = (question: Question | null) => {
    setError(null);
    setAnsweringQuestion(question);
  };

  const fetchQuestions = async () => {
    try {
      setLoading(true);
      setError(null);
      setQuestions(await apiRequest<Question[]>(`/api/season/${seasonId}/questions`, {
        errorMessage: 'Failed to fetch questions',
      }));
    } catch (err) {
      setError(err instanceof Error ? err.message : 'An error occurred');
    } finally {
      setLoading(false);
    }
  };

  const handleUpvote = async (question: Question) => {
    if (voteRequests.current.has(question.id)) return;
    voteRequests.current.add(question.id);
    setPendingVotes(new Set(voteRequests.current));
    const shouldUpvote = !question.user_has_upvoted;
    const url = `/api/questions/${question.id}/${shouldUpvote ? 'upvote' : 'unvote'}`;
    try {
      setError(null);
      await apiRequest(url, { method: 'POST', csrfToken, errorMessage: 'Failed to update vote' });
      setQuestions((current) => current.map(q => q.id === question.id && q.user_has_upvoted !== shouldUpvote ? {
        ...q,
        user_has_upvoted: shouldUpvote,
        upvotes_count: q.upvotes_count + (shouldUpvote ? 1 : -1),
      } : q));
    } catch (err) {
      setError(err instanceof Error ? err.message : 'An error occurred');
    } finally {
      voteRequests.current.delete(question.id);
      setPendingVotes(new Set(voteRequests.current));
    }
  };

  const handleStoreQuestion = async () => {
    if (!newQuestionContent.trim()) return;
    try {
      setError(null);
      await apiRequest(`/api/season/${seasonId}/questions`, {
        method: 'POST', csrfToken, json: { content: newQuestionContent }, errorMessage: 'Failed to post question',
      });
      changeAskDialog(false);
      setNewQuestionContent('');
      await fetchQuestions();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'An error occurred');
    }
  };

  const handleUpdateQuestion = async () => {
    if (!editingQuestion || !editContent.trim()) return;
    try {
      setError(null);
      await apiRequest(`/api/questions/${editingQuestion.id}`, {
        method: 'PATCH', csrfToken, json: { content: editContent }, errorMessage: 'Failed to update question',
      });
      changeEditDialog(null);
      await fetchQuestions();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'An error occurred');
    }
  };

  const handleAnswerQuestion = async () => {
    if (!answeringQuestion || !answerContent.trim()) return;
    try {
      setError(null);
      await apiRequest(`/api/questions/${answeringQuestion.id}/answer`, {
        method: 'POST', csrfToken, json: { answer: answerContent }, errorMessage: 'Failed to post answer',
      });
      changeAnswerDialog(null);
      await fetchQuestions();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'An error occurred');
    }
  };

  const handleDeleteQuestion = async (id: number) => {
    if (!confirm('Are you sure you want to delete this question?')) return;
    try {
      setError(null);
      await apiRequest(`/api/questions/${id}`, { method: 'DELETE', csrfToken, errorMessage: 'Failed to delete question' });
      await fetchQuestions();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'An error occurred');
    }
  };

  return (
    <div className="max-w-4xl mx-auto px-4 py-8">
      <div className="mb-8 flex items-center justify-between">
        <div className="flex items-center gap-4">
          <ShadcnButton variant="ghost" size="icon" asChild>
            <a href="/dashboard">
              <ArrowLeft className="w-5 h-5" />
            </a>
          </ShadcnButton>
          <MainTitle>Q&A</MainTitle>
        </div>
        <ShadcnButton onClick={() => changeAskDialog(true)}>
          <MessageSquare className="w-4 h-4 mr-2" />
          Ask a Question
        </ShadcnButton>
      </div>

      {error && !isAsking && !editingQuestion && !answeringQuestion && <p role="alert" className="mb-4 text-destructive">{error}</p>}

      {loading ? (
        <div className="space-y-6">
          {[...Array(3)].map((_, i) => (
            <Skeleton key={i} className="h-32 w-full rounded-xl" />
          ))}
        </div>
      ) : questions.length === 0 ? (
        <Card className="text-center py-12">
          <CardContent>
            <HelpCircle className="w-12 h-12 mx-auto text-muted-foreground mb-4 opacity-20" />
            <p className="text-muted-foreground">No questions yet for this season. Be the first to ask!</p>
          </CardContent>
        </Card>
      ) : (
        <div className="space-y-6">
          {questions.map((question) => (
            <Card key={question.id} className={question.answer ? 'border-primary/20 shadow-sm' : ''}>
              <CardHeader className="flex flex-row items-start justify-between pb-2">
                <div className="flex flex-col">
                  <span className="text-sm font-medium text-primary mb-1">{question.user.name} asked:</span>
                  <div data-color-mode="auto">
                    <Markdown source={question.content} />
                  </div>
                </div>
                <div className="flex flex-col items-center gap-2">
                  <ShadcnButton 
                    variant={question.user_has_upvoted ? "default" : "outline"} 
                    size="sm" 
                    className="h-9 px-3"
                    onClick={() => handleUpvote(question)}
                    disabled={pendingVotes.has(question.id)}
                    aria-pressed={question.user_has_upvoted}
                  >
                    <ThumbsUp className={`w-4 h-4 mr-2 ${question.user_has_upvoted ? 'fill-current' : ''}`} />
                    {question.upvotes_count}
                  </ShadcnButton>
                  
                  {(!question.answer && (isAdmin || question.user_id === Number(document.getElementById('dashboard')?.getAttribute('data-user-id')))) && (
                    <div className="flex gap-1 mt-2">
                         {question.user_id === Number(document.getElementById('dashboard')?.getAttribute('data-user-id')) && (
                            <ShadcnButton variant="ghost" size="icon" className="h-8 w-8" aria-label="Edit question" onClick={() => {
                                changeEditDialog(question);
                                setEditContent(question.content);
                            }}>
                                <Edit2 className="w-3.5 h-3.5" />
                            </ShadcnButton>
                         )}
                         <ShadcnButton variant="ghost" size="icon" className="h-8 w-8 text-destructive" onClick={() => handleDeleteQuestion(question.id)}>
                            <Trash2 className="w-3.5 h-3.5" />
                         </ShadcnButton>
                    </div>
                  )}
                </div>
              </CardHeader>

              {question.answer && (
                <CardContent className="pt-4 border-t bg-muted/30">
                  <div className="flex items-start gap-3">
                    <CheckCircle2 className="w-5 h-5 text-green-600 mt-1 shrink-0" />
                    <div className="flex flex-col flex-1">
                      <span className="text-sm font-semibold mb-2">Answer from {question.answered_by?.name || 'Admin'}:</span>
                      <div data-color-mode="auto">
                        <Markdown source={question.answer ?? ''} />
                      </div>
                      {isAdmin && (
                        <ShadcnButton variant="ghost" size="sm" className="mt-4 self-start" onClick={() => {
                            changeAnswerDialog(question);
                            setAnswerContent(question.answer || '');
                        }}>
                            <Edit2 className="w-3 h-3 mr-2" />
                            Edit Answer
                        </ShadcnButton>
                      )}
                    </div>
                  </div>
                </CardContent>
              )}

              {!question.answer && isAdmin && (
                <CardFooter className="pt-4 border-t">
                  <ShadcnButton variant="outline" size="sm" onClick={() => {
                    changeAnswerDialog(question);
                    setAnswerContent('');
                  }}>
                    <MessageSquare className="w-4 h-4 mr-2" />
                    Post Answer
                  </ShadcnButton>
                </CardFooter>
              )}
            </Card>
          ))}
        </div>
      )}

      {/* Ask Question Dialog */}
      <Dialog open={isAsking} onOpenChange={changeAskDialog}>
        <DialogContent className="max-w-2xl">
          <DialogHeader>
            <DialogTitle>Ask a Question</DialogTitle>
            <DialogDescription>
              Use Markdown to format your question. Headings, lists, and bold text are supported.
            </DialogDescription>
          </DialogHeader>
          <div className="mt-4" data-color-mode="auto">
            {isAsking && <MarkdownEditor value={newQuestionContent} onChange={setNewQuestionContent} label="Question content" />}
          </div>
          {error && <p role="alert" className="text-destructive">{error}</p>}
          <DialogFooter>
            <ShadcnButton variant="outline" onClick={() => changeAskDialog(false)}>Cancel</ShadcnButton>
            <ShadcnButton onClick={handleStoreQuestion}>
                <Send className="w-4 h-4 mr-2" />
                Post Question
            </ShadcnButton>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Edit Question Dialog */}
      <Dialog open={!!editingQuestion} onOpenChange={() => changeEditDialog(null)}>
        <DialogContent className="max-w-2xl">
          <DialogHeader>
            <DialogTitle>Edit Question</DialogTitle>
            <DialogDescription>Update your question using Markdown.</DialogDescription>
          </DialogHeader>
          <div className="mt-4" data-color-mode="auto">
            {editingQuestion && <MarkdownEditor value={editContent} onChange={setEditContent} label="Question content" />}
          </div>
          {error && <p role="alert" className="text-destructive">{error}</p>}
          <DialogFooter>
            <ShadcnButton variant="outline" onClick={() => changeEditDialog(null)}>Cancel</ShadcnButton>
            <ShadcnButton onClick={handleUpdateQuestion}>Save Changes</ShadcnButton>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Answer Question Dialog */}
      <Dialog open={!!answeringQuestion} onOpenChange={() => changeAnswerDialog(null)}>
        <DialogContent className="max-w-2xl">
          <DialogHeader>
            <DialogTitle>{answeringQuestion?.answer ? 'Edit Answer' : 'Post Answer'}</DialogTitle>
            <DialogDescription>Write your answer using Markdown.</DialogDescription>
          </DialogHeader>
          <div className="mt-4" data-color-mode="auto">
            {answeringQuestion && <MarkdownEditor value={answerContent} onChange={setAnswerContent} label="Answer content" />}
          </div>
          {error && <p role="alert" className="text-destructive">{error}</p>}
          <DialogFooter>
            <ShadcnButton variant="outline" onClick={() => changeAnswerDialog(null)}>Cancel</ShadcnButton>
            <ShadcnButton onClick={handleAnswerQuestion}>
                <Send className="w-4 h-4 mr-2" />
                Post Answer
            </ShadcnButton>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
}
