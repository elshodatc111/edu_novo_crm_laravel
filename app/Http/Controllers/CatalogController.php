<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Support\Catalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CatalogController extends Controller
{
    public function index(string $catalog)
    {
        $def = Catalog::get($catalog);
        abort_unless(auth()->user()->can($def['view_permission'] ?? $def['permission']) || auth()->user()->can($def['permission']), 403);
        $model = $def['model'];

        $items = $model::query()
            ->when($catalog === 'lesson-times', fn ($q) => $q->orderBy('starts_at'), fn ($q) => $q->orderBy('name'))
            ->get();

        return view('catalog.index', compact('def', 'catalog', 'items'));
    }

    public function store(Request $request, string $catalog): RedirectResponse
    {
        $def = $this->definition($catalog);
        $data = $this->validated($request, $def);
        $this->assertUnique($def, $data);

        $item = $def['model']::create($data);
        AuditLog::record("{$catalog}.created", $item, "{$def['singular']} qo'shildi");

        return back()->with('success', "{$def['singular']} qo'shildi.");
    }

    public function update(Request $request, string $catalog, int $id): RedirectResponse
    {
        $def = $this->definition($catalog);
        $item = $def['model']::findOrFail($id);
        $data = $this->validated($request, $def);
        $this->assertUnique($def, $data, $item->id);

        $item->update($data);
        AuditLog::record("{$catalog}.updated", $item, "{$def['singular']} yangilandi");

        return back()->with('success', 'Saqlandi.');
    }

    /** Ishlatilgan yozuvni o'chirib bo'lmaydi, shuning uchun faolsizlantiriladi. */
    public function toggle(string $catalog, int $id): RedirectResponse
    {
        $def = $this->definition($catalog);
        $item = $def['model']::findOrFail($id);
        $item->update(['is_active' => ! $item->is_active]);

        AuditLog::record("{$catalog}.toggled", $item, $item->is_active ? 'Faollashtirildi' : 'Faolsizlantirildi');

        return back()->with('success', $item->is_active ? 'Faollashtirildi.' : "Faolsizlantirildi (yangi guruhlarda ko'rinmaydi).");
    }

    private function definition(string $catalog): array
    {
        $def = Catalog::get($catalog);
        $this->authorize($def['permission']);

        return $def;
    }

    private function validated(Request $request, array $def): array
    {
        $rules = collect($def['fields'])->map(fn ($f) => $f['rules'])->all();
        $attributes = collect($def['fields'])->map(fn ($f) => $f['label'])->all();

        return $request->validate($rules, [], $attributes);
    }

    private function assertUnique(array $def, array $data, ?int $ignoreId = null): void
    {
        $query = $def['model']::query()->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId));
        foreach ($def['unique'] as $column) {
            $query->where($column, $data[$column]);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([$def['unique'][0] => 'Bunday yozuv allaqachon mavjud.']);
        }
    }
}
