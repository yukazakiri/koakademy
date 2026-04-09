<table class="schedule-table">
    <thead>
        <tr>
            <th style="width: 12%;">Day</th>
            <th style="width: 15%;">Time</th>
            <th style="width: 25%;">Subject</th>
            <th style="width: 8%;">Section</th>
            <th style="width: 6%;">Units</th>
            @if($selectedView !== 'faculty')
                <th style="width: 15%;">Faculty</th>
            @endif
            @if($selectedView !== 'room')
                <th style="width: 10%;">Room</th>
            @endif
            @if($selectedView === 'course')
                <th style="width: 9%;">Year Level</th>
            @endif
        </tr>
    </thead>
    <tbody>
        @foreach($groupedSchedules as $day => $daySchedules)
            @foreach($daySchedules->sortBy('start_time') as $schedule)
                @php
                    $subjectCode = $schedule['class']['subject']['code'] ?? 'N/A';
                    $colorClass = $subjectColors[$subjectCode]['class'] ?? 'bg-gray';
                @endphp
                <tr class="schedule-row">
                    <td class="day-cell">{{ $day }}</td>
                    <td class="time-cell">
                        {{ $schedule['start_time_formatted'] }} - {{ $schedule['end_time_formatted'] }}
                        <br><small>({{ $schedule['duration_minutes'] }} min)</small>
                    </td>
                    <td class="subject-cell {{ $colorClass }}">
                        <strong>{{ $schedule['class']['subject']['title'] ?? 'N/A' }}</strong>
                        <br><small>{{ $subjectCode }}</small>
                    </td>
                    <td class="section-cell">{{ $schedule['class']['section'] ?? 'N/A' }}</td>
                    <td class="units-cell">{{ $schedule['class']['subject']['units'] ?? '-' }}</td>
                    @if($selectedView !== 'faculty')
                        <td class="faculty-cell">{{ $schedule['class']['faculty']['full_name'] ?? 'N/A' }}</td>
                    @endif
                    @if($selectedView !== 'room')
                        <td class="room-cell">{{ $schedule['room']['name'] ?? 'N/A' }}</td>
                    @endif
                    @if($selectedView === 'course')
                        <td class="section-cell">
                            @php
                                $yearLevel = $schedule['class']['academic_year'];
                                echo match($yearLevel) {
                                    '1' => '1st Year',
                                    '2' => '2nd Year', 
                                    '3' => '3rd Year',
                                    '4' => '4th Year',
                                    default => $yearLevel ?? 'N/A'
                                };
                            @endphp
                        </td>
                    @endif
                </tr>
            @endforeach
        @endforeach
    </tbody>
</table>
