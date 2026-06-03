@php
    use App\Support\ScheduleDisplay;
@endphp
<table class="min-w-full border-collapse border border-gray-300 text-sm" style="margin-bottom: 2em;">
    <thead class="bg-gray-100">
        <tr>
            <th class="border border-gray-300 px-3 py-2 text-left">Academic Year</th>
            <th class="border border-gray-300 px-3 py-2 text-left">Department</th>
            <th class="border border-gray-300 px-3 py-2 text-left">Year Level</th>
            <th class="border border-gray-300 px-3 py-2 text-left">Semester</th>
            <th class="border border-gray-300 px-3 py-2 text-left">Course</th>
            <th class="border border-gray-300 px-3 py-2 text-left">Instructor</th>
            <th class="border border-gray-300 px-3 py-2 text-left">Classroom</th>
            <th class="border border-gray-300 px-3 py-2 text-left">Day</th>
            <th class="border border-gray-300 px-3 py-2 text-left">Time</th>
        </tr>
    </thead>
    <tbody>
        @foreach($items->sortBy(fn ($s) => $s->timeslot?->start_time) as $schedule)
            @php $d = ScheduleDisplay::for($schedule); @endphp
            <tr>
                <td class="border border-gray-300 px-3 py-2">{{ $d['academic_year'] ?? '—' }}</td>
                <td class="border border-gray-300 px-3 py-2">{{ $d['department'] ?? '—' }}</td>
                <td class="border border-gray-300 px-3 py-2">{{ $d['year_level'] ?? '—' }}</td>
                <td class="border border-gray-300 px-3 py-2">{{ $d['semester'] ?? '—' }}</td>
                <td class="border border-gray-300 px-3 py-2">{{ ($d['course_code'] ?? '') . ' — ' . ($d['course_name'] ?? '') }}</td>
                <td class="border border-gray-300 px-3 py-2">{{ $d['instructor'] ?? '—' }}</td>
                <td class="border border-gray-300 px-3 py-2">{{ $d['classroom'] ?? '—' }}</td>
                <td class="border border-gray-300 px-3 py-2">{{ $d['day'] ?? '—' }}</td>
                <td class="border border-gray-300 px-3 py-2">{{ $d['time'] ?? '—' }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
