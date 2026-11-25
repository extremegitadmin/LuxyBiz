<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Insurance;
use App\Support\Audit;

class InsuranceController extends Controller
{
    public function index()
    {
        return view('admin.insurance.index', ['options' => Insurance::orderBy('sort_order')->orderBy('id')->get()]);
    }

    public function store(Request $request)
    {
        $option = Insurance::create($this->validated($request) + ['sort_order' => (int) Insurance::max('sort_order') + 1]);
        Audit::log('insurance.created', $option);

        return redirect()->back()->with('success', "Insurance Option added");
    }

    public function update(Request $request, Insurance $option)
    {
        $option->update($this->validated($request));
        Audit::log('insurance.updated', $option);

        return redirect()->back()->with('success', 'Insurance Option saved.');
    }

    public function destroy(Insurance $option)
    {
        Audit::log('insurance.deleted', $option, ['label' => $option->label]);
        $option->delete();

        return redirect()->back()->with('success', 'Insurance Option deleted.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'price' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);
        $data['is_active'] = $request->boolean('is_active');
        if (! isset($data['sort_order'])) {
            unset($data['sort_order']);
        }

        return $data;
    }
}
