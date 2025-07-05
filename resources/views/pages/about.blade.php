@extends('layouts.app')

@section('content')
<section class="flex items-start justify-between min-h-screen px-16 pt-16 font-roboto text-white bg-cover bg-center"
    style="background-image: url('/path/to/your/background.png');">
    
    <div class="max-w-2xl ml-8"> {{-- Adjusted content slightly to the right --}}

        <!-- Vision -->
        <div class="mb-10">
            <h2 class="text-3xl font-bold relative inline-block mb-3">
                <span class="relative z-10">VISION</span>
                <span class="absolute bottom-3.5 left-0 w-[190%] h-10 bg-cyan-900 -ml-2"></span>
            </h2>
            <p class="text-xl leading-relaxed">
                A Premier National University that develops leaders in the global knowledge economy
            </p>
        </div>

        <!-- Mission -->
        <div class="mb-10">
            <h2 class="text-3xl font-bold relative inline-block mb-3">
                <span class="relative z-10">MISSION</span>
                <span class="absolute bottom-3.5 left-0 w-[150%] h-10 bg-cyan-900 -ml-2"></span>
            </h2>
            <p class="text-xl leading-relaxed">
                A University committed to producing leaders by providing a 21st century learning environment through
                innovations in education, multidisciplinary research, and community and industry partnerships in order
                to nurture the spirit of nationhood, propel the national economy, and engage the world for sustainable
                development.
            </p>
        </div>

        <!-- Core Values -->
        <div>
            <h2 class="text-3xl font-bold relative inline-block mb-4">
                <span class="relative z-10">CORE VALUES</span>
                <span class="absolute bottom-3.5 left-0 w-[100%] h-10 bg-cyan-900 -ml-2"></span>
            </h2>
            <div class="grid grid-cols-2 gap-4 text-lg leading-relaxed pl-4">
                <ul class="list-disc list-inside space-y-1">
                    <li>Patriotism</li>
                    <li>Service</li>
                    <li>Integrity</li>
                </ul>
                <ul class="list-disc list-inside space-y-1">
                    <li>Resilience</li>
                    <li>Excellence</li>
                    <li>Faith</li>
                </ul>
            </div>
        </div>
        
    </div>

</section>
@endsection
