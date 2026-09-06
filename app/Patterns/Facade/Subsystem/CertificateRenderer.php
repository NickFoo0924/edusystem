<?php

/**
 * LearnSync -- Facade pattern: subsystem collaborator
 *
 * Module 1: Identity, Access & Digital Credentialing
 *
 * @author Serena Lim Sze Kee
 */

namespace App\Patterns\Facade\Subsystem;

use App\Models\Certificate;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/**
 * SUBSYSTEM COMPONENT -- turning a certificate row into a printable artefact.
 *
 * One of the five collaborators hidden behind the CredentialAuthority Facade,
 * and on its own the clearest argument for the pattern: issuing a credential
 * touches DomPDF, a QR encoder, the filesystem and a placeholder grammar, and
 * a controller should have to know about none of them.
 */
class CertificateRenderer
{
    /**
     * Where a credential's PDF lives on the private disk. It is served through
     * a controller that checks permissions, never linked to directly.
     */
    public function pdfPathFor(string $credentialId): string
    {
        return 'certificates/'.$credentialId.'.pdf';
    }

    /**
     * The public URL a third party visits, and the payload encoded in the QR
     * code printed on the PDF.
     */
    public function verificationUrl(string $credentialId): string
    {
        return route('certificates.verify', ['credential_id' => $credentialId]);
    }

    /**
     * The QR code for a credential, as an SVG data URI that DomPDF can embed.
     */
    public function verificationQrCode(string $credentialId): string
    {
        $svg = QrCode::format('svg')
            ->size(150)
            ->margin(0)
            ->errorCorrection('M')
            ->generate($this->verificationUrl($credentialId));

        return 'data:image/svg+xml;base64,'.base64_encode((string) $svg);
    }

    /**
     * Render the template to PDF with the QR code embedded, and store it.
     */
    public function render(Certificate $certificate): void
    {
        $certificate->loadMissing(['student', 'course', 'learningPath', 'certificateTemplate']);

        $pdf = Pdf::loadView('certificates.pdf', [
            'certificate' => $certificate,
            'heading' => $certificate->learning_path_id !== null
                ? 'Pathway Certificate'
                : 'Certificate of Completion',
            'bodyText' => $this->fillPlaceholders($certificate),
            'qrCode' => $this->verificationQrCode($certificate->credential_id),
            'verificationUrl' => $this->verificationUrl($certificate->credential_id),
        ])->setPaper('a4', 'landscape');

        Storage::disk('local')->put($certificate->pdf_path, $pdf->output());
    }

    /**
     * Substitute the template placeholders listed in EduSystem.md 1C.
     */
    private function fillPlaceholders(Certificate $certificate): string
    {
        return str_replace(
            ['{{student_name}}', '{{course_title}}', '{{score}}', '{{issued_date}}', '{{credential_id}}'],
            [
                $certificate->student->name,
                $certificate->course?->title ?? $certificate->learningPath?->title ?? '',
                rtrim(rtrim(number_format($certificate->final_score, 2), '0'), '.'),
                $certificate->issued_at->format('j F Y'),
                $certificate->credential_id,
            ],
            $certificate->certificateTemplate->body_text
        );
    }
}
