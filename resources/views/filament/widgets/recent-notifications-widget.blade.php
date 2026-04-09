<div class="space-y-4">
    @if($unreadCount > 0)
        <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
            <div class="flex items-center">
                <svg class="w-5 h-5 text-blue-600 dark:text-blue-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                </svg>
                <span class="text-blue-800 dark:text-blue-200 font-medium">
                    You have {{ $unreadCount }} unread notification{{ $unreadCount > 1 ? 's' : '' }}
                </span>
            </div>
        </div>
    @endif

    @if($notifications->count() > 0)
        <div class="space-y-3">
            @foreach($notifications as $notification)
                <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4 shadow-sm @if(!$notification['read_at']) border-l-4 @endif"
                     @if(!$notification['read_at'])
                     style="border-left-color: {{ $notification['type'] === 'success' ? '#10b981' : ($notification['type'] === 'error' ? '#ef4444' : '#3b82f6') }}"
                     @endif>
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            @if(str_contains($notification['icon'], 'heroicon'))
                                <svg class="w-6 h-6 {{ $notification['type'] === 'success' ? 'text-green-600' : ($notification['type'] === 'error' ? 'text-red-600' : 'text-blue-600') }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    @if($notification['icon'] === 'heroicon-o-document-arrow-down')
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                    @elseif($notification['icon'] === 'heroicon-o-exclamation-triangle')
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                                    @else
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                                    @endif
                                </svg>
                            @else
                                <div class="w-6 h-6 bg-gray-300 dark:bg-gray-600 rounded-full flex items-center justify-center">
                                    <i class="{{ $notification['icon'] }} text-white text-sm"></i>
                                </div>
                            @endif
                        </div>
                        <div class="ml-3 flex-1">
                            <div class="flex items-center justify-between">
                                <h3 class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                    {{ $notification['title'] }}
                                </h3>
                                <span class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ $notification['created_at'] }}
                                </span>
                            </div>
                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                                {{ $notification['message'] }}
                            </p>
                            @if($notification['action_url'] && $notification['action_text'])
                                <div class="mt-2">
                                    <a href="{{ $notification['action_url'] }}"
                                       target="_blank"
                                       class="inline-flex items-center px-3 py-1 border border-transparent text-xs font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                        {{ $notification['action_text'] }}
                                        <svg class="ml-1 w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                                        </svg>
                                    </a>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="text-center py-8">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
            </svg>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                No notifications yet
            </p>
        </div>
    @endif
</div>
