# pdf-to-json

Prosty skrypt PHP, który wyciąga tekst (i podstawowe metadane) z pliku PDF i zwraca wynik jako JSON.

## Wymagania

- PHP 7.4+ (u Ciebie działa na PHP 8.3)
- Composer

## Instalacja

```bash
composer install
```

## Uruchomienie (CLI)

Zwróć JSON na stdout:

```bash
php index.php "document.pdf"
```

Zapisz wynik do pliku:

```bash
php index.php "document.pdf" > document.json
```

Jeśli nie podasz argumentu, skrypt spróbuje użyć domyślnego pliku `document.pdf` w katalogu projektu.

## Uruchomienie (WWW)

Możesz uruchomić skrypt przez serwer PHP:

```bash
php -S localhost:8000
```

Następnie:

- domyślny plik: `http://localhost:8000/index.php`
- wskazanie pliku: `http://localhost:8000/index.php?file=document.pdf`

## Format danych

JSON zawiera:

- `metadata`: metadane PDF (to, co zwraca parser)
- `pages`: tablica stron z polami:
  - `page`: numer strony (od 1)
  - `text`: tekst strony
  - `fonts`: lista nazw fontów wykrytych na stronie

