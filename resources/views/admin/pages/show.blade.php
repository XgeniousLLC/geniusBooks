@extends('admin.layouts.admin')

@section('title', $page->title)

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ $page->title }}</h1>
            <p class="text-gray-600">/{{ $page->slug }}</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('admin.pages.edit', $page) }}">
                <x-admin.button>Edit</x-admin.button>
            </a>
            <a href="{{ route('admin.pages.index') }}">
                <x-admin.button variant="secondary">Back</x-admin.button>
            </a>
        </div>
    </div>

    <x-admin.card>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm">
            <div>
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Status</p>
                <p class="mt-1 text-gray-900 capitalize">{{ $page->status }}</p>
            </div>
            <div>
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Created by</p>
                <p class="mt-1 text-gray-900">{{ $page->creator?->name ?? '—' }}</p>
            </div>
            <div>
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Updated</p>
                <p class="mt-1 text-gray-900">{{ $page->updated_at?->diffForHumans() }}</p>
            </div>
        </div>
    </x-admin.card>

    <x-admin.card title="Content">
        <div class="prose max-w-none text-gray-700">{!! $page->content !!}</div>
    </x-admin.card>

    @if($page->metaInformation)
        <x-admin.card title="SEO">
            <div class="space-y-4 text-sm">
                <div>
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Meta title</p>
                    <p class="mt-1 text-gray-900">{{ $page->metaInformation->effective_meta_title }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Meta description</p>
                    <p class="mt-1 text-gray-900">{{ $page->metaInformation->effective_meta_description }}</p>
                </div>
                @if($seoAnalysis)
                    <div>
                        <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">SEO score</p>
                        <p class="mt-1 text-gray-900">{{ $seoAnalysis['score'] }} / 100 ({{ $seoAnalysis['grade'] }})</p>
                    </div>
                @endif
            </div>
        </x-admin.card>
    @endif
</div>
@endsection
