@props([
    // Alpine expression for the index of the category this chip sits in, or the literal 'null'
    // for the ungrouped bucket. Used to disable the chip's own category in the move-to select.
    'categoryIndex' => 'null',
])

{{-- A draggable room chip. Expects `displayId` in the surrounding Alpine x-for scope. --}}
<span draggable="true"
      x-on:dragstart="startDrag(displayId)"
      x-on:dragend="draggingId = null"
      x-on:dragover.prevent
      x-on:drop.prevent.stop="dropBefore({{ $categoryIndex }}, displayId)"
      x-bind:class="[
          draggingId === displayId ? 'opacity-50' : '',
          isOnBoard(displayId) ? 'bg-white text-gray-900' : 'bg-gray-50 text-gray-400',
      ]"
      class="inline-flex items-center gap-1.5 rounded-md border border-gray-300 py-1 pl-2 pr-1 text-sm cursor-move">
    <span class="text-gray-400 select-none" aria-hidden="true">&#x283F;</span>
    <span x-text="displayLabel(displayId)"></span>
    <span x-show="!isOnBoard(displayId)" class="text-xs italic">not on this board</span>
    <select x-on:change="moveTo(displayId, $event.target.value); $event.target.value = ''"
            title="Move this room to another category"
            aria-label="Move this room to another category"
            class="w-14 rounded border-gray-300 py-0 pl-1 pr-4 text-xs text-gray-500 focus:border-blue-500 focus:ring-blue-500">
        <option value="">&rarr;</option>
        <template x-for="(category, index) in categories" :key="index">
            <option x-bind:value="index"
                    x-bind:disabled="index === {{ $categoryIndex }}"
                    x-text="category.name || 'Unnamed category'"></option>
        </template>
        <option value="none" x-bind:disabled="{{ $categoryIndex }} === null">Other (ungrouped)</option>
    </select>
</span>
