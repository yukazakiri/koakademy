<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assessment Form</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,typography,aspect-ratio"></script>
    <style>
        /* Ensure Tailwind styles are properly applied */
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');

        * {
            font-family: 'Inter', DejaVu Sans, Arial, sans-serif;
        }

        /* Force background colors to print */
        * {
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
    </style>
    <style>
        @page {
            size: landscape;
            margin: 5mm;
        }
        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            line-height: 1.1;
            margin: 0;
            padding: 0;
            color: #333;
            font-size: 8pt;
        }
    </style>
</head>
<body class="bg-white mt-0">
    <div class="max-w-full mx-auto">
        <!-- Header Section -->
        <div class="mb-1 flex items-center justify-center gap-2">
            @if(isset($general_settings) && $general_settings?->school_portal_logo)
                <img src="{{ $general_settings->school_portal_logo }}" alt="College Logo" class="w-12">
            @else
                <img src="{{ asset('logo.png') }}" alt="College Logo" class="w-12">
            @endif
            <div class="text-center flex-1">
                <h1 class="font-bold text-sm">
                    {{ $general_settings?->school_portal_title ?? $general_settings?->site_name ?? app(\App\Settings\SiteSettings::class)->getOrganizationName() }}
                </h1>
                <p class="text-[7pt]">
                    @if(isset($general_settings) && ($general_settings?->support_phone || $general_settings?->support_email))
                        @if($general_settings?->support_phone)Tel No. {{ $general_settings->support_phone }}@endif
                        @if($general_settings?->support_phone && $general_settings?->support_email) | @endif
                        @if($general_settings?->support_email)Email: {{ $general_settings->support_email }}@endif
                    @else
                        {{ app(\App\Settings\SiteSettings::class)->getOrganizationAddress() ?? '118 Bonifacio Street, Holyghost Proper, Baguio City' }} | Tel No. {{ app(\App\Settings\SiteSettings::class)->getSupportPhone() ?? '444-5389/442-4160' }}
                    @endif
                </p>
                <p class="text-xs font-bold">Assessment Form</p>
            </div>
        </div>

        <div class="flex gap-4">
            <!-- Left Column -->
            <div class="w-2/3">
                <!-- Student Info -->
                <div class="bg-gray-50 p-1 rounded text-[7pt]">
                    <p class="mb-0.5"><strong>Course:</strong> {{ $student->getCourse->code ?? $student->course?->code ?? 'N/A' }}</p>
                    <p class="mb-0.5"><strong>Full Name:</strong> {{ $student->student_name }} : <strong>{{ $student->student->id }}</strong></p>
                    <p class="mb-0.5"><strong>Semester/School Year:</strong> {{ $semester }} {{ $school_year }}</p>
                    <p class="mb-0.5"><strong>Date:</strong> {{ now()->format('m-d-Y') }}</p>
                </div>

                <!-- Subjects Table -->
                <div class="overflow-x-auto mb-2">
                    <table class="w-full text-[7pt]">
                        <thead>
                            <tr class="bg-blue-500 text-white">
                                <th class="border px-1 py-0.5 text-left">Code</th>
                                <th class="border px-1 py-0.5 text-left">Title</th>
                                <th class="border px-1 py-0.5 text-center">Type</th>
                                <th class="border px-1 py-0.5 text-left">Units</th>
                                <th class="border px-1 py-0.5 text-left">Lec Fee</th>
                                <th class="border px-1 py-0.5 text-left">Lab Fee</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                $totalUnits = 0;
                                $total_lecture = 0;
                                $total_laboratory = 0;
                                $totalModularSubjects = 0;
                                $totalUnits2 = $subjects->sum('subject.units');

                                // Get lec_per_unit from the course for total units calculation
                                $courseId = $student->course_id ?? ($student->getCourse->id ?? null);
                                $course = null;
                                if ($courseId) {
                                    $course = \App\Models\Course::find($courseId);
                                }
                                $lecPerUnit = $course?->lec_per_unit ?? 0;
                            @endphp
                            @foreach ($subjects as $subject)
                                @php
                                    $isModular = $subject->is_modular ?? false;
                                    $isNSTP = str_contains(strtoupper($subject->subject->code ?? ''), 'NSTP');
                                    $hasLab = ($subject->subject->laboratory ?? 0) !== 0;

                                    // Calculate lecture fee WITHOUT modular addition (modular is separate)
                                    // Lecture fee = (lecture units + lab units) × lec_per_unit
                                    $totalSubjectUnits = ($subject->subject->lecture ?? 0) + ($subject->subject->laboratory ?? 0);
                                    $lectureFee = $totalSubjectUnits * ($subject->subject->course->lec_per_unit ?? 0);

                                    // Apply NSTP discount
                                    if ($isNSTP) {
                                        $lectureFee *= 0.5;
                                    }

                                    // Lab fee is 1 × lab_per_unit if subject has laboratory
                                    $laboratoryFee = $hasLab ? (1 * ($subject->subject->course->lab_per_unit ?? 0)) : 0;

                                    // For modular subjects, divide lab fee by 2
                                    if ($isModular && $hasLab) {
                                        $laboratoryFee = $laboratoryFee / 2;
                                    }

                                    // Count modular subjects
                                    if ($isModular) {
                                        $totalModularSubjects++;
                                    }

                                    // Accumulate totals
                                    $total_lecture += $lectureFee;
                                    $total_laboratory += $laboratoryFee;
                                @endphp
                                <tr class="hover:bg-gray-50">
                                    <td class="border px-1 py-0.5">{{ $subject->subject->code ?? 'N/A' }}</td>
                                    <td class="border px-1 py-0.5">{{ $subject->subject->title ?? 'Unknown Subject' }}</td>
                                    <td class="border px-1 py-0.5 text-center">
                                        @if($isModular)
                                            <span class="bg-purple-100 text-purple-700 px-1 rounded text-[6pt]">Modular</span>
                                        @else
                                            <span class="text-gray-500 text-[6pt]">Regular</span>
                                        @endif
                                    </td>
                                    <td class="border px-1 py-0.5">{{ $subject->subject->units }}</td>
                                    <td class="border px-1 py-0.5">{{ number_format($lectureFee, 2) }}</td>
                                    <td class="border px-1 py-0.5">{{ number_format($laboratoryFee, 2) }}</td>
                                </tr>
                            @endforeach
                            <tr class="font-bold bg-gray-100">
                                <td colspan="3" class="border px-1 py-0.5">Total</td>
                                <td class="border px-1 py-0.5">{{ $totalUnits2 }}</td>
                                <td class="border px-1 py-0.5">{{ number_format($total_lecture, 2) }}</td>
                                <td class="border px-1 py-0.5">{{ number_format($total_laboratory, 2) }}</td>
                            </tr>
                            @if($totalModularSubjects > 0)
                            <tr class="bg-purple-50">
                                <td colspan="6" class="border px-1 py-0.5 text-purple-700 text-[6pt]">
                                    * {{ $totalModularSubjects }} Modular Subject(s) @ ₱2,400.00 each = ₱{{ number_format($totalModularSubjects * 2400, 2) }}
                                </td>
                            </tr>
                            @endif
                        </tbody>
                    </table>
                </div>

                <!-- Schedule Table -->
                <div class="overflow-x-auto">
                    <table class="w-full text-[6pt]">
                        <thead>
                            <tr class="bg-blue-500 text-white">
                                <th class="border px-1 py-0.5">Title</th>
                                <th class="border px-1 py-0.5">Mon</th>
                                <th class="border px-1 py-0.5">Tue</th>
                                <th class="border px-1 py-0.5">Wed</th>
                                <th class="border px-1 py-0.5">Thu</th>
                                <th class="border px-1 py-0.5">Fri</th>
                                <th class="border px-1 py-0.5">Sat</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($subjects as $subject)
                                @php
                                    // Use class_id directly instead of section field for more reliable lookup
                                    $classId = $subject->class_id;
                                    $subjectCode = $subject->subject->code;

                                    // First try: exact match by class_id (most reliable)
                                    $class = \App\Models\Classes::find($classId);

                                    // Fallback only if class ID lookup failed (e.g. old data without class_id)
                                    if (!$class) {
                                        $class = \App\Models\Classes::whereRaw('LOWER(TRIM(subject_code)) = LOWER(TRIM(?))', [$subjectCode])
                                            ->where('school_year', $school_year) // Use variable instead of hardcoded
                                            ->where('semester', $semester)       // Use variable instead of hardcoded
                                            ->first();
                                    }

                                    $daysOfWeek = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
                                    $scheduleByDay = array_fill_keys($daysOfWeek, '');

                                    if ($class) {
                                        $schedules = $class->Schedule;
                                        foreach ($schedules as $schedule) {
                                            $day = strtolower($schedule->day_of_week);
                                            $room = $schedule->room->name ?? '';
                                            $section = $class->section ?? '';
                                            if (in_array($day, $daysOfWeek)) {
                                                $scheduleByDay[$day] = $schedule->start_time->format('g:i') . '-' . $schedule->end_time->format('g:i') . ' ' . $section . ' (' . $room . ')';
                                            }
                                        }
                                    }
                                @endphp
                                <tr>
                                    <td class="border px-1 py-0.5">{{ $subject->subject->title }}</td>
                                    @foreach ($daysOfWeek as $day)
                                        <td class="border px-1 py-0.5 {{ $scheduleByDay[$day] ? 'bg-blue-100' : '' }}">
                                            {{ $scheduleByDay[$day] }}
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Right Column -->
            <div class="w-1/3 border-l border-gray-300 pl-1">
                <div class="bg-gray-50 p-1 rounded">
                    <h2 class="text-xs font-bold text-gray-800 border-b border-gray-300 pb-0.5 mb-1">Breakdown of Fees</h2>

                    <!-- Tuition Fee Section -->
                    <div class="bg-white p-1 rounded shadow-sm mb-1 text-[7pt]">
                        <p class="font-bold mb-1">Tuition Fee Details</p>
                        <p class="mb-0.5">Sub-Total (tuition fee): ₱{{ number_format($total_lecture, 2) }}</p>
                        <p class="mb-0.5">Discount: {{ $tuition->discount }}%</p>
                        <p class="border-t border-gray-200 pt-1 mt-1 font-bold">
                            Total Tuition Fee: ₱{{ number_format($tuition->total_lectures, 2) }}
                        </p>
                    </div>

                    <!-- Additional Fees Section -->
                    <div class="bg-white p-1 rounded shadow-sm mb-1 text-[7pt]">
                        <p class="font-bold mb-1">Additional Fees</p>
                        <p class="mb-0.5">Laboratory Fee: ₱{{ number_format($tuition->total_laboratory, 2) }}</p>
                        <p class="mb-0.5">Miscellaneous Fee: ₱{{ number_format($tuition->total_miscelaneous_fees, 2) }}</p>

                        @php
                            // Calculate total modular fee (2400 per modular subject)
                            $totalModularFee = $totalModularSubjects * 2400;
                        @endphp

                        @if($totalModularSubjects > 0)
                            <p class="mb-0.5 text-purple-700">Total Modular Fee ({{ $totalModularSubjects }} subjects): ₱{{ number_format($totalModularFee, 2) }}</p>
                        @endif

                        @if($student->additionalFees && $student->additionalFees->count() > 0)
                            @php
                                $additionalFeesTotal = 0;
                            @endphp
                            @foreach($student->additionalFees as $fee)
                                <p class="mb-0.5">{{ $fee->fee_name }}: ₱{{ number_format($fee->amount, 2) }}
                                    @if($fee->is_required)
                                        <span class="text-red-600 text-[6pt]">(Required)</span>
                                    @endif
                                </p>
                                @php
                                    $additionalFeesTotal += $fee->amount;
                                @endphp
                            @endforeach
                            @if($additionalFeesTotal > 0)
                                <p class="border-t border-gray-200 pt-1 mt-1 font-bold">
                                    Additional Fees Total: ₱{{ number_format($additionalFeesTotal, 2) }}
                                </p>
                            @endif
                        @endif
                    </div>

                    <!-- Payment Summary -->
                    <div class="bg-blue-50 p-1 rounded border border-blue-200 mb-1 text-[7pt]">
                        <p class="font-bold mb-1">Payment Summary</p>
                        @php
                            $additionalFeesTotal = $student->additionalFees ? $student->additionalFees->sum('amount') : 0;
                            // The tuition record already includes all fees correctly calculated
                            // Use overall_tuition if available, otherwise calculate from components
                            $totalAmount = $tuition->overall_tuition ?? ($tuition->total_lectures + $tuition->total_laboratory + $tuition->total_miscelaneous_fees + $additionalFeesTotal);
                        @endphp
                        <p class="mb-0.5">Tuition Fee: ₱{{ number_format($tuition->total_lectures, 2) }}</p>
                        <p class="mb-0.5">Laboratory Fee: ₱{{ number_format($tuition->total_laboratory, 2) }}</p>
                        <p class="mb-0.5">Miscellaneous Fee: ₱{{ number_format($tuition->total_miscelaneous_fees, 2) }}</p>
                        @if($totalModularSubjects > 0)
                            <p class="mb-0.5 text-purple-700 text-[6pt]">* Modular fees ({{ $totalModularSubjects }}) already included in tuition</p>
                        @endif
                        @if($additionalFeesTotal > 0)
                            <p class="mb-0.5">Additional Fees: ₱{{ number_format($additionalFeesTotal, 2) }}</p>
                        @endif
                        <p class="border-t border-gray-200 pt-1 mt-1 mb-0.5 font-bold">Total Amount: ₱{{ number_format($totalAmount, 2) }}</p>
                        <p class="mb-0.5">Downpayment: ₱{{ number_format($tuition->downpayment, 2) }}</p>
                        <p class="text-sm font-bold text-gray-800">
                            Balance: ₱{{ number_format($tuition->total_balance ?? ($totalAmount - $tuition->downpayment), 2) }}
                        </p>
                    </div>

                    <!-- Signatures Section -->
                    <div class="space-y-6 mt-7">
                        <div>
                            <div class="border-b border-black w-48"></div>
                            <p class="text-[8pt] mt-0.5">Assessed By</p>
                        </div>

                        <div>
                            <div class="border-b border-black w-48"></div>
                            <p class="text-[8pt] mt-0.5">Student Signature</p>
                        </div>

                        <div>
                            <div class="border-b border-black w-48"></div>
                            <p class="text-[8pt] mt-0.5">Registrar</p>
                        </div>

                        <div>
                            <div class="border-b border-black w-48"></div>
                            <p class="text-[8pt] mt-0.5">Cashier</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
