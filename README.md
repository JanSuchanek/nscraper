# NScraper — Web Scraper for Product Analysis

Competitor product scraping — Heureka.cz, Google snippets, JSON-LD extraction. Zero dependencies beyond ext-curl.

## Installation

```bash
composer require jansuchanek/nscraper
```

## Usage

```php
use NScraper\WebScraper;

$scraper = new WebScraper();

// Scrape Heureka.cz product descriptions
$data = $scraper->scrapeHeureka('Samsung Galaxy S24');
// ['descriptions' => [...], 'source' => 'heureka']

// Google search snippets
$data = $scraper->scrapeGoogle('Samsung Galaxy S24');

// Scrape any URL (JSON-LD, meta, content extraction)
$desc = $scraper->scrapeUrl('https://example.com/product/123');

// Build context for AI pipelines
$context = $scraper->buildCompetitorContext('Samsung Galaxy S24');
```

## Extraction Strategies

1. **JSON-LD** structured data (`@type: Product`)
2. **Meta description** tag
3. **CSS selectors** — `.product-desc`, `.description`, `#description`

## Requirements

- PHP >= 8.1
- ext-curl
