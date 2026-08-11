<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Course;
use App\Services\CourseScheduleExportService;
use Spatie\LaravelPdf\Enums\Format;
use Spatie\LaravelPdf\PdfBuilder;

use function Spatie\LaravelPdf\Support\pdf;

final class AdministratorCourseSchedulePdfController extends Controller
{
    public function __construct(private readonly CourseScheduleExportService $exports) {}

    public function __invoke(Course $course): PdfBuilder
    {
        $data = $this->exports->build($course);

        abort_if($data['year_groups'] === [], 404, 'No schedules were found for this program in the current academic period.');

        return pdf()
            ->view('pdf.course-schedule-sheet', $data)
            ->format(Format::A4)
            ->portrait()
            ->margins(top: 8, right: 8, bottom: 10, left: 8, unit: 'mm')
            ->inline($this->exports->filename($course));
    }
}
