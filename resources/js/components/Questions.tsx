import React, { useState, useEffect } from 'react';
import MainTitle from '@/components/MainTitle';
import { Button as ShadcnButton } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardFooter } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { ThumbsUp, MessageSquare, ArrowLeft, Send, Edit2, Trash2, CheckCircle2, HelpCircle } from 'lucide-react';
import { MDXEditor, headingsPlugin, listsPlugin, quotePlugin, thematicBreakPlugin, markdownShortcutPlugin, BoldItalicUnderlineToggles, UndoRedo, Separator, ListsToggle } from '@mdxeditor/editor';
import '@mdxeditor/editor/style.css';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';

interface User {
  id: number;
  name: string;
}

interface Question {
  id: number;
  user_id: number;
  user: User;
  content: string;
  answer: string | null;
  answered_by: number | null;
  answered_by_user?: User;
  answered_at: string | null;
  created_at: string;
  upvotes_count: number;
  user_has_upvoted: boolean;
}

interface QuestionsProps {
  seasonId: number;
  isAdmin: boolean;
  csrfToken: string;
}

const EditorToolbar = () => (
    <>
        <UndoRedo />
        <Separator />
        <BoldItalicUnderlineToggles />
        <Separator />
        <ListsToggle />
    </>
);

export default function Questions({ seasonId, isAdmin, csrfToken }: QuestionsProps) {
  const [questions, setQuestions] = useState<Question[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [isAsking, setIsAsking] = useState(false);
  const [newQuestionContent, setNewQuestionContent] = useState('');
  const [editingQuestion, setEditingQuestion] = useState<Question | null>(null);
  const [answeringQuestion, setAnsweringQuestion] = useState<Question | null>(null);
  const [editContent, setEditContent] = useState('');
  const [answerContent, setAnswerContent] = useState('');

  const fetchQuestions = async () => {
    try {
      setLoading(true);
      const response = await fetch(`/api/season/${seasonId}/questions`);
      if (!response.ok) throw new Error('Failed to fetch questions');
      const data = await response.json();
      setQuestions(data);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'An error occurred');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchQuestions();
  }, [seasonId]);

  const handleUpvote = async (question: Question) => {
    const url = `/api/questions/${question.id}/${question.user_has_upvoted ? 'unvote' : 'upvote'}`;
    try {
      const response = await fetch(url, {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': csrfToken,
          'Accept': 'application/json',
        },
      });
      if (response.ok) {
        setQuestions(questions.map(q => {
          if (q.id === question.id) {
            return {
              ...q,
              user_has_upvoted: !q.user_has_upvoted,
              upvotes_count: q.user_has_upvoted ? q.upvotes_count - 1 : q.upvotes_count + 1
            };
          }
          return q;
        }));
      }
    } catch (err) {
      console.error(err);
    }
  };

  const handleStoreQuestion = async () => {
    if (!newQuestionContent.trim()) return;
    try {
      const response = await fetch(`/api/season/${seasonId}/questions`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrfToken,
          'Accept': 'application/json',
        },
        body: JSON.stringify({ content: newQuestionContent }),
      });
      if (response.ok) {
        setIsAsking(false);
        setNewQuestionContent('');
        fetchQuestions();
      }
    } catch (err) {
      console.error(err);
    }
  };

  const handleUpdateQuestion = async () => {
    if (!editingQuestion || !editContent.trim()) return;
    try {
      const response = await fetch(`/api/questions/${editingQuestion.id}`, {
        method: 'PATCH',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrfToken,
          'Accept': 'application/json',
        },
        body: JSON.stringify({ content: editContent }),
      });
      if (response.ok) {
        setEditingQuestion(null);
        fetchQuestions();
      }
    } catch (err) {
      console.error(err);
    }
  };

  const handleAnswerQuestion = async () => {
    if (!answeringQuestion || !answerContent.trim()) return;
    try {
      const response = await fetch(`/api/questions/${answeringQuestion.id}/answer`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrfToken,
          'Accept': 'application/json',
        },
        body: JSON.stringify({ answer: answerContent }),
      });
      if (response.ok) {
        setAnsweringQuestion(null);
        fetchQuestions();
      }
    } catch (err) {
      console.error(err);
    }
  };

  const handleDeleteQuestion = async (id: number) => {
    if (!confirm('Are you sure you want to delete this question?')) return;
    try {
      const response = await fetch(`/api/questions/${id}`, {
        method: 'DELETE',
        headers: {
          'X-CSRF-TOKEN': csrfToken,
          'Accept': 'application/json',
        },
      });
      if (response.ok) {
        fetchQuestions();
      }
    } catch (err) {
      console.error(err);
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
        <ShadcnButton onClick={() => setIsAsking(true)}>
          <MessageSquare className="w-4 h-4 mr-2" />
          Ask a Question
        </ShadcnButton>
      </div>

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
                  <div className="prose prose-sm dark:prose-invert max-w-none">
                    <MDXEditor 
                        markdown={question.content} 
                        readOnly 
                        plugins={[headingsPlugin(), listsPlugin(), quotePlugin(), thematicBreakPlugin()]}
                    />
                  </div>
                </div>
                <div className="flex flex-col items-center gap-2">
                  <ShadcnButton 
                    variant={question.user_has_upvoted ? "default" : "outline"} 
                    size="sm" 
                    className="h-9 px-3"
                    onClick={() => handleUpvote(question)}
                  >
                    <ThumbsUp className={`w-4 h-4 mr-2 ${question.user_has_upvoted ? 'fill-current' : ''}`} />
                    {question.upvotes_count}
                  </ShadcnButton>
                  
                  {(!question.answer && (isAdmin || question.user_id === Number(document.getElementById('dashboard')?.getAttribute('data-user-id')))) && (
                    <div className="flex gap-1 mt-2">
                         {question.user_id === Number(document.getElementById('dashboard')?.getAttribute('data-user-id')) && (
                            <ShadcnButton variant="ghost" size="icon" className="h-8 w-8" onClick={() => {
                                setEditingQuestion(question);
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
                      <span className="text-sm font-semibold mb-2">Answer from {question.answeredBy?.name || 'Admin'}:</span>
                      <div className="prose prose-sm dark:prose-invert max-w-none">
                        <MDXEditor 
                            markdown={question.answer} 
                            readOnly 
                            plugins={[headingsPlugin(), listsPlugin(), quotePlugin(), thematicBreakPlugin()]}
                        />
                      </div>
                      {isAdmin && (
                        <ShadcnButton variant="ghost" size="sm" className="mt-4 self-start" onClick={() => {
                            setAnsweringQuestion(question);
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
                    setAnsweringQuestion(question);
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
      <Dialog open={isAsking} onOpenChange={setIsAsking}>
        <DialogContent className="max-w-2xl">
          <DialogHeader>
            <DialogTitle>Ask a Question</DialogTitle>
            <DialogDescription>
              Use Markdown to format your question. Headings, lists, and bold text are supported.
            </DialogDescription>
          </DialogHeader>
          <div className="border rounded-md min-h-[200px] mt-4">
            <MDXEditor 
                markdown={newQuestionContent} 
                onChange={setNewQuestionContent}
                placeholder="Type your question here..."
                plugins={[
                    headingsPlugin(), 
                    listsPlugin(), 
                    quotePlugin(), 
                    thematicBreakPlugin(),
                    markdownShortcutPlugin(),
                ]}
            />
          </div>
          <DialogFooter>
            <ShadcnButton variant="outline" onClick={() => setIsAsking(false)}>Cancel</ShadcnButton>
            <ShadcnButton onClick={handleStoreQuestion}>
                <Send className="w-4 h-4 mr-2" />
                Post Question
            </ShadcnButton>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Edit Question Dialog */}
      <Dialog open={!!editingQuestion} onOpenChange={() => setEditingQuestion(null)}>
        <DialogContent className="max-w-2xl">
          <DialogHeader>
            <DialogTitle>Edit Question</DialogTitle>
          </DialogHeader>
          <div className="border rounded-md min-h-[200px] mt-4">
            <MDXEditor 
                markdown={editContent} 
                onChange={setEditContent}
                plugins={[
                    headingsPlugin(), 
                    listsPlugin(), 
                    quotePlugin(), 
                    thematicBreakPlugin(),
                    markdownShortcutPlugin(),
                ]}
            />
          </div>
          <DialogFooter>
            <ShadcnButton variant="outline" onClick={() => setEditingQuestion(null)}>Cancel</ShadcnButton>
            <ShadcnButton onClick={handleUpdateQuestion}>Save Changes</ShadcnButton>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Answer Question Dialog */}
      <Dialog open={!!answeringQuestion} onOpenChange={() => setAnsweringQuestion(null)}>
        <DialogContent className="max-w-2xl">
          <DialogHeader>
            <DialogTitle>{answeringQuestion?.answer ? 'Edit Answer' : 'Post Answer'}</DialogTitle>
          </DialogHeader>
          <div className="border rounded-md min-h-[200px] mt-4">
            <MDXEditor 
                markdown={answerContent} 
                onChange={setAnswerContent}
                placeholder="Type your answer here..."
                plugins={[
                    headingsPlugin(), 
                    listsPlugin(), 
                    quotePlugin(), 
                    thematicBreakPlugin(),
                    markdownShortcutPlugin(),
                ]}
            />
          </div>
          <DialogFooter>
            <ShadcnButton variant="outline" onClick={() => setAnsweringQuestion(null)}>Cancel</ShadcnButton>
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
