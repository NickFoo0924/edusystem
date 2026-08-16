{{-- certificates/pdf.blade.php --}}
{{--
    Rendered by DomPDF from the CredentialAuthority, never served as a web page.
    Tailwind is unavailable here because there is no compiled stylesheet in the
    PDF context, so everything is inline CSS that DomPDF understands.
--}}
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        /*
         * Geometry note: DomPDF's box-sizing support is unreliable, so nothing
         * here sets an explicit width and only one element sets a height. The
         * page margin alone defines the printable area, which keeps the frame
         * from overflowing the right edge or spilling onto a second page.
         *
         * A4 landscape is 842pt x 595pt. With a 26pt page margin the usable
         * height is 543pt; the frame and inner borders and padding consume 54pt,
         * so .inner is given 480pt and the whole certificate stays on one page.
         */
        @page { margin: 26pt; }

        body {
            margin: 0;
            font-family: "DejaVu Sans", sans-serif;
            color: #1f2937;
        }

        .frame {
            border: 3pt solid #1e3a8a;
            padding: 5pt;
        }

        .inner {
            border: 1pt solid #93c5fd;
            height: 480pt;
            padding: 20pt 26pt 16pt 26pt;
            text-align: center;
        }

        .issuer {
            font-size: 10pt;
            letter-spacing: 3pt;
            text-transform: uppercase;
            color: #1e3a8a;
        }

        .title {
            font-size: 27pt;
            font-weight: bold;
            color: #1e3a8a;
            margin-top: 8pt;
        }

        .rule {
            width: 90pt;
            border-bottom: 2pt solid #f59e0b;
            margin: 12pt auto 0 auto;
        }

        .recipient {
            font-size: 22pt;
            margin-top: 18pt;
            color: #111827;
        }

        .body {
            font-size: 11pt;
            line-height: 1.7;
            margin-top: 14pt;
            color: #374151;
        }

        .footer {
            width: 100%;
            margin-top: 22pt;
            font-size: 8.5pt;
            color: #4b5563;
        }

        .footer td { vertical-align: bottom; }

        .credential {
            font-family: "DejaVu Sans Mono", monospace;
            font-size: 11pt;
            letter-spacing: 1pt;
            color: #1e3a8a;
        }

        .signature-line {
            border-top: 1pt solid #9ca3af;
            width: 130pt;
            padding-top: 4pt;
        }

        .qr-caption {
            font-size: 7pt;
            color: #6b7280;
            margin-top: 2pt;
        }
    </style>
</head>
<body>
    <div class="frame">
        <div class="inner">

            <div class="issuer">{{ config('app.name') }}</div>
            <div class="title">{{ $heading }}</div>
            <div class="rule"></div>

            <div class="recipient">{{ $certificate->student->name }}</div>

            <div class="body">{!! nl2br(e($bodyText)) !!}</div>

            <table class="footer">
                <tr>
                    <td style="width: 38%; text-align: left;">
                        @if ($certificate->certificateTemplate->signature_path)
                            <img src="{{ storage_path('app/public/'.$certificate->certificateTemplate->signature_path) }}"
                                 style="height: 34pt;" alt="">
                        @endif
                        <div class="signature-line">Authorised Signatory</div>
                    </td>

                    <td style="width: 34%; text-align: center;">
                        <div>Credential ID</div>
                        <div class="credential">{{ $certificate->credential_id }}</div>
                        <div style="margin-top: 6pt;">Issued {{ $certificate->issued_at->format('j F Y') }}</div>
                    </td>

                    <td style="width: 28%; text-align: right;">
                        <img src="{{ $qrCode }}" style="width: 76pt; height: 76pt;" alt="Verification QR code">
                        <div class="qr-caption">Scan to verify</div>
                    </td>
                </tr>
            </table>

        </div>
    </div>
</body>
</html>
