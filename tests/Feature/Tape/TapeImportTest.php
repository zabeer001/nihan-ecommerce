<?php

use App\Models\Tape;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

it('updates existing tapes from an uploaded spreadsheet', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
        'is_frozen' => false,
    ]);

    $tape = Tape::query()->create([
        'tape_id' => 'TDB-100',
        'title' => 'Old Title',
        'year' => '1990',
        'distributor' => 'Old Distributor',
    ]);

    $file = UploadedFile::fake()->createWithContent(
        'tapes.csv',
        "tapedb ID,title,year,distributor,catalog\nTDB-100,New Title,1991,New Distributor,1234567\n"
    );

    $token = JWTAuth::fromUser($admin);

    $response = $this
        ->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/tapes/import', [
            'file' => $file,
        ]);

    $response
        ->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.updated', 1)
        ->assertJsonPath('data.created', 0);

    expect($tape->fresh())
        ->title->toBe('New Title')
        ->year->toBe('1991')
        ->distributor->toBe('New Distributor')
        ->catalog->toBe('1234567');
});

it('creates tapes from an uploaded spreadsheet when tapedb id does not exist', function () {
    $editor = User::factory()->create([
        'role' => 'editor',
        'is_frozen' => false,
    ]);

    $file = UploadedFile::fake()->createWithContent(
        'tapes.csv',
        "tapedb ID,title,year,distributor,catalog\nTDB-200,Created Title,2001,Created Distributor,7654321\n"
    );

    $token = JWTAuth::fromUser($editor);

    $response = $this
        ->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/tapes/import', [
            'file' => $file,
        ]);

    $response
        ->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.created', 1)
        ->assertJsonPath('data.updated', 0);

    $tape = Tape::query()->where('tape_id', 'TDB-200')->first();

    expect($tape)->not->toBeNull()
        ->and($tape->title)->toBe('Created Title')
        ->and($tape->year)->toBe('2001')
        ->and($tape->distributor)->toBe('Created Distributor')
        ->and($tape->catalog)->toBe('7654321')
        ->and($tape->user_id)->toBe($editor->id);
});

it('maps case import headers to case descriptions', function (string $header) {
    $editor = User::factory()->create([
        'role' => 'editor',
        'is_frozen' => false,
    ]);

    $file = UploadedFile::fake()->createWithContent(
        'tapes.csv',
        "tapedb ID,title,{$header}\nTDB-210,Case Type Title,Clamshell case\n"
    );

    $token = JWTAuth::fromUser($editor);

    $response = $this
        ->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/tapes/import', [
            'file' => $file,
        ]);

    $response
        ->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.created', 1);

    expect(Tape::query()->where('tape_id', 'TDB-210')->first())
        ->case_desc->toBe('Clamshell case');
})->with([
    'case',
    'Case',
    'case type',
    'Case Type',
]);

it('imports tapes from an xlsx spreadsheet', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
        'is_frozen' => false,
    ]);

    $tape = Tape::query()->create([
        'tape_id' => 'TDB-250',
        'title' => 'Original XLSX Title',
    ]);

    $file = createTapeImportXlsx([
        ['tapedb ID', 'title', 'year', 'distributor', 'catalog'],
        ['TDB-250', 'XLSX Title', '2005', 'XLSX Distributor', '3456789'],
    ]);

    $token = JWTAuth::fromUser($admin);

    $response = $this
        ->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/tapes/import', [
            'file' => $file,
        ]);

    $response
        ->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.updated', 1);

    expect($tape->fresh())
        ->title->toBe('XLSX Title')
        ->year->toBe('2005')
        ->distributor->toBe('XLSX Distributor')
        ->catalog->toBe('3456789');
});

it('reports row errors without stopping the whole import', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
        'is_frozen' => false,
    ]);

    Tape::query()->create([
        'tape_id' => 'TDB-300',
        'title' => 'Original Title',
    ]);

    $file = UploadedFile::fake()->createWithContent(
        'tapes.csv',
        "tapedb ID,title,catalog\nTDB-300,Updated Title,1234567\nTDB-301,Bad Catalog,ABC\n"
    );

    $token = JWTAuth::fromUser($admin);

    $response = $this
        ->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/tapes/import', [
            'file' => $file,
        ]);

    $response
        ->assertOk()
        ->assertJsonPath('status', 'partial_success')
        ->assertJsonPath('data.updated', 1)
        ->assertJsonPath('data.created', 0)
        ->assertJsonPath('data.errors.0.row', 3);

    expect(Tape::query()->where('tape_id', 'TDB-300')->first()->title)->toBe('Updated Title')
        ->and(Tape::query()->where('tape_id', 'TDB-301')->exists())->toBeFalse();
});

it('rejects imports with more than sixty tape rows', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
        'is_frozen' => false,
    ]);

    $rows = [
        ['tapedb ID', 'title'],
    ];

    foreach (range(1, 61) as $index) {
        $rows[] = ["TDB-LIMIT-{$index}", "Limit Test {$index}"];
    }

    $file = createTapeImportXlsx($rows);
    $token = JWTAuth::fromUser($admin);

    $response = $this
        ->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/tapes/import', [
            'file' => $file,
        ]);

    $response
        ->assertUnprocessable()
        ->assertJsonPath('errors.file.0', 'You can import a maximum of 60 tapes at a time. Please keep Excel files to 50 tapes or fewer for best performance.');

    expect(Tape::query()->where('tape_id', 'like', 'TDB-LIMIT-%')->exists())->toBeFalse();
});

function createTapeImportXlsx(array $rows): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'tape-import-');
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
    <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
    <Default Extension="xml" ContentType="application/xml"/>
    <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
    <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
</Types>');
    $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>');
    $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
    <sheets><sheet name="Sheet1" sheetId="1" r:id="rId1" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"/></sheets>
</workbook>');
    $zip->addFromString('xl/worksheets/sheet1.xml', tapeImportSheetXml($rows));
    $zip->close();

    return new UploadedFile(
        $path,
        'tapes.xlsx',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        null,
        true
    );
}

function tapeImportSheetXml(array $rows): string
{
    $xmlRows = [];

    foreach ($rows as $rowIndex => $row) {
        $cells = [];

        foreach ($row as $columnIndex => $value) {
            $reference = tapeImportColumnName($columnIndex).($rowIndex + 1);
            $value = htmlspecialchars((string) $value, ENT_XML1);
            $cells[] = '<c r="'.$reference.'" t="inlineStr"><is><t>'.$value.'</t></is></c>';
        }

        $xmlRows[] = '<row r="'.($rowIndex + 1).'">'.implode('', $cells).'</row>';
    }

    return '<?xml version="1.0" encoding="UTF-8"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
    <sheetData>'.implode('', $xmlRows).'</sheetData>
</worksheet>';
}

function tapeImportColumnName(int $index): string
{
    $name = '';
    $index++;

    while ($index > 0) {
        $index--;
        $name = chr(65 + ($index % 26)).$name;
        $index = intdiv($index, 26);
    }

    return $name;
}
