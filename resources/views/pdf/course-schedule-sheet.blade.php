<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Course Schedule - {{ $course["code"] }}</title>
    <style>
        @page {
            size: A4 portrait;
        }

        * {
            box-sizing: border-box;
        }

        html {
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        body {
            margin: 0;
            color: #111111;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 7.5pt;
            line-height: 1.08;
        }

        .document-header {
            margin: 0 0 11px;
            text-align: center;
            text-transform: uppercase;
        }

        .institution-name,
        .program-title,
        .academic-period,
        .semester {
            margin: 0;
            font-weight: 700;
        }

        .institution-name {
            font-size: 10pt;
        }

        .program-title {
            margin-top: 2px;
            font-size: 9.5pt;
        }

        .academic-period {
            margin-top: 2px;
            font-size: 9pt;
        }

        .semester {
            margin-top: 1px;
            font-size: 7.5pt;
        }

        .year-group {
            margin-bottom: 10px;
        }

        .section-group + .section-group {
            margin-top: 9px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        thead {
            display: table-header-group;
        }

        tr {
            break-inside: avoid;
            page-break-inside: avoid;
        }

        th,
        td {
            border: 0.65px solid #242424;
            padding: 2px 3px;
            vertical-align: middle;
        }

        .year-band th,
        .section-band th {
            background: #f2f2f0;
            font-weight: 700;
            letter-spacing: 0.15px;
            text-align: center;
            text-transform: uppercase;
        }

        .year-band th {
            padding: 3px;
            font-size: 8.4pt;
        }

        .section-band th {
            padding: 2.5px;
            font-size: 8pt;
        }

        .column-headings th {
            background: #fafafa;
            font-size: 7pt;
            font-weight: 700;
            text-align: center;
            text-transform: uppercase;
        }

        tbody td {
            font-size: 7.2pt;
        }

        .code-column {
            width: 14%;
        }

        .title-column {
            width: 34%;
        }

        .units-column {
            width: 7%;
        }

        .schedule-column {
            width: 20%;
        }

        .room-column {
            width: 9%;
        }

        .face-to-face-column {
            width: 16%;
        }

        .center {
            text-align: center;
        }

        .units,
        .schedule {
            font-variant-numeric: tabular-nums;
        }

        .schedule {
            white-space: nowrap;
        }
    </style>
</head>

<body>
    <header class="document-header">
        <h1 class="institution-name">{{ $school["name"] }}</h1>
        <h2 class="program-title">{{ $course["title"] }}</h2>
        <p class="academic-period">Academic Year {{ str_replace(" ", "", $school_year) }}</p>
        <p class="semester">{{ $semester_label }}</p>
    </header>

    @foreach ($year_groups as $group)
        <section class="year-group">
            @foreach ($group["section_groups"] as $section)
                <div class="section-group">
                    <table>
                        <thead>
                            @if ($loop->first)
                                <tr class="year-band">
                                    <th colspan="6">
                                        {{ $group["label"] }}
                                        @if ($loop->parent->first)
                                            {{ str_replace(" ", "", $school_year) }}
                                        @endif
                                    </th>
                                </tr>
                            @endif
                            @if ($group["show_section_labels"])
                                <tr class="section-band">
                                    <th colspan="6">{{ $section["label"] }}</th>
                                </tr>
                            @endif
                            <tr class="column-headings">
                                <th class="code-column">Course Code</th>
                                <th class="title-column">Descriptive Title</th>
                                <th class="units-column">Units</th>
                                <th class="schedule-column">Schedule</th>
                                <th class="room-column">Room</th>
                                <th class="face-to-face-column">Face to Face Classes</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($section["rows"] as $row)
                                <tr>
                                    <td>{{ $row["code"] }}</td>
                                    <td>{{ $row["title"] }}</td>
                                    <td class="center units">{{ $row["units"] ?? "—" }}</td>
                                    <td class="center schedule">{{ $row["schedule"] }}</td>
                                    <td class="center">{{ $row["room"] }}</td>
                                    <td class="center">{{ $row["face_to_face"] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endforeach
        </section>
    @endforeach
</body>

</html>
