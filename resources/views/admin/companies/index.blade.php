@extends('admin.layouts.admin')

@section('title', 'Businesses')

@section('content')
<div class="space-y-6">
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Businesses</h1>
            <p class="text-gray-600">Manage tenant workspaces on the platform</p>
        </div>
    </div>

    <x-admin.card>
        <form method="GET" class="flex flex-col sm:flex-row gap-4 items-start sm:items-end">
            <div class="flex-1 min-w-0">
                <label for="search" class="block text-sm font-medium text-gray-700 mb-2">Search</label>
                <x-admin.input type="text" id="search" name="search" placeholder="Name or email" value="{{ request('search') }}" />
            </div>

            <div class="w-full sm:w-auto sm:min-w-[140px]">
                <label for="status" class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                <x-admin.select id="status" name="status">
                    <option value="">All Status</option>
                    <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
                    <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Suspended</option>
                </x-admin.select>
            </div>

            <div class="flex gap-2 w-full sm:w-auto">
                <x-admin.button type="submit" class="flex-1 sm:flex-none">Filter</x-admin.button>
                <x-admin.button variant="secondary" type="button" onclick="window.location.href='{{ route('admin.companies.index') }}'" class="flex-1 sm:flex-none">Clear</x-admin.button>
            </div>
        </form>
    </x-admin.card>

    <x-admin.card :padding="false">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-300">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Business</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Currency</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Members</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($companies as $company)
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900">{{ $company->name }}</div>
                                <div class="text-sm text-gray-500">{{ $company->email ?? $company->slug }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ $company->currency }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ $company->users_count }}</td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($company->is_active)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Active</span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">Suspended</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                <a href="{{ route('admin.companies.show', $company) }}" class="text-blue-600 hover:text-blue-900 font-medium">View</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-10 text-center text-sm text-gray-500">No businesses found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-admin.card>

    {{ $companies->links() }}
</div>
@endsection
