@extends('layouts.app')

@section('title', 'Admin Dashboard')

@section('content')
<div class="flex min-h-screen">
    <!-- ✅ SIDEBAR -->
    <aside class="w-64 bg-[#183057] text-white p-6">
        <h2 class="text-xl font-bold mb-6">Admin Panel</h2>
        
        <!-- Dashboard Link -->
        <a href="{{ route('admin') }}" class="block px-4 py-2 rounded hover:bg-blue-800 transition">Dashboard</a>
        
        <!-- ✅ ADD THIS BELOW -->
        <a href="{{ route('admin.journalist') }}" class="block px-4 py-2 mt-2 rounded hover:bg-blue-800 transition">Journalist</a>
    </aside>

    <!-- ✅ MAIN CONTENT -->
    <main class="flex-1 p-8">
        <h1 class="text-3xl font-bold mb-4">Welcome, Admin</h1>
        <!-- More content -->
    </main>
</div>
@endsection