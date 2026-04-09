<!-- Start of Selection -->
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificate of Enrollment</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            text-align: center;
            margin: 0;
            padding: 0;
        }

        .container {
            width: 80%;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            text-align: center;
            margin-bottom: 20px;
        }

        .header img {
            width: 100px;
            height: auto;
        }

        .header h1 {
            font-size: 24px;
            margin: 0;
        }

        .header p {
            margin: 5px 0;
        }

        .title {
            font-size: 20px;
            font-weight: bold;
            margin: 20px 0;
        }

        .content {
            text-align: left;
            margin-bottom: 20px;
        }

        .content p {
            margin: 5px 0;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .table th,
        .table td {
            border: 1px solid #000;
            padding: 8px;
            text-align: left;
        }

        .table th {
            background-color: #f2f2f2;
        }

        .footer {
            text-align: right;
            margin-top: 20px;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <img src="{{ $logo }}" alt="College Logo">
            <h1>{{ app(\App\Settings\SiteSettings::class)->getOrganizationName() }}</h1>
            <p>{{ app(\App\Settings\SiteSettings::class)->getOrganizationAddress() ?? '118 Bonifacio Street, Holyghost Proper, Baguio City' }}</p>
            <p>Tel No. {{ app(\App\Settings\SiteSettings::class)->getSupportPhone() ?? '444-5389/442-4160' }}</p>
        </div>
        <div class="title">
            CERTIFICATION OF ENROLMENT
        </div>
        <div class="content">
            <p>This is to certify that according to our records filed here at <strong>{{ app(\App\Settings\SiteSettings::class)->getOrganizationName() }}, {{ $student->guest_personal_info->full_name ?? $student->student->full_name }} </strong> is currently enrolled in the
                <strong>{{ $student->getCourse->code ?? $student->course->code }}</strong> course, {{ $semester }} Semester School Year
                {{ $school_year }}.</p>
        </div>
        <table class="table">
            <thead>
                <tr>
                    <th>CODE</th>
                    <th>DESCRIPTIVE TITLE</th>
                    <th>UNITS</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($subjects as $subject)
                    <tr>
                        <td>{{ $subject->subject->code }}</td>
                        <td>{{ $subject->subject->title }}</td>
                        <td>{{ $subject->subject->units }}</td>
                    </tr>
                @endforeach
                <tr>
                    <td colspan="2" style="text-align: right;"><strong>Total</strong></td>
                    <td><strong>{{ $subjects->sum('subject.units') }}</strong></td>
                </tr>
            </tbody>
        </table>
        <div class="content">
            <p>This certification is issued upon the request of the aforementioned student for NCIP purposes only.</p>
            <p>Issued this {{ \Carbon\Carbon::now()->format('jS') }} day of {{ \Carbon\Carbon::now()->format('F, Y') }}
                at Baguio City Philippines</p>
        </div>
        <div class="footer">
            <p>Jocelyn M. Pilacun</p>
            <p>Registrar</p>
        </div>
    </div>
</body>

</html>
<!-- End of Selection -->
