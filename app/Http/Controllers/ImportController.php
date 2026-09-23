<?php

namespace App\Http\Controllers;

use App\Models\ImportBatch;
use App\Services\ImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ImportController extends Controller
{
    public function __construct(private ImportService $import) {}

    public function index(Request $request)
    {
        $this->authorizeImport($request);

        return view('imports.index', [
            'batches' => ImportBatch::where('user_id', $request->user()->id)->latest('id')->limit(10)->get(['id', 'status', 'filename', 'summary', 'created_at']),
        ]);
    }

    public function template(Request $request)
    {
        $this->authorizeImport($request);

        $path = $this->import->template($request->user()->isSuperAdmin());

        return response()->download($path, 'oquvchilar-import-namuna.xlsx')->deleteFileAfterSend(true);
    }

    public function upload(Request $request): RedirectResponse
    {
        $this->authorizeImport($request);

        $request->validate(['file' => ['required', 'file', 'mimes:xlsx,csv,txt', 'max:10240']], [], ['file' => 'Fayl']);

        $batch = $this->import->preview($request->user(), $request->file('file'));

        return redirect()->route('imports.show', $batch);
    }

    public function show(Request $request, ImportBatch $batch)
    {
        $this->authorizeBatch($request, $batch);

        return view('imports.show', ['batch' => $batch, 'preview' => array_slice($batch->rows, 0, 300), 'problems' => collect($batch->rows)->where('status', '!=', 'ok')->take(100)]);
    }

    public function confirm(Request $request, ImportBatch $batch): RedirectResponse
    {
        $this->authorizeBatch($request, $batch);

        $result = $this->import->confirm($batch, $request->user());

        return redirect()->route('imports.show', $batch)->with('success', "{$result['created']} ta o'quvchi qo'shildi".($result['created_branches'] ? ", {$result['created_branches']} ta yangi filial ochildi" : '').'.');
    }

    public function cancel(Request $request, ImportBatch $batch): RedirectResponse
    {
        $this->authorizeBatch($request, $batch);

        $this->import->cancel($batch);

        return redirect()->route('imports.index')->with('success', 'Import bekor qilindi.');
    }

    /** Login va parollar fayli: bir marta yuklab olinadi va o'chiriladi. */
    public function result(Request $request, ImportBatch $batch)
    {
        $this->authorizeBatch($request, $batch);
        abort_unless($batch->result_path && Storage::disk('local')->exists($batch->result_path), 404);

        $content = Storage::disk('local')->get($batch->result_path);
        Storage::disk('local')->delete($batch->result_path);
        $batch->update(['result_path' => null]);

        return response($content, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="import-login-parollar.xlsx"',
        ]);
    }

    private function authorizeImport(Request $request): void
    {
        abort_unless($request->user()->can('students.import'), 403);
    }

    private function authorizeBatch(Request $request, ImportBatch $batch): void
    {
        $this->authorizeImport($request);
        abort_unless($batch->user_id === $request->user()->id, 404);
    }
}
