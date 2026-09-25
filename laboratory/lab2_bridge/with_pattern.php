<?php
// Lab 2 - WITH Bridge (GOOD starter, has gaps to complete)
// Times formatter (abstraction) x DataSource + Compressor (implementors) - compression Bridge.
// Swap the source or the compressor freely without touching the report logic.

// ---- Implementor A: where records come from ----
interface DataSource
{
    public function fetch(): array;
}

class ApiDataSource implements DataSource
{
    public function fetch(): array
    {
        $json = @file_get_contents("https://jsonplaceholder.typicode.com/posts?_limit=4");
        if ($json === false || trim($json) === "") {
            throw new RuntimeException("Live API unreachable.");
        }
        return json_decode($json, true);
    }
}

class FileDataSource implements DataSource
{
    public function fetch(): array
    {
        $json = @file_get_contents(__DIR__ . "/data.json");
        if ($json === false) {
            throw new RuntimeException("data.json not found.");
        }
        return json_decode($json, true);
    }
}

// ---- Implementor B: how output is compressed ----
interface Compressor
{
    public function compress(string $content): string;
    public function isCompressed(): bool;
}

class GzipCompressor implements Compressor
{
    public function compress(string $content): string
    {
        return gzencode($content, 6);
    }

    public function isCompressed(): bool
    {
        return true;
    }
}

class NoneCompressor implements Compressor
{
    public function compress(string $content): string
    {
        return $content;
    }

    public function isCompressed(): bool
    {
        return false;
    }
}

// ---- Abstraction: the report, delegates both varying dimensions ----
abstract class TimesFormatter
{
    public function __construct(
        protected DataSource $source,
        protected Compressor $compressor
    ) {}

    public function setCompressor(Compressor $compressor): void
    {
        $this->compressor = $compressor;
    }

    abstract protected function toText(array $records): string;

    public function generate(string $title): string
    {
        $records = $this->source->fetch();
        $body = $this->toText($records);
        return $this->compressor->compress($title . "\n" . $body);
    }
}

class AttendanceTimesFormatter extends TimesFormatter
{
    protected function toText(array $records): string
    {
        $lines = [];
        foreach ($records as $r) {
            $lines[] = "{$r['userId']}  {$r['id']}  " . substr($r['title'] ?? '', 0, 18);
        }
        return implode("\n", $lines);
    }
}

class TimesheetJsonFormatter extends TimesFormatter
{
    protected function toText(array $records): string
    {
        return json_encode($records, JSON_PRETTY_PRINT);
    }
}

if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    echo "WITH Bridge (GOOD - complete TODOs):\n";
    
    // Demo: 2 formats x 2 sources x 2 compressors = 8 combos with 6 classes
    echo "\n  === 2 Formats x 2 Sources x 2 Compressors = 8 Combos ===\n";
    $formatters = [
        'Attendance' => new AttendanceTimesFormatter(new ApiDataSource(), new NoneCompressor()),
        'TimesheetJSON' => new TimesheetJsonFormatter(new ApiDataSource(), new NoneCompressor()),
    ];
    $sources = [
        'API' => new ApiDataSource(),
        'File' => new FileDataSource(),
    ];
    $compressors = [
        'None' => new NoneCompressor(),
        'Gzip' => new GzipCompressor(),
    ];
    
    foreach ($formatters as $formatName => $formatter) {
        foreach ($sources as $sourceName => $source) {
            foreach ($compressors as $compName => $compressor) {
                // Create fresh instance with this source/compressor combo
                $reportClass = get_class($formatter);
                $report = new $reportClass($source, $compressor);
                $output = $report->generate($formatName === 'Attendance' ? 'TIMES' : 'TIMESHEET');
                $size = strlen($output);
                $marker = $compressor->isCompressed() && $size < 200 ? ' <-- SMALLER' : '';
                printf("  [%s + %s + %s] %scompressed, %d bytes%s\n", 
                    $formatName, $sourceName, $compName, 
                    $compressor->isCompressed() ? '' : 'not ', $size, $marker);
            }
        }
    }
    
    // Demo TimesheetJsonFormatter with runtime compressor swap
    echo "\n  TimesheetJsonFormatter with runtime compressor swap:\n";
    $jsonReport = new TimesheetJsonFormatter(new FileDataSource(), new NoneCompressor());
    echo $jsonReport->generate("TIMESHEET");
    echo "\n";
    
    $jsonReport->setCompressor(new GzipCompressor());
    $compressed = $jsonReport->generate("TIMESHEET");
    printf("  After setCompressor(GzipCompressor): compressed=%s, %d bytes\n", $compressed !== '' ? 'yes' : 'no', strlen($compressed));
    
    echo "\n  Classes used: AttendanceTimesFormatter, TimesheetJsonFormatter (2)";
    echo "\n              ApiDataSource, FileDataSource (2)";
    echo "\n              NoneCompressor, GzipCompressor (2)";
    echo "\n              Total: 6 classes for 8 combinations\n";
}