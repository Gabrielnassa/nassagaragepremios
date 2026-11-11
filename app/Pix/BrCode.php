<?php

namespace App\Pix;

class BrCode
{
    public static function build(string $key, int $amountCents, string $txid, string $name, string $city): string
    {
        $account = self::field('00', 'br.gov.bcb.pix').self::field('01', $key);

        $payload = self::field('00', '01')
            .self::field('01', '12')
            .self::field('26', $account)
            .self::field('52', '0000')
            .self::field('53', '986')
            .self::field('54', number_format($amountCents / 100, 2, '.', ''))
            .self::field('58', 'BR')
            .self::field('59', substr($name, 0, 25))
            .self::field('60', substr($city, 0, 15))
            .self::field('62', self::field('05', substr($txid, 0, 25)))
            .'6304';

        return $payload.self::crc16($payload);
    }

    private static function field(string $id, string $value): string
    {
        return $id.str_pad((string) strlen($value), 2, '0', STR_PAD_LEFT).$value;
    }

    public static function crc16(string $data): string
    {
        $crc = 0xFFFF;

        foreach (str_split($data) as $char) {
            $crc ^= ord($char) << 8;
            for ($i = 0; $i < 8; $i++) {
                $crc = ($crc & 0x8000) ? (($crc << 1) ^ 0x1021) : ($crc << 1);
                $crc &= 0xFFFF;
            }
        }

        return strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
    }
}
