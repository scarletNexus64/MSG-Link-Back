@extends('admin.layouts.app')

@section('title', 'Cadeaux')
@section('header', 'Gestion des cadeaux')

@section('content')
<div class="mb-6 flex justify-between items-center">
    <h2 class="text-2xl font-bold text-gray-800">Cadeaux</h2>
    <a href="{{ route('admin.gift-management.create') }}"
       class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 inline-flex items-center">
        <i class="fas fa-plus mr-2"></i>Nouveau cadeau
    </a>
</div>

@if(session('success'))
    <div class="mb-6 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
        <span class="block sm:inline">{{ session('success') }}</span>
    </div>
@endif

@if(session('error'))
    <div class="mb-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
        <span class="block sm:inline">{{ session('error') }}</span>
    </div>
@endif

<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Icône</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Image</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nom</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Catégorie</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Prix</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Statut</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse($gifts as $gift)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            #{{ $gift->id }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="w-10 h-10 rounded-lg flex items-center justify-center text-2xl"
                                 style="background-color: {{ $gift->background_color }}">
                                {{ $gift->icon }}
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @if($gift->emoji_image_path && $gift->emoji_image_url)
                                <div class="flex items-center gap-2">
                                    <img src="{{ $gift->emoji_image_url }}"
                                         alt="{{ $gift->icon }}"
                                         class="w-8 h-8 object-contain"
                                         onerror="this.style.display='none'; this.nextElementSibling.style.display='inline';">
                                    <span class="text-2xl" style="display:none;">{{ $gift->icon }}</span>
                                    <span class="px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-700">
                                        <i class="fas fa-check"></i> Généré
                                    </span>
                                </div>
                            @else
                                <div class="flex items-center gap-2">
                                    <span class="px-2 py-1 text-xs font-medium rounded-full bg-red-100 text-red-700">
                                        <i class="fas fa-times"></i> Non généré
                                    </span>
                                </div>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            <div>
                                <div class="text-sm font-medium text-gray-900">{{ $gift->name }}</div>
                                @if($gift->description)
                                    <div class="text-sm text-gray-500">{{ Str::limit($gift->description, 50) }}</div>
                                @endif
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            @if($gift->category)
                                <span class="px-2 py-1 text-xs font-medium rounded-full bg-purple-100 text-purple-700">
                                    {{ $gift->category->name }}
                                </span>
                            @else
                                <span class="text-gray-400">Aucune</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="text-sm font-semibold text-gray-900">{{ number_format($gift->price, 0, ',', ' ') }} FCFA</span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @if($gift->is_active)
                                <span class="px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-700">
                                    Actif
                                </span>
                            @else
                                <span class="px-2 py-1 text-xs font-medium rounded-full bg-gray-100 text-gray-700">
                                    Inactif
                                </span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <div class="flex items-center gap-2">
                                <a href="{{ route('admin.gift-management.edit', $gift) }}"
                                   class="text-primary-600 hover:text-primary-900"
                                   title="Modifier">
                                    <i class="fas fa-edit"></i>
                                </a>

                                <form action="{{ route('admin.gift-management.regenerate-image', $gift) }}"
                                      method="POST"
                                      class="inline-block"
                                      onsubmit="return confirm('Voulez-vous régénérer l\'image Twemoji pour ce cadeau ?');">
                                    @csrf
                                    <button type="submit"
                                            class="text-blue-600 hover:text-blue-900"
                                            title="Régénérer l'image">
                                        <i class="fas fa-sync-alt"></i>
                                    </button>
                                </form>

                                <form action="{{ route('admin.gift-management.destroy', $gift) }}"
                                      method="POST"
                                      class="inline-block"
                                      onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer ce cadeau ?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                            class="text-red-600 hover:text-red-900"
                                            title="Supprimer">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-6 py-12 text-center text-gray-500">
                            <i class="fas fa-gift text-4xl mb-4"></i>
                            <p>Aucun cadeau trouvé</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($gifts->hasPages())
        <div class="px-6 py-4 border-t border-gray-200">
            {{ $gifts->links() }}
        </div>
    @endif
</div>
@endsection
