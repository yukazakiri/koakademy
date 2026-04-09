@foreach ($groupedSubjects as $academicYear => $semesters)
    <x-filament::section
        :heading="'Academic Year ' . $academicYear"
        :collapsible="true"
        :collapsed="true"
    >
        @foreach ($semesters as $semester => $subjects)
            <h4 class="text-md font-semibold leading-6 text-gray-950 dark:text-white mb-4">Semester {{ $semester }}</h4>
            <table class="w-full text-sm text-left rtl:text-right text-gray-500 dark:text-gray-400">
                <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                    <tr>
                        <th scope="col" class="px-4 py-2">Code</th>
                        <th scope="col" class="px-4 py-2">Title</th>
                        <th scope="col" class="px-4 py-2 text-right">Units</th>
                        <th scope="col" class="px-4 py-2">Status</th>
                        <th scope="col" class="px-4 py-2">Grade</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($subjects as $subject)
                        @php
                            $enrolledSubject = $subjectEnrolled->get($subject->id);
                            $status = 'Not Completed';
                            $statusColor = 'danger';
                            $grade = '-';
                            $gradeColor = 'gray';

                            if ($enrolledSubject) {
                                if ($enrolledSubject->grade) {
                                    $status = 'Completed';
                                    $statusColor = 'success';
                                    $grade = number_format($enrolledSubject->grade, 2);
                                    $gradeColor = \App\Enums\GradeEnum::fromGrade($enrolledSubject->grade)->getColor();
                                } else {
                                    $status = 'In Progress';
                                    $statusColor = 'warning';
                                }
                            }
                        @endphp
                        <tr class="bg-white border-b dark:bg-gray-800 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                            <td class="px-4 py-2">{{ $subject->code }}</td>
                            <td class="px-4 py-2">{{ $subject->title }}</td>
                            <td class="px-4 py-2 text-right">{{ $subject->units }}</td>
                            <td class="px-4 py-2">
                                <x-filament::badge :color="$statusColor">
                                    {{ $status }}
                                </x-filament::badge>
                            </td>
                            <td class="px-4 py-2">
                                <x-filament::badge :color="$gradeColor">
                                    {{ $grade }}
                                </x-filament::badge>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @if (!$loop->last)
                <br>
            @endif
        @endforeach
    </x-filament::section>
@endforeach
