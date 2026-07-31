<x-guest-layout>
    <x-authentication-card>
        <x-slot name="logo">
            <x-authentication-card-logo />
        </x-slot>

        <div class="mb-4 text-sm text-gray-600">
            <strong>{{ $client->name }}</strong> {{ __('is requesting permission to access your Pluma account.') }}
        </div>

        @if (count($scopes) > 0)
            <div class="mb-4">
                <p class="text-sm font-medium text-gray-700">{{ __('This application will be able to:') }}</p>
                <ul class="mt-2 text-sm text-gray-600 list-disc list-inside">
                    @foreach ($scopes as $scope)
                        <li>{{ $scope->description }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="flex items-center justify-end mt-4 space-x-3">
            <form method="POST" action="{{ route('passport.authorizations.deny') }}">
                @csrf
                @method('DELETE')
                <input type="hidden" name="state" value="{{ $request->state }}">
                <input type="hidden" name="client_id" value="{{ $client->id }}">
                <input type="hidden" name="auth_token" value="{{ $authToken }}">
                <button type="submit" class="text-sm text-gray-600 hover:text-gray-900">
                    {{ __('Cancel') }}
                </button>
            </form>

            <form method="POST" action="{{ route('passport.authorizations.approve') }}">
                @csrf
                <input type="hidden" name="state" value="{{ $request->state }}">
                <input type="hidden" name="client_id" value="{{ $client->id }}">
                <input type="hidden" name="auth_token" value="{{ $authToken }}">
                <x-button>{{ __('Authorize') }}</x-button>
            </form>
        </div>
    </x-authentication-card>
</x-guest-layout>
