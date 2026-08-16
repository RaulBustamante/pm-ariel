<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', User::class);

        $logs = AuditLog::query()
            ->with('user:id,name,email')
            ->when($request->filled('event'), fn ($query) => $query->where('event', $request->string('event')))
            ->when($request->filled('type'), fn ($query) => $query->where('auditable_type', $request->string('type')))
            ->latest('created_at')
            ->paginate(50)
            ->withQueryString();

        return view('admin.audit.index', ['logs' => $logs]);
    }
}
