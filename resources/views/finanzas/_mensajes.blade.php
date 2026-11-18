@if(session('success'))
    <div class="mb-4 p-3 rounded-lg bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-300 text-sm">{{ session('success') }}</div>
@endif
@if($errors->any())
    <div class="mb-4 p-3 rounded-lg bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-300 text-sm">{{ $errors->first() }}</div>
@endif
