@extends('admin.layouts.admin')

@section('title', 'Dashboard')

@section('content')
    <!-- Stats Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6 mb-8">
        <x-admin.card class="text-center">
            <div class="text-3xl font-bold text-blue-600">{{ $stats['total_pages'] }}</div>
            <div class="text-sm text-gray-500 mt-1">Total Pages</div>
        </x-admin.card>
        
        <x-admin.card class="text-center">
            <div class="text-3xl font-bold text-green-600">{{ $stats['published_pages'] }}</div>
            <div class="text-sm text-gray-500 mt-1">Published</div>
        </x-admin.card>
        
        <x-admin.card class="text-center">
            <div class="text-3xl font-bold text-yellow-600">{{ $stats['draft_pages'] }}</div>
            <div class="text-sm text-gray-500 mt-1">Drafts</div>
        </x-admin.card>
        
        <x-admin.card class="text-center">
            <div class="text-3xl font-bold text-purple-600">{{ $stats['total_admins'] }}</div>
            <div class="text-sm text-gray-500 mt-1">Admins</div>
        </x-admin.card>
        
        <x-admin.card class="text-center">
            <div class="text-3xl font-bold text-indigo-600">{{ $stats['total_users'] }}</div>
            <div class="text-sm text-gray-500 mt-1">Users</div>
        </x-admin.card>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <!-- Recent Pages -->
        <x-admin.card title="Recent Pages">
            @if($recent_pages->count() > 0)
                <div class="space-y-4">
                    @foreach($recent_pages as $page)
                        <div class="flex items-center justify-between border-b border-gray-200 pb-3">
                            <div class="flex-1">
                                <h4 class="text-sm font-medium text-gray-900">{{ $page->title }}</h4>
                                <p class="text-xs text-gray-500">
                                    Created by {{ $page->creator->name }} • {{ $page->created_at->diffForHumans() }}
                                </p>
                            </div>
                            <div class="flex items-center space-x-2">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                    {{ $page->status === 'published' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' }}">
                                    {{ ucfirst($page->status) }}
                                </span>
                                <a href="{{ route('admin.pages.edit', $page) }}" class="text-blue-600 hover:text-blue-900 text-sm">
                                    Edit
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>
                
                <div class="mt-4 text-center">
                    <x-admin.button href="{{ route('admin.pages.index') }}" variant="outline" size="sm">
                        View All Pages
                    </x-admin.button>
                </div>
            @else
                <div class="text-center py-8">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">No pages yet</h3>
                    <p class="mt-1 text-sm text-gray-500">Get started by creating your first page.</p>
                    <div class="mt-6">
                        <x-admin.button href="{{ route('admin.pages.create') }}" variant="primary" size="sm">
                            Create Page
                        </x-admin.button>
                    </div>
                </div>
            @endif
        </x-admin.card>

        <!-- Quick Actions -->
        <x-admin.card title="Quick Actions">
            <div class="space-y-4">
                <x-admin.button href="{{ route('admin.pages.create') }}" variant="primary" class="w-full justify-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                    </svg>
                    Create New Page
                </x-admin.button>
                
                <x-admin.button href="{{ route('admin.pages.index') }}" variant="outline" class="w-full justify-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    Manage Pages
                </x-admin.button>
                
                <hr class="my-4">
                
                <div class="text-sm text-gray-600 space-y-2">
                    <h4 class="font-medium">System Status</h4>
                    <div class="flex items-center justify-between">
                        <span>Laravel Version</span>
                        <span class="text-green-600">{{ app()->version() }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span>PHP Version</span>
                        <span class="text-green-600">{{ PHP_VERSION }}</span>
                    </div>
                </div>
            </div>
        </x-admin.card>
    </div>

    <!-- Xgenious Free Software Showcase -->
    <div class="mt-8">
        <x-admin.card>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
                        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"></path></svg>
                        More Free Software by Xgenious
                    </h3>
                    <p class="text-sm text-gray-500 mt-1">MIT-licensed, self-hosted — free forever. Explore our other products.</p>
                </div>
                <a href="https://xgenious.com/free-software" target="_blank" rel="noopener noreferrer" class="inline-flex items-center text-sm font-medium text-blue-600 hover:text-blue-700 whitespace-nowrap">
                    View all free software
                    <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
                </a>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach(config('xgenious.software', []) as $item)
                    <a href="{{ $item['url'] }}" target="_blank" rel="noopener noreferrer" class="group block border border-gray-200 rounded-lg p-4 hover:border-blue-300 hover:bg-blue-50/50 transition-colors">
                        <div class="flex items-start justify-between gap-3">
                            <h4 class="text-sm font-semibold text-gray-900 group-hover:text-blue-700">{{ $item['name'] }}</h4>
                            <svg class="w-4 h-4 text-gray-400 group-hover:text-blue-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
                        </div>
                        <p class="text-xs text-gray-500 mt-1.5 line-clamp-2">{{ $item['description'] }}</p>
                        <span class="inline-flex items-center mt-3 text-xs font-medium text-blue-600 group-hover:text-blue-700">
                            View details
                            <svg class="w-3 h-3 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                        </span>
                    </a>
                @endforeach
            </div>
        </x-admin.card>
    </div>

    <!-- Help & Support -->
    <div class="mt-6">
        <x-admin.card>
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-6">
                <div class="flex-1">
                    <h3 class="text-base font-semibold text-gray-900 flex items-center gap-2">
                        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                        Need help or found a bug?
                    </h3>
                    <p class="text-sm text-gray-500 mt-1">Contact the Xgenious team — we typically respond within one business day (Sun–Thu).</p>
                    <div class="flex flex-wrap items-center gap-3 mt-4">
                        <a href="{{ config('xgenious.contact_url') }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700 transition-colors">
                            Contact / Report a Bug
                            <svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
                        </a>
                        <a href="mailto:{{ config('xgenious.contact_email') }}" class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                            {{ config('xgenious.contact_email') }}
                        </a>
                        <a href="https://xgenious.com/free-software" target="_blank" rel="noopener noreferrer" class="text-sm text-gray-500 hover:text-gray-700">xgenious.com</a>
                    </div>
                </div>
                <div class="hidden md:block text-gray-300">
                    <svg class="w-20 h-20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                </div>
            </div>
        </x-admin.card>
    </div>
@endsection