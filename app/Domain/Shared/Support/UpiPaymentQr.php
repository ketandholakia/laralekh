<?php
declare(strict_types=1);
namespace App\Domain\Shared\Support;

use App\Domain\Shared\ValueObjects\Money;
use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Common\Version;
use chillerlan\QRCode\Data\Byte;
use chillerlan\QRCode\Data\QRMatrix;
use chillerlan\QRCode\Output\QRCodeOutputException;
use chillerlan\QRCode\QROptions;
use chillerlan\QRCode\QRCode;
use InvalidArgumentException;

// Supplier-side UPI payment QR for invoices. This is a payment convenience QR
// (like the taxpayer-generated B2C dynamic QR) and is NOT the IRP-signed
// e-invoice QR, which this project does not implement.
final class UpiPaymentQr
{
    public static function isValidVpa(string $vpa): bool
    {
        return preg_match('/^[a-zA-Z0-9._-]{2,64}@[a-zA-Z]{2,32}$/D', $vpa) === 1;
    }

    public static function uri(string $vpa, string $payeeName, Money $amount, string $note): string
    {
        if (!self::isValidVpa($vpa)) {
            throw new InvalidArgumentException('Configure a valid UPI ID to print a payment QR.');
        }
        if ($payeeName === '' || $amount->isNegative() || $amount->isZero()) {
            throw new InvalidArgumentException('A payee name and a positive amount are required for the payment QR.');
        }
        return 'upi://pay?'.http_build_query([
            'pa' => $vpa,
            'pn' => mb_substr($payeeName, 0, 40),
            'am' => (string) $amount,
            'cu' => 'INR',
            'tn' => mb_substr($note, 0, 50),
        ], '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Renders the QR as a borderless HTML table of fixed-size cells, which
     * DomPDF lays out reliably without the GD/Imagick image backends.
     *
     * @return string HTML for direct inclusion in the PDF template.
     */
    public static function html(string $data, int $cellSize = 3): string
    {
        $matrix = self::booleanMatrix($data);
        $size = count($matrix);
        $rows = '';
        foreach ($matrix as $row) {
            $cells = '';
            foreach ($row as $dark) {
                $cells .= '<td style="width:'.$cellSize.'pt;height:'.$cellSize.'pt;padding:0;'
                    .'border:0;background:'.($dark ? '#000000' : '#ffffff').';"></td>';
            }
            $rows .= '<tr>'.$cells.'</tr>';
        }
        return '<table style="border-collapse:collapse;border-spacing:0;">'.$rows.'</table>';
    }

    /** @return list<list<bool>> */
    private static function booleanMatrix(string $data): array
    {
        try {
            $qrcode = new QRCode(new QROptions([
                'version' => Version::AUTO,
                'eccLevel' => EccLevel::L,
            ]));
            $qrcode->addSegment(new Byte($data));
            $matrix = $qrcode->getQRMatrix();
        } catch (QRCodeOutputException $e) {
            throw new InvalidArgumentException('Unable to render the payment QR.', 0, $e);
        }
        $out = [];
        foreach ($matrix->getMatrix() as $row) {
            $out[] = array_map(fn (int $type): bool => $matrix->isDark($type), $row);
        }
        return $out;
    }
}
