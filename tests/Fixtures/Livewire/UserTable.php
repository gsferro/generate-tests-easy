<?php

namespace Tests\Fixtures\Livewire;

use Livewire\Component;
use Livewire\WithPagination;
use Tests\Fixtures\Models\User;

class UserTable extends Component
{
    use WithPagination;

    public $search = '';
    public $perPage = 10;
    public $sortField = 'name';
    public $sortDirection = 'asc';
    public $filters = [
        'active' => null,
        'type' => null,
    ];

    protected $queryString = [
        'search' => ['except' => ''],
        'sortField' => ['except' => 'name'],
        'sortDirection' => ['except' => 'asc'],
    ];

    protected $listeners = [
        'refreshUsers' => '$refresh',
        'deleteUser' => 'deleteUser',
    ];

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function sortBy($field)
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortDirection = 'asc';
        }

        $this->sortField = $field;
    }

    public function deleteUser($userId)
    {
        $user = User::find($userId);
        if ($user) {
            $user->delete();
            $this->emit('userDeleted', $userId);
        }
    }

    public function resetFilters()
    {
        $this->reset('filters');
    }

    public function render()
    {
        $users = User::query()
            ->when($this->search, function ($query) {
                $query->where(function ($query) {
                    $query->where('name', 'like', '%' . $this->search . '%')
                        ->orWhere('email', 'like', '%' . $this->search . '%');
                });
            })
            ->when($this->filters['active'] !== null, function ($query) {
                $query->where('active', $this->filters['active']);
            })
            ->when($this->filters['type'], function ($query) {
                $query->where('type', $this->filters['type']);
            })
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate($this->perPage);

        return view('livewire.user-table', [
            'users' => $users,
        ]);
    }
}