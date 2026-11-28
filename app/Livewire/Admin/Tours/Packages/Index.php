<?php

namespace App\Livewire\Admin\Tours\Packages;

use App\Models\Tour;
use Livewire\Component;

class Index extends Component
{
    public Tour $tour;

    public function mount(Tour $tour): void
    {
        $this->tour = $tour;
    }

    public function render()
    {
        $packages = $this->tour->packages()
            ->withCount('days')
            ->get();

        return view('livewire.admin.tours.packages.index', [
            'tour' => $this->tour,
            'packages' => $packages,
        ])->extends('admin.layouts.master')
          ->section('content');
    }
}
