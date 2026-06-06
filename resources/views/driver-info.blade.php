<!DOCTYPE html>
<html lang="lt">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }} — Vairuotojo duomenys</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="antialiased bg-gray-50">
    <div class="min-h-screen flex items-center justify-center p-6">
        <div class="w-full max-w-md rounded-xl border border-gray-200 bg-white p-8 shadow-sm">
            <h1 class="text-xl font-semibold text-gray-900">Vairuotojo duomenys</h1>

            @if (session('status'))
                <p class="mt-4 rounded-md bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('status') }}</p>
            @endif

            <form method="POST" action="{{ route('driver-info.store') }}" class="mt-6 space-y-5">
                @csrf

                <div>
                    <label for="birth_date" class="block text-sm font-medium text-gray-700">Gimimo data</label>
                    <input
                        type="date"
                        name="birth_date"
                        id="birth_date"
                        value="{{ old('birth_date') }}"
                        required
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-amber-500 focus:ring-amber-500"
                    >
                    @error('birth_date')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="driver_license" class="block text-sm font-medium text-gray-700">Vairuotojo pažymėjimo Nr.*</label>
                    <input
                        type="text"
                        name="driver_license"
                        id="driver_license"
                        value="{{ old('driver_license') }}"
                        inputmode="numeric"
                        pattern="\d+"
                        required
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-amber-500 focus:ring-amber-500"
                    >
                    @error('driver_license')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="flex items-start gap-3 text-sm text-gray-700">
                        <input
                            type="checkbox"
                            name="privacy_policy_accepted"
                            value="1"
                            @checked(old('privacy_policy_accepted'))
                            required
                            class="mt-0.5 rounded border-gray-300 text-amber-600 focus:ring-amber-500"
                        >
                        <span>
                            Sutinku su
                            <a
                                href="https://www.sitandgo.lt/privatumo-politika/"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="text-amber-700 underline hover:text-amber-800"
                            >Privatumo politika</a>
                        </span>
                    </label>
                    @error('privacy_policy_accepted')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <button
                    type="submit"
                    class="w-full rounded-md bg-amber-600 px-4 py-2 text-sm font-medium text-white hover:bg-amber-700"
                >
                    Pateikti
                </button>
            </form>
        </div>
    </div>
</body>
</html>
