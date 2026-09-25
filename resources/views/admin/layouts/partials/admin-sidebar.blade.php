<div class="flex flex-col h-full">
    <!-- Logo -->
    <div class="flex items-center justify-center h-16 px-6 bg-blue-600 text-white">
        <h1 class="text-xl font-bold">{{ config('app.name') }}</h1>
    </div>
    
    <!-- Navigation -->
    <nav class="flex-1 px-4 py-6 space-y-2 overflow-y-auto">
        <!-- Dashboard -->
        <x-admin.sidebar-link 
            :href="route('admin.dashboard')" 
            :active="request()->routeIs('admin.dashboard')"
            icon="home">
            Dashboard
        </x-admin.sidebar-link>
        
        <!-- Pages Management -->
        <div class="space-y-1">
            <div class="text-xs font-semibold text-gray-400 uppercase tracking-wider px-3 py-2">
                Content
            </div>
            
            <x-admin.sidebar-link 
                :href="route('admin.pages.index')" 
                :active="request()->routeIs('admin.pages.*')"
                icon="document-text">
                Pages
            </x-admin.sidebar-link>
        </div>
        
        <!-- Platform -->
        <div class="space-y-1">
            <div class="text-xs font-semibold text-gray-400 uppercase tracking-wider px-3 py-2">
                Platform
            </div>
            
            <x-admin.sidebar-link 
                :href="route('admin.companies.index')" 
                :active="request()->routeIs('admin.companies.*')"
                icon="building-office">
                Businesses
            </x-admin.sidebar-link>
        </div>
        
        <!-- Users Management -->
        <div class="space-y-1">
            <div class="text-xs font-semibold text-gray-400 uppercase tracking-wider px-3 py-2">
                Users
            </div>
            
            <x-admin.sidebar-link 
                :href="route('admin.users.index')" 
                :active="request()->routeIs('admin.users.*')"
                icon="users">
                Users
            </x-admin.sidebar-link>
            
            <x-admin.sidebar-link 
                :href="route('admin.admins.index')" 
                :active="request()->routeIs('admin.admins.*')"
                icon="shield-check">
                Admins
            </x-admin.sidebar-link>
        </div>

        <!-- Xgenious -->
        <div class="space-y-1">
            <div class="text-xs font-semibold text-gray-400 uppercase tracking-wider px-3 py-2">
                Xgenious
            </div>
            @foreach(config('xgenious.software', []) as $item)
                <a href="{{ $item['url'] }}" target="_blank" rel="noopener noreferrer"
                   class="border-transparent text-gray-600 hover:bg-gray-50 hover:text-gray-900 group border-l-4 px-3 py-2 flex items-center text-sm font-medium transition-colors duration-200">
                    <svg class="text-gray-400 group-hover:text-gray-500 mr-3 flex-shrink-0 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                    </svg>
                    <span class="truncate flex-1">{{ $item['name'] }}</span>
                    <svg class="w-3 h-3 text-gray-400 flex-shrink-0 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
                </a>
            @endforeach
        </div>

        <!-- Help & Support -->
        <div class="space-y-1">
            <div class="text-xs font-semibold text-gray-400 uppercase tracking-wider px-3 py-2">
                Help & Support
            </div>
            <a href="{{ config('xgenious.contact_url') }}" target="_blank" rel="noopener noreferrer"
               class="border-transparent text-gray-600 hover:bg-gray-50 hover:text-gray-900 group border-l-4 px-3 py-2 flex items-center text-sm font-medium transition-colors duration-200">
                <svg class="text-gray-400 group-hover:text-gray-500 mr-3 flex-shrink-0 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z"></path>
                </svg>
                <span class="truncate flex-1">Contact / Report a Bug</span>
                <svg class="w-3 h-3 text-gray-400 flex-shrink-0 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
            </a>
            <a href="mailto:{{ config('xgenious.contact_email') }}"
               class="border-transparent text-gray-600 hover:bg-gray-50 hover:text-gray-900 group border-l-4 px-3 py-2 flex items-center text-sm font-medium transition-colors duration-200">
                <svg class="text-gray-400 group-hover:text-gray-500 mr-3 flex-shrink-0 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                </svg>
                <span class="truncate">{{ config('xgenious.contact_email') }}</span>
            </a>
        </div>
    </nav>
    
    <!-- User Info -->
    @auth('admin')
    <div class="p-4 border-t border-gray-200">
        <div class="flex items-center space-x-3">
            <div class="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center">
                <span class="text-white text-sm font-medium">
                    {{ substr(auth('admin')->user()->name, 0, 1) }}
                </span>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-gray-900 truncate">
                    {{ auth('admin')->user()->name }}
                </p>
                <p class="text-xs text-gray-500 truncate">
                    {{ auth('admin')->user()->email }}
                </p>
            </div>
        </div>
    </div>
    @endauth
</div>
