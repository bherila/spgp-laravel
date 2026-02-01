<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailLog;
use Illuminate\Http\Request;

class EmailLogController extends Controller
{
    /**
     * Display the email log admin page.
     */
    public function index()
    {
        return view('admin.email-log');
    }

    /**
     * Get paginated email logs with optional filtering.
     */
    public function list(Request $request)
    {
        $page = $request->input('page', 1);
        $limit = min($request->input('limit', 50), 100); // Max 100 per page
        $to = $request->input('to');
        $body = $request->input('body');

        $query = EmailLog::query()
            ->orderBy('created_at', 'desc');

        if ($to) {
            $query->where('email_to', 'like', "%{$to}%");
        }

        if ($body) {
            $query->where('body', 'like', "%{$body}%");
        }

        $total = $query->count();
        $emailLogs = $query
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get();

        return response()->json([
            'emailLogs' => $emailLogs,
            'total' => $total,
            'page' => (int) $page,
            'limit' => (int) $limit,
        ]);
    }
}
