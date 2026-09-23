<?php

namespace Tests\Unit;

use App\Support\Xlsx\XlsxReader;
use App\Support\Xlsx\XlsxWriter;
use PHPUnit\Framework\TestCase;

class XlsxTest extends TestCase
{
    public function test_roundtrip_keeps_text_numbers_and_special_characters(): void
    {
        $path = XlsxWriter::write("Hisobot & <1>", ['F.I.O', 'Summa', 'Izoh'], [
            ["Olim O'ktamov", 1250000, 'Tez & <oson> "qo\'shtirnoq"'],
            ['Ikkinchi', 0, ''],
            ['Uchinchi', 3, "Ko'p\nqatorli"],
        ], 'Sarlavha');

        $rows = XlsxReader::read($path, 'xlsx');
        unlink($path);

        $this->assertSame(['Sarlavha'], $rows[0]);                      // sarlavha qatori
        $this->assertSame(['F.I.O', 'Summa', 'Izoh'], $rows[1]);
        $this->assertSame(["Olim O'ktamov", '1250000', 'Tez & <oson> "qo\'shtirnoq"'], $rows[2]);
        $this->assertSame('Ikkinchi', $rows[3][0]);
        $this->assertSame("Ko'p\nqatorli", $rows[4][2]);
    }

    public function test_csv_with_semicolon_and_bom(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($path, "\xEF\xBB\xBFIsm;Telefon\nAli;901234567\n\n;\nVali;902223344\n");

        $rows = XlsxReader::read($path, 'csv');
        unlink($path);

        $this->assertSame([['Ism', 'Telefon'], ['Ali', '901234567'], ['Vali', '902223344']], $rows);
    }

    public function test_column_names_and_empty_cells(): void
    {
        $this->assertSame('A', XlsxWriter::columnName(1));
        $this->assertSame('Z', XlsxWriter::columnName(26));
        $this->assertSame('AA', XlsxWriter::columnName(27));

        $path = XlsxWriter::write('X', ['A', 'B', 'C'], [['1', null, 'z']]);
        $rows = XlsxReader::read($path, 'xlsx');
        unlink($path);

        $this->assertSame(['1', '', 'z'], $rows[1]);
    }

    public function test_invalid_file_raises_readable_error(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'bad');
        file_put_contents($path, 'bu excel emas');

        $this->expectException(\RuntimeException::class);
        XlsxReader::read($path, 'xlsx');
    }
}
