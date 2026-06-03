@extends('layouts.app')
@section('content')
    <div class="mb-6">
        <h2 class="text-2xl font-bold">Teacher Timetable</h2>
        <p class="text-gray-600 mt-1">Academic year, department, year level, semester, course, instructor, classroom, and time.</p>
    </div>
    @if($schedules->isEmpty())
        <p>No schedules found.</p>
    @else
        @php
            $grouped = $schedules->groupBy(fn ($item) => $item->timeslot?->day_of_week ?? 'Unscheduled');
            $dayOrder = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday', 'Unscheduled'];
        @endphp
        @foreach($dayOrder as $day)
            @if(!isset($grouped[$day]))
                @continue
            @endif
            <h3 class="text-lg font-semibold mb-2">{{ $day }}</h3>
            @include('partials.schedule-table', ['items' => $grouped[$day]])
        @endforeach
    @endif
@endsection
