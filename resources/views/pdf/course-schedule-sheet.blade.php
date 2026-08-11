<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Course Schedule - {{ $course["code"] }}</title>
    <style>
        @page {
            size: A4 portrait;
        }

        html {
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            color: #111111;
            font-family: "Times New Roman", Times, serif;
            font-size: 8.5pt;
            line-height: 1.15;
        }

        .institution-header {
            position: relative;
            min-height: 54px;
            margin-bottom: 8px;
            padding: 0 62px;
            text-align: center;
        }

        .institution-logo {
            position: absolute;
            top: 0;
            left: 6px;
            width: 52px;
            max-height: 52px;
            object-fit: contain;
        }

        .institution-name {
            margin: 0 0 2px;
            font-size: 11pt;
            font-weight: 700;
        }

        .institution-meta {
            margin: 1px 0;
            font-size: 7.7pt;
            font-style: italic;
        }

        .document-heading {
            margin-bottom: 8px;
            text-align: center;
        }

        .program-title {
            margin: 0;
            font-size: 10.5pt;
            font-weight: 700;
            text-decoration: underline;
            text-transform: uppercase;
        }

        .academic-period {
            margin: 2px 0 0;
            font-size: 9pt;
            font-weight: 700;
            text-transform: uppercase;
        }

        .year-group {
            margin-bottom: 8px;
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
            border: 0.7px solid #111111;
            padding: 2.5px 4px;
            vertical-align: middle;
        }

        .year-band th {
            background: #e97825;
            color: #111111;
            font-size: 8pt;
            font-weight: 700;
            letter-spacing: 0.2px;
            text-align: left;
            text-transform: uppercase;
        }

        .column-headings th {
            background: #f5a15e;
            font-size: 7.4pt;
            font-weight: 700;
            text-align: center;
            text-transform: uppercase;
        }

        tbody td {
            font-size: 7.7pt;
        }

        .code-column {
            width: 13%;
        }

        .title-column {
            width: 31%;
        }

        .section-column {
            width: 9%;
        }

        .units-column {
            width: 7%;
        }

        .day-column {
            width: 9%;
        }

        .time-column {
            width: 18%;
        }

        .room-column {
            width: 13%;
        }

        .center {
            text-align: center;
        }

        .time,
        .units {
            font-variant-numeric: tabular-nums;
            white-space: nowrap;
        }
    </style>
</head>

<body>
    <header class="institution-header">
        @if (!empty($school["logo"]))
            <img src="{{ $school["logo"] }}" alt="{{ $school["name"] }} logo" class="institution-logo">
        @endif

        <h1 class="institution-name">{{ $school["name"] }}</h1>

        @if (!empty($school["address"]))
            <p class="institution-meta">{{ $school["address"] }}</p>
        @endif

        @if (!empty($school["phone"]) || !empty($school["email"]))
            <p class="institution-meta">
                @if (!empty($school["phone"]))
                    Tel: {{ $school["phone"] }}
                @endif
                @if (!empty($school["phone"]) && !empty($school["email"]))
                    &nbsp;&middot;&nbsp;
                @endif
                @if (!empty($school["email"]))
                    {{ $school["email"] }}
                @endif
            </p>
        @endif
    </header>

    <section class="document-heading">
        <h2 class="program-title">{{ $course["title"] }}</h2>
        <p class="academic-period">{{ $semester_label }} &middot; AY {{ $school_year }}</p>
    </section>

    @foreach ($year_groups as $group)
        <section class="year-group">
            <table>
                <thead>
                    <tr class="year-band">
                        <th colspan="7">{{ $group["label"] }}</th>
                    </tr>
                    <tr class="column-headings">
                        <th class="code-column">Course Code</th>
                        <th class="title-column">Descriptive Title</th>
                        <th class="section-column">Section</th>
                        <th class="units-column">Units</th>
                        <th class="day-column">Day</th>
                        <th class="time-column">Time</th>
                        <th class="room-column">Room</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($group["rows"] as $row)
                        <tr>
                            <td>{{ $row["code"] }}</td>
                            <td>{{ $row["title"] }}</td>
                            <td class="center">{{ $row["section"] }}</td>
                            <td class="center units">{{ $row["units"] ?? "—" }}</td>
                            <td class="center">{{ $row["day"] }}</td>
                            <td class="center time">{{ $row["time"] }}</td>
                            <td class="center">{{ $row["room"] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>
    @endforeach
</body>

</html>
