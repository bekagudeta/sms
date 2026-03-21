@extends('layouts.app')
@section('content')
    <h2>Teacher Timetable</h2>
    @if($schedules->isEmpty())
        <p>No schedules found.</p>
    @else
        @php
            $grouped = $schedules->groupBy(function($item) {
                return $item->timeslot->day;
            });
        @endphp
        @foreach($grouped as $day => $items)
            <h3>{{ $day }}</h3>
            <table border="1" style="margin-bottom: 2em;">
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Course</th>
                        <th>Room</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($items->sortBy(fn($s) => $s->timeslot->start_time) as $schedule)
                    <tr>
                        <td>{{ $schedule->timeslot->start_time }} - {{ $schedule->timeslot->end_time }}</td>
                        <td>{{ $schedule->course->course_name }}</td>
                        <td>{{ $schedule->room->room_code }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endforeach
    @endif
@endsection
