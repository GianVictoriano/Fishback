@extends('layouts.app')

@section('title', 'Admin Dashboard')

@section('content')
<!-- FIRST NAME -->
<div class="mb-4">
    <label class="block font-semibold mb-1">First Name</label>
    <input type="text" name="fname" required class="w-full border px-4 py-2 rounded">
</div>

<!-- LAST NAME -->
<div class="mb-4">
    <label class="block font-semibold mb-1">Last Name</label>
    <input type="text" name="lname" required class="w-full border px-4 py-2 rounded">
</div>
@endsection