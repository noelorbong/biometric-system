<?php

namespace Tests\Unit;

use App\Http\Controllers\UserController;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ZipArchive;

class UserImportExcelTest extends TestCase
{
    public function test_shared_strings_headers_and_names_are_decoded_with_extra_id_column(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'user-import-');
        try {
            $zip = new ZipArchive();
            $zip->open($path, ZipArchive::OVERWRITE);
            $zip->addFromString('xl/sharedStrings.xml', '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><si><t>id</t></si><si><t>USERID</t></si><si><t>Badgenumber</t></si><si><t>Name</t></si><si><r><t>ABAIGAR, </t></r><r><t>APRIL D.</t></r></si></sst>');
            $zip->addFromString('xl/worksheets/sheet1.xml', '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData><row><c r="A1" t="s"><v>0</v></c><c r="B1" t="s"><v>1</v></c><c r="C1" t="s"><v>2</v></c><c r="D1" t="s"><v>3</v></c><c r="AA1" t="inlineStr"><is><t>CardNo</t></is></c></row><row><c r="A2"><v>1</v></c><c r="B2"><v>2</v></c><c r="C2"><v>2</v></c><c r="D2" t="s"><v>4</v></c><c r="AA2"><v>1234</v></c></row><row><c r="A3"><v>2</v></c><c r="B3"><v>3</v></c><c r="C3" t="inlineStr"><is><t>0003</t></is></c><c r="D3" t="inlineStr"><is><t>ABAIGAR, EVELYN D.</t></is></c></row></sheetData></worksheet>');
            $zip->close();
            $method = new ReflectionMethod(UserController::class, 'parseUserInfoXlsx');
            $rows = $method->invoke(new UserController(), $path);
            self::assertCount(2, $rows);
            self::assertSame(2, $rows[0]['resolved_user_id']);
            self::assertSame('2', $rows[0]['pin']);
            self::assertSame('ABAIGAR, APRIL D.', $rows[0]['name']);
            self::assertSame(1234, $rows[0]['card']);
            self::assertSame('ID', $rows[0]['_source_columns'][0]);
            self::assertSame('ABAIGAR, APRIL D.', $rows[0]['_source_data']['NAME']);
            self::assertSame('', $rows[0]['password']);
            self::assertSame('0003', $rows[1]['pin']);
            self::assertSame('ABAIGAR, EVELYN D.', $rows[1]['name']);
            self::assertSame(0, $rows[1]['card']);
        } finally {
            unlink($path);
        }
    }
}
