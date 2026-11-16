<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Objective;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ObjectiveController extends Controller
{
    public function index()
    {
        return view('admin.objectives.index', [
            'objectives' => Objective::withCount('tours')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:80', 'unique:objectives,name']]);
        $objective = Objective::create(['name' => $data['name'], 'slug' => Str::slug($data['name'])]);
        Audit::log('objective.created', $objective);

        return back()->with('success', 'Objective added.');
    }

    public function update(Request $request, Objective $objective)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80', Rule::unique('objectives', 'name')->ignore($objective->id)],
        ]);
        $objective->update(['name' => $data['name'], 'slug' => Str::slug($data['name'])]);
        Audit::log('objective.updated', $objective);

        return back()->with('success', 'Objective renamed.');
    }

    public function destroy(Objective $objective)
    {
        $count = $objective->tours()->count();
        if ($count > 0) {
            return back()->with('error', "This objective is used by {$count} tour(s). Move them to another objective first.");
        }

        Audit::log('objective.deleted', $objective, ['name' => $objective->name]);
        $objective->delete();

        return back()->with('success', 'Objective deleted.');
    }
}
