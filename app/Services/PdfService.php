<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;

class PdfService
{
    public function generateFromHtml(string $title, string $content)
    {
        $pdf = Pdf::loadHTML($this->wrapHtml($title, $content));
        return $pdf->output();
    }

    protected function wrapHtml(string $title, string $content)
    {
        return "
            <html>
                <head>
                    <style>
                        body { font-family: 'Helvetica', sans-serif; line-height: 1.6; }
                        h1 { color: #2c3e50; border-bottom: 2px solid #34495e; padding-bottom: 10px; }
                        .content { margin-top: 20px; }
                    </style>
                </head>
                <body>
                    <h1>{$title}</h1>
                    <div class='content'>
                        " . nl2br(e($content)) . "
                    </div>
                </body>
            </html>
        ";
    }
}
