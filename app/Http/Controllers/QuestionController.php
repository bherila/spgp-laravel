<?php

namespace App\Http\Controllers;

use App\Models\Question;
use App\Models\QuestionUpvote;
use App\Models\Season;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class QuestionController extends Controller
{
    /**
     * Display the Q&A page for a season.
     */
    public function index(Season $season)
    {
        // Check if user has access to this season
        if (!Auth::user()->isAdmin()) {
            $now = now();
            $threeMonthsFromNow = $now->copy()->addMonths(3);
            
            $isActive = ($season->start_date <= $now && $season->final_deadline >= $now) ||
                        ($season->start_date > $now && $season->start_date <= $threeMonthsFromNow) ||
                        $season->passRequests()->where('user_id', Auth::id())->exists();

            if (!$isActive) {
                abort(403, 'You do not have access to this season.');
            }
        }

        return view('questions', [
            'season' => $season,
        ]);
    }

    /**
     * Get all questions for a season.
     */
    public function getQuestions(Season $season)
    {
        $questions = $season->questions()
            ->with(['user:id,name', 'answeredBy:id,name'])
            ->withCount('upvotes')
            ->orderBy('upvotes_count', 'desc')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($question) {
                $question->user_has_upvoted = $question->upvotes()->where('user_id', Auth::id())->exists();
                return $question;
            });

        return response()->json($questions);
    }

    /**
     * Store a new question.
     */
    public function store(Request $request, Season $season)
    {
        $validated = $request->validate([
            'content' => 'required|string',
        ]);

        $question = new Question();
        $question->user_id = Auth::id();
        $question->content = $validated['content'];
        $question->save();

        $question->seasons()->attach($season->id);

        return response()->json($question->load(['user:id,name', 'answeredBy:id,name'])->setAttribute('upvotes_count', 0)->setAttribute('user_has_upvoted', false));
    }

    /**
     * Update a question (user only if not answered).
     */
    public function update(Request $request, Question $question)
    {
        if ($question->user_id !== Auth::id()) {
            abort(403);
        }

        if ($question->isAnswered()) {
            return response()->json(['message' => 'Cannot edit a question that has already been answered.'], 422);
        }

        $validated = $request->validate([
            'content' => 'required|string',
        ]);

        $question->update(['content' => $validated['content']]);

        return response()->json($question);
    }

    /**
     * Answer a question (admin only).
     */
    public function answer(Request $request, Question $question)
    {
        if (!Auth::user()->isAdmin()) {
            abort(403);
        }

        $validated = $request->validate([
            'answer' => 'required|string',
            'seasons' => 'sometimes|array',
            'seasons.*' => 'exists:seasons,id',
        ]);

        $question->update([
            'answer' => $validated['answer'],
            'answered_by' => Auth::id(),
            'answered_at' => now(),
        ]);

        if (isset($validated['seasons'])) {
            $question->seasons()->sync($validated['seasons']);
        }

        return response()->json($question->load(['user:id,name', 'answeredBy:id,name']));
    }

    /**
     * Upvote a question.
     */
    public function upvote(Question $question)
    {
        $upvote = QuestionUpvote::firstOrCreate([
            'question_id' => $question->id,
            'user_id' => Auth::id(),
        ]);

        return response()->json(['success' => true]);
    }

    /**
     * Remove upvote.
     */
    public function unvote(Question $question)
    {
        QuestionUpvote::where('question_id', $question->id)
            ->where('user_id', Auth::id())
            ->forceDelete();

        return response()->json(['success' => true]);
    }

    /**
     * Delete a question.
     */
    public function destroy(Question $question)
    {
        if ($question->user_id !== Auth::id() && !Auth::user()->isAdmin()) {
            abort(403);
        }

        $question->delete();

        return response()->json(['success' => true]);
    }
}
