<?php
// Wymuszamy ścisłe typowanie, żeby szybciej wychwytywać błędy (np. złe typy argumentów).
declare(strict_types=1);

use Smalot\PdfParser\Parser;

/**
 * Kończy działanie programu z czytelnym komunikatem błędu.
 *
 * - W CLI wypisuje błąd na STDERR.
 * - W WWW ustawia kod HTTP 400 i zwraca zwykły tekst.
 */
function fail(string $message, int $code = 1): never {
    if (PHP_SAPI === 'cli') {
        // CLI: komunikat błędu na STDERR + kod wyjścia.
        fwrite(STDERR, $message . PHP_EOL);
        exit($code);
    }

    // WWW: czytelny błąd dla klienta HTTP.
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo $message;
    exit($code);
}

// Ścieżka do autoloadera Composera (wymagane do działania biblioteki PDF parsera).
$autoload = __DIR__ . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
if (!is_file($autoload)) {
    // Jeśli brakuje zależności, podpowiadamy najczęstszą komendę naprawczą.
    fail("Brak vendor/autoload.php. Uruchom: composer install");
}
// Ładujemy zależności zainstalowane przez Composera.
require $autoload;

/**
 * Normalizuje tekst wyciągnięty z PDF:
 * - usuwa tabulatory i znaki końca linii (zamienia na spacje)
 * - zwija wielokrotne białe znaki do jednej spacji
 * - przycina spacje na początku i końcu
 *
 * Dzięki temu w JSON nie pojawiają się sekwencje typu \n czy \t.
 */
function normalizePdfText(string $text): string {
    // Zamiana typowych znaków sterujących na spacje.
    $text = str_replace(["\r\n", "\r", "\n", "\t"], ' ', $text);
    // Zwijamy dowolne ciągi białych znaków do pojedynczej spacji.
    $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
    // Na koniec przycinamy.
    return trim($text);
}

/**
 * Czyści listę fontów, żeby w JSON zostawały tylko nazwy fontów (stringi),
 * bez liczbowych kluczy/identyfikatorów które czasem zwraca parser.
 */
function normalizeFonts(array $fonts): array {
    // Bierzemy klucze i zostawiamy wyłącznie te będące stringami (np. "F1").
    $keys = array_keys($fonts);
    $names = array_values(array_filter($keys, 'is_string'));
    return $names;
}

/**
 * Parsuje PDF i zwraca dane w postaci tablicy gotowej do json_encode().
 *
 * Zwracamy:
 * - metadata: metadane dokumentu
 * - pages: listę stron (numer, tekst, fonty)
 */
function pdfToArray(string $pdfPath): array {
    // Inicjalizacja parsera PDF.
    $parser = new Parser();
    // Wczytanie i sparsowanie pliku PDF.
    $pdf = $parser->parseFile($pdfPath);

    // Główna struktura wyniku.
    $data = [
        'metadata' => $pdf->getDetails(),
        'pages' => [],
    ];

    // Iterujemy po stronach i wyciągamy tekst + listę fontów.
    foreach ($pdf->getPages() as $i => $page) {
        // Tekst z PDF często zawiera nowe linie i tabulatory — normalizujemy go przed zapisem do JSON.
        $text = normalizePdfText($page->getText());
        // Normalizujemy fonty (usuwamy liczby typu 1, 2... zostawiamy same nazwy).
        $fonts = normalizeFonts($page->getFonts());
        $data['pages'][] = [
            'page' => $i + 1,
            'text' => $text,
            'fonts' => $fonts,
        ];
    }

    return $data;
}

/**
 * Pobiera ścieżkę do PDF z wejścia:
 * - CLI: pierwszy argument (argv[1])
 * - WWW: parametr GET ?file=...
 */
function getPdfPathFromInput(): ?string {
    if (PHP_SAPI === 'cli') {
        // CLI: argument przekazany w konsoli.
        global $argv;
        return $argv[1] ?? null;
    }

    // WWW: ścieżka/nazwa pliku przekazana jako query param.
    return isset($_GET['file']) ? (string)$_GET['file'] : null;
}

// Ustalamy plik wejściowy: z CLI/WWW albo domyślny plik w katalogu projektu.
$pdfPath = getPdfPathFromInput() ?? 'KRS.pdf';
if (!is_file($pdfPath)) {
    // Dodatkowa wskazówka: jeśli da się policzyć realpath, dopiszemy go do komunikatu.
    $resolved = realpath($pdfPath);
    $hint = $resolved ? " (realpath: {$resolved})" : '';
    fail("Nie znaleziono pliku PDF: {$pdfPath}{$hint}");
}

try {
    // Budujemy JSON; JSON_THROW_ON_ERROR sprawia, że w razie problemu dostaniemy wyjątek.
    $json = json_encode(
        pdfToArray($pdfPath),
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
    );
} catch (Throwable $e) {
    // Jeden wspólny komunikat dla błędów parsowania/enkodowania.
    fail("Błąd: " . $e->getMessage(), 2);
}

if (PHP_SAPI !== 'cli') {
    // WWW: ustawiamy JSON content-type, żeby przeglądarka/klient poprawnie rozpoznał odpowiedź.
    header('Content-Type: application/json; charset=utf-8');
}

// Zwracamy JSON na stdout (CLI) albo w treści odpowiedzi HTTP (WWW).
echo $json;