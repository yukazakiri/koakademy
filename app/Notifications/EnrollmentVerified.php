<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;

final class EnrollmentVerified extends Notification
{
    use Queueable;

    // public $student_name;

    /**
     * Create a new notification instance.
     */
    public function __construct(public $record)
    {
        // $this->student_name = $student_name;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $subjects = $this->record->SubjectsEnrolled;
        $subjectTable = '<table style="width:100%; border-collapse: collapse; margin-bottom: 20px;">';
        $subjectTable .= '<tr style="background-color: #f2f2f2;"><th style="border: 1px solid #ddd; padding: 12px;">Subject</th><th style="border: 1px solid #ddd; padding: 12px;">Title</th><th style="border: 1px solid #ddd; padding: 12px;">Units</th><th style="border: 1px solid #ddd; padding: 12px;">Teacher</th></tr>';

        foreach ($subjects as $subject) {
            $subjectDetails = $subject->subject;
            $subjectTable .= '<tr>';
            $subjectTable .= '<td style="border: 1px solid #ddd; padding: 12px;">'.$subjectDetails->code.'</td>';
            $subjectTable .= '<td style="border: 1px solid #ddd; padding: 12px;">'.$subjectDetails->title.'</td>';
            $subjectTable .= '<td style="border: 1px solid #ddd; padding: 12px;">'.$subjectDetails->units.'</td>';
            $subjectTable .= '<td style="border: 1px solid #ddd; padding: 12px;">'.$subject->instructor.'</td>';
            $subjectTable .= '</tr>';
        }

        $subjectTable .= '</table>';

        $invoice = $this->record->guest_tuition;
        $invoiceTable = '<table style="width:100%; border-collapse: collapse; margin-bottom: 20px;">';
        $invoiceTable .= '<tr style="background-color: #f2f2f2;"><th style="border: 1px solid #ddd; padding: 12px;">Fee Type</th><th style="border: 1px solid #ddd; padding: 12px;">Amount</th></tr>';
        $invoiceTable .= '<tr><td style="border: 1px solid #ddd; padding: 12px;">Tuition Fee</td><td style="border: 1px solid #ddd; padding: 12px;">₱ '.number_format($invoice->totaltuition, 2).'</td></tr>';
        $invoiceTable .= '<tr><td style="border: 1px solid #ddd; padding: 12px;">Miscellaneous Fee</td><td style="border: 1px solid #ddd; padding: 12px;">₱ '.number_format($invoice->miscellaneous, 2).'</td></tr>';
        $invoiceTable .= '<tr><td style="border: 1px solid #ddd; padding: 12px;">Other Fees</td><td style="border: 1px solid #ddd; padding: 12px;">₱ '.number_format($invoice->other_fees, 2).'</td></tr>';
        $invoiceTable .= '<tr style="font-weight: bold;"><td style="border: 1px solid #ddd; padding: 12px;">Total</td><td style="border: 1px solid #ddd; padding: 12px;">₱ '.number_format($invoice->overall_total, 2).'</td></tr>';
        $invoiceTable .= '</table>';

        // $iamge = '<img src="'.$this->record->signature->depthead_signature.'" class="rounded-xl m-4 w-full" > </img>';

        return (new MailMessage)
            ->level('success')
            ->subject('Good News! Your Enrollment is Verified')
            ->greeting('Hello '.$this->record->student_name.',')
            ->line("We're excited to let you know that your enrollment has been verified. Welcome to our school!")
            ->line("Here are the subjects you'll be taking:")
            ->line(new HtmlString($subjectTable))
            ->line("And here's a breakdown of your fees:")
            ->line(new HtmlString($invoiceTable))
            ->line('Next Steps:')
            ->line('1. Please come to the school within the next 3 to 5 days to:')
            ->line('   • Pay your downpayment of ₱ '.number_format($invoice->downpayment, 2))
            ->line('   • Sign your student contract')
            ->line('   • Submit your required documents (please bring original and photocopy documents)')
            ->line("2. Once you've completed these steps, you'll be all set to start your classes!")
            ->line("If you have any questions, don't hesitate to reach out to us. We're here to help!")
            ->line("We're looking forward to seeing you soon and starting this exciting journey together.")
            ->salutation("Best wishes,\nThe Head of Department");
        // ->line(new \Illuminate\Support\HtmlString($iamge));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
