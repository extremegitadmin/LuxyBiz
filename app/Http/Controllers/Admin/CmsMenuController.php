<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CmsMenu;
use App\Models\CmsMenuItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CmsMenuController extends Controller
{
    public function index()
    {
        $menus = CmsMenu::with('items')
            ->orderBy('name')
            ->get();

        return view('admin.cms.menus.index', compact('menus'));
    }

    public function edit(CmsMenu $menu)
    {
        $menu->load('allItems');

        return view('admin.cms.menus.edit', compact('menu'));
    }

    public function update(Request $request, CmsMenu $menu)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],

            'is_active' => ['required', 'boolean'],

            'items' => ['nullable', 'array'],

            'items.*.id' => [
                'nullable',
                'integer',
                Rule::exists('cms_menu_items', 'id')
                    ->where('menu_id', $menu->id),
            ],

            'items.*.parent_id' => [
                'nullable',
                'integer',
                Rule::exists('cms_menu_items', 'id')
                    ->where('menu_id', $menu->id),
            ],

            'items.*.label' => ['required', 'string', 'max:255'],
            'items.*.url' => ['nullable', 'string', 'max:255'],
            'items.*.target' => ['required', Rule::in(['_self', '_blank'])],
            'items.*.is_active' => ['nullable', 'boolean'],
        ]);

        DB::transaction(function () use ($validated, $menu) {

            // Update menu
            $menu->update([
                'name' => $validated['name'],
                'is_active' => $validated['is_active'],
            ]);

            $keepIds = [];

            foreach (($validated['items'] ?? []) as $position => $item) {

                $menuItem = !empty($item['id'])
                    ? CmsMenuItem::where('menu_id', $menu->id)
                        ->findOrFail($item['id'])
                    : new CmsMenuItem();

                $menuItem->fill([
                    'menu_id' => $menu->id,
                    'parent_id' => $item['parent_id'] ?? null,
                    'label' => $item['label'],
                    'url' => $item['url'] ?? null,
                    'target' => $item['target'],
                    'sort_order' => $item['sort_order'] ?? ($position + 1),
                    'is_active' => !empty($item['is_active']),
                ]);

                $menuItem->save();

                $keepIds[] = $menuItem->id;
            }

            // Delete removed menu items
            $menu->allItems()
                ->whereNotIn('id', $keepIds ?: [0])
                ->delete();
        });

        return redirect()
            ->route('admin.cms.menus.index')
            ->with('success', 'Menu updated successfully.');
    }
}
