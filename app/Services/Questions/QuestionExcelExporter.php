<?php

namespace App\Services\Questions;

use App\Models\Question;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;
use XMLWriter;
use ZipArchive;

class QuestionExcelExporter
{
    public const HEADERS = ['ders', 'konu', 'zorluk', 'soru', 'A', 'B', 'C', 'D', 'cevap'];

    private const LABELS = ['A', 'B', 'C', 'D'];

    /**
     * Arşivlenmiş, eksik şıklı veya cevap işaretlemesi tutarsız sorular
     * eğitim verisine alınmaz. İçerik doğruluğu ayrıca gözden geçirilmelidir.
     *
     * @return Builder<Question>
     */
    public function eligibleQuery(): Builder
    {
        return Question::query()
            ->whereRaw("TRIM(questions.text) <> ''")
            ->whereIn('correct_option', self::LABELS)
            ->has('options', '=', 4)
            ->whereHas('options', fn (Builder $query): Builder => $query
                ->whereIn('label', self::LABELS)
                ->whereRaw("TRIM(options.text) <> ''"), '=', 4)
            ->whereHas('options', fn (Builder $query): Builder => $query->where('is_correct', true), '=', 1)
            ->whereHas('options', fn (Builder $query): Builder => $query
                ->where('is_correct', true)
                ->whereColumn('options.label', 'questions.correct_option'));
    }

    /**
     * @param  Builder<Question>  $query
     * @return string İndirme tamamlanınca silinecek geçici XLSX dosyası.
     */
    public function export(Builder $query, bool $metadata = false): string
    {
        $path = tempnam(sys_get_temp_dir(), 'question-export-');
        $sheetPath = tempnam(sys_get_temp_dir(), 'question-sheet-');

        if ($path === false || $sheetPath === false) {
            throw new RuntimeException('Excel için geçici dosya oluşturulamadı.');
        }

        try {
            $headers = $metadata
                ? [...self::HEADERS, 'soru_id', 'kademe', 'kaynak', 'kaynak_url', 'kaynak_sayfa']
                : self::HEADERS;
            $xml = new XMLWriter;
            $xml->openUri($sheetPath);
            $xml->startDocument('1.0', 'UTF-8');
            $xml->startElementNS(null, 'worksheet', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $xml->startElement('sheetViews');
            $xml->startElement('sheetView');
            $xml->writeAttribute('workbookViewId', '0');
            $xml->startElement('pane');
            $xml->writeAttribute('ySplit', '1');
            $xml->writeAttribute('topLeftCell', 'A2');
            $xml->writeAttribute('activePane', 'bottomLeft');
            $xml->writeAttribute('state', 'frozen');
            $xml->endElement();
            $xml->endElement();
            $xml->endElement();
            $xml->startElement('cols');

            foreach ($headers as $index => $header) {
                $xml->startElement('col');
                $xml->writeAttribute('min', (string) ($index + 1));
                $xml->writeAttribute('max', (string) ($index + 1));
                $xml->writeAttribute('width', $header === 'soru' ? '90' : (in_array($header, self::LABELS, true) ? '40' : '24'));
                $xml->writeAttribute('customWidth', '1');
                $xml->endElement();
            }

            $xml->endElement();
            $xml->startElement('sheetData');
            $this->writeRow($xml, 1, $headers, 1);
            $row = 1;

            foreach ((clone $query)->with(['course', 'options', 'pastExamSection.pastExam'])->lazyById(500) as $question) {
                if (++$row > 1048576) {
                    throw ValidationException::withMessages(['export' => 'Excel satır sınırı aşıldı. Ders veya kaynak filtresiyle daha küçük bir seçim yapın.']);
                }

                $options = $question->options->keyBy('label');
                $values = [
                    $question->course->name,
                    '',
                    '',
                    implode("\n\n", array_values(array_filter([$question->passage, $question->text], static fn (?string $text): bool => $text !== null && $text !== ''))),
                    ...array_map(static fn (string $label): string => $options[$label]->text, self::LABELS),
                    $question->correct_option,
                ];

                if ($metadata) {
                    $values = [
                        ...$values,
                        (string) $question->id,
                        $question->course->level->value,
                        $question->source->value,
                        $question->pastExamSection?->pastExam->source_url ?? $question->course->source_url ?? '',
                        $question->source_page === null ? '' : (string) $question->source_page,
                    ];
                }

                $this->writeRow($xml, $row, $values, 0);
            }

            if ($row === 1) {
                throw ValidationException::withMessages(['export' => 'Seçiminize uygun, dört şıklı ve tutarlı cevap anahtarı olan soru bulunamadı.']);
            }

            $xml->endElement();
            $xml->startElement('autoFilter');
            $xml->writeAttribute('ref', 'A1:'.$this->columnName(count($headers) - 1).$row);
            $xml->endElement();
            $xml->endElement();
            $xml->endDocument();
            $xml->flush();
            unset($xml);

            $this->package($path, $sheetPath);

            return $path;
        } catch (Throwable $exception) {
            unlink($path);

            throw $exception;
        } finally {
            unlink($sheetPath);
        }
    }

    /** @param list<string> $values */
    private function writeRow(XMLWriter $xml, int $number, array $values, int $style): void
    {
        $xml->startElement('row');
        $xml->writeAttribute('r', (string) $number);

        foreach ($values as $index => $value) {
            if (strlen(mb_convert_encoding($value, 'UTF-16LE', 'UTF-8')) > 65534
                || preg_match('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', $value)) {
                throw ValidationException::withMessages(['export' => "{$number}. satırda Excel’in metin sınırlarını aşan içerik var. Veri kaybını önlemek için dosya oluşturulmadı."]);
            }

            $xml->startElement('c');
            $xml->writeAttribute('r', $this->columnName($index).$number);
            $xml->writeAttribute('s', (string) $style);
            $xml->writeAttribute('t', 'inlineStr');
            $xml->startElement('is');
            $xml->startElement('t');
            $xml->writeAttribute('xml:space', 'preserve');
            $xml->text($value);
            $xml->endElement();
            $xml->endElement();
            $xml->endElement();
        }

        $xml->endElement();
    }

    private function columnName(int $index): string
    {
        $name = '';

        do {
            $name = chr(65 + $index % 26).$name;
            $index = intdiv($index, 26) - 1;
        } while ($index >= 0);

        return $name;
    }

    private function package(string $path, string $sheetPath): void
    {
        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Excel arşivi oluşturulamadı.');
        }

        $files = [
            '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>',
            '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
            'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Sorular" sheetId="1" r:id="rId1"/></sheets></workbook>',
            'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>',
            'xl/styles.xml' => '<?xml version="1.0" encoding="UTF-8"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><color rgb="FFFFFFFF"/><sz val="11"/><name val="Calibri"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF334155"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="1"><border/></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="2"><xf numFmtId="49" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf><xf numFmtId="49" fontId="1" fillId="2" borderId="0" xfId="0" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>',
        ];

        try {
            foreach ($files as $name => $content) {
                if (! $zip->addFromString($name, $content)) {
                    throw new RuntimeException('Excel dosyası paketlenemedi.');
                }
            }

            if (! $zip->addFile($sheetPath, 'xl/worksheets/sheet1.xml')) {
                throw new RuntimeException('Excel soru sayfası paketlenemedi.');
            }
        } finally {
            if (! $zip->close()) {
                throw new RuntimeException('Excel dosyası tamamlanamadı.');
            }
        }
    }
}
