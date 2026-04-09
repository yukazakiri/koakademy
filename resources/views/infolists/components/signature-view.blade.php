<x-dynamic-component :component="$getEntryWrapperView()" :entry="$entry">
    <div>
      <img src="{{ $getState() ?? 'https://via.placeholder.com/150x150' }}" class="w-full h-20 rounded-xl">
    </div>
</x-dynamic-component>
