<?php
declare(strict_types=1);
namespace App\Domain\Gst\Returns;

/** Validates GSTIN structure and its standard base-36 mod-36 check digit. */
final class GstinValidator
{
    private const ALPHABET = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    private const STATE_CODES = [
        '01','02','03','04','05','06','07','08','09','10','11','12','13','14','15','16','17','18','19',
        '20','21','22','23','24','25','26','27','28','29','30','31','32','33','34','35','36','37','38','97','99',
    ];

    public function isValid(string $gstin):bool
    {
        $gstin=strtoupper(trim($gstin));
        if(!preg_match('/^(\d{2})([A-Z]{5}\d{4}[A-Z])([1-9A-Z])Z([0-9A-Z])$/D',$gstin,$matches))return false;
        if(!in_array($matches[1],self::STATE_CODES,true))return false;

        $sum=0;$factor=2;
        for($index=13;$index>=0;$index--){
            $value=strpos(self::ALPHABET,$gstin[$index]);
            if($value===false)return false;
            $product=$value*$factor;
            $sum+=intdiv($product,36)+($product%36);
            $factor=$factor===2?1:2;
        }
        $checkValue=(36-($sum%36))%36;
        return self::ALPHABET[$checkValue]===$gstin[14];
    }
}
