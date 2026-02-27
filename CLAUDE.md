# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Opis projektu

Biblioteka PHP dostarczająca dwa custom fixery dla php-cs-fixer, które zachowują bazową indentację bloków PHP osadzonych w HTML. Fixery pracują w tandemie: dedent (priority 1000) usuwa indentację HTML przed formatowaniem, reindent (priority -1000) przywraca ją po formatowaniu.

## Komendy

```bash
# Instalacja zależności
composer install

# Uruchomienie fixerów (wymaga konfiguracji w projekcie-hoście)
# W .php-cs-fixer.dist.php projektu dodaj:
#   ->registerCustomFixers([
#       new \RepinsPL\PhpCsFixerHtmlIndent\HtmlContextDedentFixer(),
#       new \RepinsPL\PhpCsFixerHtmlIndent\HtmlContextReindentFixer(),
#   ])
#   ->setRules(['RepinsPL/html_context_dedent' => true, 'RepinsPL/html_context_reindent' => true])
```

Brak testów, lintera i CI — projekt nie ma jeszcze `phpunit.xml`, `phpstan.neon` ani pipeline'u.

## Architektura

Trzy pliki w `src/`, namespace `RepinsPL\PhpCsFixerHtmlIndent\` (PSR-4):

- **HtmlContextDetectionTrait** — wspólna logika: `detectBaseIndent()` wykrywa liczbę tabulacji z poprzedzającego `T_INLINE_HTML`, `findBlockClose()` znajduje odpowiadający `T_CLOSE_TAG`.
- **HtmlContextDedentFixer** (`RepinsPL/html_context_dedent`, priority 1000) — przed innymi fixerami usuwa bazową indentację HTML z bloków PHP, aby fixery formatujące pracowały na "czystym" kodzie.
- **HtmlContextReindentFixer** (`RepinsPL/html_context_reindent`, priority -1000) — po wszystkich fixerach przywraca bazową indentację, zachowując formatowanie w kontekście HTML.

### Kluczowe decyzje techniczne

- Oba fixery iterują tokeny **wstecz** (od końca), aby modyfikacje nie przesuwały indeksów wcześniejszych tokenów.
- Dedent używa `clearAt()` zamiast usuwania tokenów — reindent sprawdza cleared tokens i odtwarza je jako `T_WHITESPACE`.
- Regex `/\n(?!\n)/` w reindent — negative lookahead zapobiega dodawaniu indentacji przed pustymi liniami.
- Detekcja bazowej indentacji obsługuje wyłącznie tabulacje (`^\t+$`), nie spacje.
- Wymagania: PHP >= 8.1, php-cs-fixer ^3.0.
