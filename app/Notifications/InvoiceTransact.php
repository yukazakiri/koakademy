<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\GeneralSetting;
use Barryvdh\Snappy\Facades\SnappyImage;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Label\Font\NotoSans;
use Endroid\QrCode\Label\LabelAlignment;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Storage;

final class InvoiceTransact extends Notification
{
    use Queueable;

    // public $downpayment;
    /**
     * Create a new notification instance.
     */
    public function __construct(public $record, public $student)
    {
        // $this->downpayment = $downpayment;
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
        $transaction = $this->record;
        $student = $this->student;
        $student->studentTransactions
            ->where('transaction_id', $transaction->id)
            ->first();
        $transactionUrl = url(
            '/transactions/'.$transaction->transaction_number
        );
        $settings = GeneralSetting::query()->first();
        $mailMessage = (new MailMessage)
            ->subject('Invoice Notification')
            ->greeting('Hello '.$student->name.',')
            ->line(
                'We are pleased to inform you that your transaction has been successfully recorded. Below are the details of your transaction:'
            )
            ->line('Transaction ID: '.$transaction->transaction_number)
            ->line('O.R Number: '.$transaction->invoicenumber)
            ->line('Description: '.$transaction->description)
            // ->line('Amount: Php '.number_format($this->downpayment, 2))
            ->line('Status: '.$transaction->status)
            ->line(
                'Transaction Date: '.
                    $transaction->created_at->format('F j, Y, g:i a')
            )
            ->line(
                'If you have any questions or concerns, please do not hesitate to contact us.'
            )
            ->line('Thank you for using our application!');

        if ($settings->enable_public_transactions) {
            $mailMessage->action('View Transaction', $transactionUrl);
        }

        $screenshotPath =
            'screenshots/'.$transaction->transaction_number.'.png';
        $fullScreenshotPath = storage_path('app/public/'.$screenshotPath);
        $screenshotDir = dirname($fullScreenshotPath);
        if (! file_exists($screenshotDir)) {
            mkdir($screenshotDir, 0755, true);
        }

        // Generate screenshot using SnappyImage (wkhtmltoimage)
        SnappyImage::loadFile($transactionUrl)
            ->setOption('width', 1920)
            ->setOption('height', 1080)
            ->save($fullScreenshotPath, true);

        if (! file_exists($fullScreenshotPath) || filesize($fullScreenshotPath) === 0) {
            throw new Exception('Failed to generate screenshot using SnappyImage');
        }

        // Upload screenshot to local
        $localScreenshotPath = Storage::disk('local')->putFileAs(
            'screenshots',
            $fullScreenshotPath,
            $transaction->transaction_number.'.png'
        );

        if ($settings->enable_qr_codes) {
            // Generate screenshot of the transaction page using Snappy
            $screenshotPath =
                'screenshots/'.$transaction->transaction_number.'.png';
            SnappyImage::loadFile($transactionUrl)->save(
                storage_path('app/public/'.$screenshotPath),
                true
            );
            // Upload screenshot to local
            $localScreenshotPath = Storage::disk('local')->putFileAs(
                'screenshots',
                storage_path('app/public/'.$screenshotPath),
                $transaction->transaction_number.'.png'
            );
            $screenshotUrl = Storage::disk('local')->url($localScreenshotPath);

            $qrCode = Builder::create()
                ->writer(new PngWriter)
                ->writerOptions([])
                ->data($screenshotUrl)
                ->encoding(new Encoding('UTF-8'))
                ->errorCorrectionLevel(ErrorCorrectionLevel::High)
                ->size(200)
                ->margin(10)
                ->roundBlockSizeMode(RoundBlockSizeMode::Margin)
                ->labelText('Scan to view transaction')
                ->labelFont(new NotoSans(12))
                ->labelAlignment(LabelAlignment::Center)
                ->build();

            $qrCodeData = $qrCode->getDataUri();

            // Save the QR code image to your local bucket
            $qrCodePath =
                'qrcodes/'.$transaction->transaction_number.'.png';
            Storage::disk('local')->put($qrCodePath, $qrCode->getString());

            $qrCodeUrl = Storage::disk('local')->url($qrCodePath);
            $qrcodeImgTag = '<img src="'.$qrCodeUrl.'" alt="QR Code">';

            $mailMessage
                ->line('Scan the QR code below to view your transaction:')
                ->attachData($qrCodeData, 'qrcode.png', [
                    'mime' => 'image/png',
                ]);
        }

        $mailMessage
            ->line('Please keep this email for your records.')
            ->attach(storage_path('app/public/'.$screenshotPath), [
                'as' => 'transaction_screenshot.png',
                'mime' => 'image/png',
            ]);

        return $mailMessage;
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
