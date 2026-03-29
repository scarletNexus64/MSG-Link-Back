@extends('admin.layouts.app')

@section('title', 'Nouveau cadeau')
@section('header', 'Créer un nouveau cadeau')

@section('content')
<div class="max-w-3xl">
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <form action="{{ route('admin.gift-management.store') }}" method="POST">
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="md:col-span-2">
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-2">
                        Nom du cadeau <span class="text-red-500">*</span>
                    </label>
                    <input type="text"
                           name="name"
                           id="name"
                           value="{{ old('name') }}"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-primary-500 focus:border-primary-500 @error('name') border-red-500 @enderror"
                           required>
                    @error('name')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="md:col-span-2">
                    <label for="description" class="block text-sm font-medium text-gray-700 mb-2">
                        Description
                    </label>
                    <textarea name="description"
                              id="description"
                              rows="3"
                              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-primary-500 focus:border-primary-500 @error('description') border-red-500 @enderror">{{ old('description') }}</textarea>
                    @error('description')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="md:col-span-2">
                    <label for="icon" class="block text-sm font-medium text-gray-700 mb-2">
                        Icône (Emoji) <span class="text-red-500">*</span>
                    </label>
                    <input type="text"
                           name="icon"
                           id="icon"
                           value="{{ old('icon', '🎁') }}"
                           maxlength="10"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-primary-500 focus:border-primary-500 @error('icon') border-red-500 @enderror"
                           required>
                    <p class="mt-1 text-xs text-gray-500">Utilisez un emoji (ex: 🎁, 💝, 🌹)</p>
                    @error('icon')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="background_color" class="block text-sm font-medium text-gray-700 mb-2">
                        Couleur de fond <span class="text-red-500">*</span>
                    </label>
                    <div class="flex gap-2">
                        <input type="color"
                               name="background_color"
                               id="background_color"
                               value="{{ old('background_color', '#FF6B6B') }}"
                               class="h-10 w-20 border border-gray-300 rounded cursor-pointer">
                        <input type="text"
                               id="background_color_text"
                               value="{{ old('background_color', '#FF6B6B') }}"
                               class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-primary-500 focus:border-primary-500"
                               readonly>
                    </div>
                    @error('background_color')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="price" class="block text-sm font-medium text-gray-700 mb-2">
                        Prix (FCFA) <span class="text-red-500">*</span>
                    </label>
                    <input type="number"
                           name="price"
                           id="price"
                           value="{{ old('price', 0) }}"
                           min="0"
                           step="0.01"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-primary-500 focus:border-primary-500 @error('price') border-red-500 @enderror"
                           required>
                    @error('price')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="gift_category_id" class="block text-sm font-medium text-gray-700 mb-2">
                        Catégorie
                    </label>
                    <select name="gift_category_id"
                            id="gift_category_id"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-primary-500 focus:border-primary-500 @error('gift_category_id') border-red-500 @enderror">
                        <option value="">Aucune catégorie</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}" {{ old('gift_category_id') == $category->id ? 'selected' : '' }}>
                                {{ $category->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('gift_category_id')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="md:col-span-2">
                    <label class="flex items-center">
                        <input type="checkbox"
                               name="is_active"
                               value="1"
                               {{ old('is_active', true) ? 'checked' : '' }}
                               class="rounded border-gray-300 text-primary-600 shadow-sm focus:border-primary-300 focus:ring focus:ring-primary-200 focus:ring-opacity-50">
                        <span class="ml-2 text-sm text-gray-700">Cadeau actif</span>
                    </label>
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-4">
                <a href="{{ route('admin.gift-management.index') }}"
                   class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                    Annuler
                </a>
                <button type="submit"
                        class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700">
                    <i class="fas fa-save mr-2"></i>Créer le cadeau
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    const colorInput = document.getElementById('background_color');
    const colorText = document.getElementById('background_color_text');

    colorInput.addEventListener('input', function() {
        colorText.value = this.value.toUpperCase();
    });
</script>
@endsection
