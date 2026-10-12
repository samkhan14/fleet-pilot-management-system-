<?php

namespace App\Http\Controllers;

use App\Domain\Reporting\OperationalReport;
use App\Http\Controllers\Concerns\UsesOrganization;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    use UsesOrganization;

    public function index(Request $request, OperationalReport $reports): Response
    {
        $ctx = $this->authorizePermission('reports.read');
        $from = $request->string('from', now($ctx->organization->timezone)->startOfMonth()->toDateString())->toString();
        $to = $request->string('to', now($ctx->organization->timezone)->toDateString())->toString();

        return Inertia::render('reports/index', [
            'report' => $reports->build($ctx, $from, $to),
        ]);
    }
}
