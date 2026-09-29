<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiUsage;
use App\Models\Provider;
use App\Models\SystemSetting;
use App\Services\Ai\AdminPrompts;
use App\Services\Analytics\DateRange;
use App\Services\Backoffice\CopilotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The AI copilot.
 *
 * Every call is advisory: the model is handed aggregates, never a model
 * instance, and its output is stored as text. Nothing on this screen can move
 * money or change an order.
 */
class AiController extends Controller
{
    public function __construct(private readonly CopilotService $copilot) {}

    public function index(): View
    {
        return view('admin.ai.index', $this->copilot->console());
    }

    public function generate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'provider_id' => ['required', 'integer', Rule::exists('providers', 'id')],
            'task' => ['required', Rule::in(array_keys(AdminPrompts::TASKS))],
            'model' => ['nullable', 'string', 'max:160'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $provider = Provider::query()->where('type', 'ai')->where('is_active', true)->find($validated['provider_id']);

        if ($provider === null) {
            return response()->json([
                'success' => false,
                'error' => 'Provider AI tidak ditemukan atau sedang nonaktif.',
            ], 422);
        }

        $range = DateRange::fromRequest($request);
        $result = $this->copilot->run(
            $provider,
            (string) $validated['task'],
            $range,
            $validated['model'] ?? null,
            $validated['note'] ?? null,
            auth('admin')->id(),
        );

        return response()->json([
            'success' => $result['success'],
            'content' => $result['content'],
            'model' => $result['model'],
            'tokens' => $result['tokens'],
            'duration_ms' => $result['duration_ms'],
            'error' => $result['error'],
            'advisory' => true,
        ], $result['success'] ? 200 : 502);
    }

    public function prompts(): View
    {
        return view('admin.ai.prompts', [
            'tasks' => AdminPrompts::catalogue(),
            'guardrail' => AdminPrompts::GUARDRAIL,
        ]);
    }

    public function updatePrompts(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'task' => ['required', Rule::in(array_keys(AdminPrompts::TASKS))],
            'system' => ['required', 'string', 'min:20', 'max:4000'],
        ]);

        SystemSetting::set(AdminPrompts::settingKey((string) $validated['task']), (string) $validated['system']);

        return back()->with('success', 'Prompt untuk '.AdminPrompts::taskLabel((string) $validated['task']).' disimpan.');
    }

    public function resetPrompt(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'task' => ['required', Rule::in(array_keys(AdminPrompts::TASKS))],
        ]);

        AdminPrompts::reset((string) $validated['task']);

        return back()->with('success', 'Prompt dikembalikan ke bawaan sistem.');
    }

    public function usage(Request $request): View
    {
        return view('admin.ai.usage', $this->copilot->usageReport(
            (int) $request->query('page', 1),
            (int) $request->query('per_page', 25),
            (string) $request->query('feature', ''),
        ));
    }

    public function destroyUsage(AiUsage $usage): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->isSuperAdmin(), 403, 'Hanya super admin yang dapat menghapus riwayat pemakaian AI.');

        $usage->delete();

        return back()->with('success', 'Riwayat pemakaian AI dihapus.');
    }
}
