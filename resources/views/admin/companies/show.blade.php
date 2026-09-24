@extends('admin.layouts.admin')

@section('title', $company->name)

@section('content')
<div class="space-y-6">
    <div class="flex justify-between items-start">
        <div>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold text-gray-900">{{ $company->name }}</h1>
                @if($company->is_active)
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Active</span>
                @else
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">Suspended</span>
                @endif
            </div>
            <p class="text-gray-600">{{ $company->email ?? 'No email' }} · {{ $company->currency }} · {{ $company->timezone }}</p>
        </div>
        <a href="{{ route('admin.companies.index') }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; Back</a>
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
        @foreach([
            'Members' => $counts['users'],
            'Active members' => $counts['active_users'],
            'Pending invites' => $counts['pending_invitations'],
            'Document sequences' => $counts['document_sequences'],
            'Audit entries' => $counts['audit_logs'],
        ] as $label => $value)
            <x-admin.card>
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">{{ $label }}</p>
                <p class="mt-1 text-2xl font-bold text-gray-900">{{ $value }}</p>
            </x-admin.card>
        @endforeach
    </div>

    <x-admin.card title="Actions">
        <div class="flex flex-wrap gap-3">
            @if($company->is_active)
                <form method="POST" action="{{ route('admin.companies.suspend', $company) }}">
                    @csrf
                    <x-admin.button variant="danger" type="submit" onclick="return confirm('Suspend this business?')">Suspend</x-admin.button>
                </form>
            @else
                <form method="POST" action="{{ route('admin.companies.reactivate', $company) }}">
                    @csrf
                    <x-admin.button variant="success" type="submit">Reactivate</x-admin.button>
                </form>
            @endif

            <form method="POST" action="{{ route('admin.companies.impersonate', $company) }}">
                @csrf
                <x-admin.button variant="warning" type="submit" onclick="return confirm('Impersonate the owner of this business?')">Impersonate owner</x-admin.button>
            </form>
        </div>
    </x-admin.card>

    <x-admin.card :padding="false">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-medium text-gray-900">Members</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-300">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($members as $member)
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $member->name }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $member->email }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-right">
                                <form method="POST" action="{{ route('admin.companies.impersonate', $company) }}">
                                    @csrf
                                    <input type="hidden" name="user_id" value="{{ $member->id }}">
                                    <button type="submit" class="text-amber-600 hover:text-amber-800 text-sm font-medium">Impersonate</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-6 py-10 text-center text-sm text-gray-500">No members yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-admin.card>
</div>
@endsection
