{{-- Shared form partial for create/edit --}}
<div class="space-y-5">
    {{-- Name --}}
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Habit Name <span class="text-red-500">*</span></label>
        <input type="text" name="name" value="{{ old('name', $habit->name ?? '') }}"
            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('name') border-red-400 @enderror"
            placeholder="e.g. Morning Exercise" required maxlength="100">
        @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
    </div>

    {{-- Description --}}
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
        <textarea name="description" rows="2"
            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
            placeholder="Optional description..." maxlength="500">{{ old('description', $habit->description ?? '') }}</textarea>
    </div>

    {{-- Color & Frequency --}}
    <div class="grid grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Color</label>
            <div class="flex items-center gap-2">
                <input type="color" name="color" value="{{ old('color', $habit->color ?? '#6366f1') }}"
                    class="h-9 w-16 rounded border border-gray-300 cursor-pointer p-0.5">
                <input type="text" id="colorHex" value="{{ old('color', $habit->color ?? '#6366f1') }}"
                    class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono" readonly>
            </div>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Frequency</label>
            <select name="frequency"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <option value="daily" {{ old('frequency', $habit->frequency ?? 'daily') === 'daily' ? 'selected' : '' }}>Daily</option>
                <option value="weekly" {{ old('frequency', $habit->frequency ?? '') === 'weekly' ? 'selected' : '' }}>Weekly</option>
            </select>
        </div>
    </div>

    {{-- Icon shortname --}}
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Icon Label</label>
        <input type="text" name="icon" value="{{ old('icon', $habit->icon ?? 'check-circle') }}"
            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
            placeholder="e.g. check-circle" maxlength="50">
        <p class="text-xs text-gray-400 mt-1">A label for the habit icon (decorative).</p>
    </div>

    @if (isset($habit) && $habit->exists)
    <div class="flex items-center gap-2">
        <input type="hidden" name="is_active" value="0">
        <input type="checkbox" name="is_active" id="is_active" value="1"
            {{ old('is_active', $habit->is_active) ? 'checked' : '' }}
            class="w-4 h-4 text-indigo-600 border-gray-300 rounded">
        <label for="is_active" class="text-sm font-medium text-gray-700">Active (show on dashboard)</label>
    </div>
    @endif
</div>

<script>
    document.querySelector('input[type="color"]').addEventListener('input', function () {
        document.getElementById('colorHex').value = this.value;
    });
</script>
