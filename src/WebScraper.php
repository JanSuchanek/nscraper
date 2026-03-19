<?php

declare(strict_types=1);

namespace NScraper;

/**
 * Web scraper for competitor product analysis.
 *
 * Zero dependencies — pure curl. Extracts product descriptions from:
 * - Heureka.cz (CZ price comparison)
 * - Google Search snippets (fallback)
 * - Any URL (JSON-LD, meta, content extraction)
 */
class WebScraper
{
	/**
	 * Search Heureka.cz for a product and extract competitor descriptions.
	 *
	 * @return array{descriptions: list<string>, source: string}
	 */
	public function scrapeHeureka(string $productName): array
	{
		$query = urlencode($productName);
		$url = "https://www.heureka.cz/?h[fraze]={$query}";

		$html = $this->fetch($url);
		if (!$html) {
			return ['descriptions' => [], 'source' => 'heureka'];
		}

		$detailUrls = $this->extractDetailUrls($html);

		$descriptions = [];
		foreach (array_slice($detailUrls, 0, 3) as $detailUrl) {
			$detailHtml = $this->fetch($detailUrl);
			if ($detailHtml) {
				$desc = $this->extractDescription($detailHtml);
				if ($desc && mb_strlen($desc) > 50) {
					$descriptions[] = $desc;
				}
			}
		}

		return ['descriptions' => $descriptions, 'source' => 'heureka'];
	}


	/**
	 * Search Google for product descriptions (simple HTTP, no API).
	 *
	 * @return array{descriptions: list<string>, source: string}
	 */
	public function scrapeGoogle(string $productName, string $suffix = 'eshop popis'): array
	{
		$query = urlencode($productName . ' ' . $suffix);
		$url = "https://www.google.com/search?q={$query}&hl=cs&num=5";

		$html = $this->fetch($url, [
			'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
			'Accept-Language: cs-CZ,cs;q=0.9',
		]);

		if (!$html) {
			return ['descriptions' => [], 'source' => 'google'];
		}

		$descriptions = [];
		if (preg_match_all('/<span[^>]*class="[^"]*"[^>]*>([^<]{80,300})<\/span>/u', $html, $matches)) {
			foreach (array_slice($matches[1], 0, 5) as $snippet) {
				$clean = trim(strip_tags(html_entity_decode($snippet, ENT_QUOTES, 'UTF-8')));
				if (mb_strlen($clean) > 50) {
					$descriptions[] = $clean;
				}
			}
		}

		return ['descriptions' => $descriptions, 'source' => 'google'];
	}


	/**
	 * Scrape a specific URL and extract the main text content.
	 */
	public function scrapeUrl(string $url): ?string
	{
		$html = $this->fetch($url);
		return $html ? $this->extractDescription($html) : null;
	}


	/**
	 * Build a context string from scraped competitor data (for AI pipelines).
	 */
	public function buildCompetitorContext(string $productName): string
	{
		$heurekaData = $this->scrapeHeureka($productName);
		$context = '';

		if (!empty($heurekaData['descriptions'])) {
			$context .= "Konkurenční popisky produktu (z Heureky):\n";
			foreach ($heurekaData['descriptions'] as $i => $desc) {
				$truncated = mb_substr($desc, 0, 500);
				$context .= "--- Popis " . ($i + 1) . " ---\n{$truncated}\n\n";
			}
		}

		if (!$context) {
			$googleData = $this->scrapeGoogle($productName);
			if (!empty($googleData['descriptions'])) {
				$context .= "Informace z vyhledávačů:\n";
				foreach ($googleData['descriptions'] as $snippet) {
					$context .= "- {$snippet}\n";
				}
			}
		}

		return $context;
	}


	/**
	 * Extract product description from HTML using multiple strategies.
	 */
	public function extractDescription(string $html): ?string
	{
		// 1. JSON-LD structured data
		if (preg_match('/<script[^>]*type="application\/ld\+json"[^>]*>(.*?)<\/script>/s', $html, $m)) {
			/** @var array<string, mixed>|null $data */
			$data = json_decode($m[1], true);
			if (is_array($data) && isset($data['description']) && is_string($data['description'])) {
				return strip_tags($data['description']);
			}
			if (is_array($data) && isset($data['@graph']) && is_array($data['@graph'])) {
				foreach ($data['@graph'] as $item) {
					if (is_array($item) && isset($item['description'], $item['@type']) && $item['@type'] === 'Product' && is_string($item['description'])) {
						return strip_tags($item['description']);
					}
				}
			}
		}

		// 2. Meta description
		if (preg_match('/<meta[^>]*name="description"[^>]*content="([^"]+)"/', $html, $m)) {
			$desc = html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
			if (mb_strlen($desc) > 50) {
				return $desc;
			}
		}

		// 3. Common content containers
		$selectors = [
			'/<div[^>]*class="[^"]*product[_-]?desc[^"]*"[^>]*>(.*?)<\/div>/si',
			'/<div[^>]*class="[^"]*description[^"]*"[^>]*>(.*?)<\/div>/si',
			'/<div[^>]*id="[^"]*description[^"]*"[^>]*>(.*?)<\/div>/si',
			'/<div[^>]*class="[^"]*detail[^"]*"[^>]*>(.*?)<\/div>/si',
		];

		foreach ($selectors as $pattern) {
			if (preg_match($pattern, $html, $m)) {
				$text = trim(strip_tags($m[1]));
				if (mb_strlen($text) > 80) {
					return mb_substr($text, 0, 1000);
				}
			}
		}

		return null;
	}


	/**
	 * @return list<string>
	 */
	private function extractDetailUrls(string $html): array
	{
		$urls = [];
		if (preg_match_all('/href="(https:\/\/[^"]*heureka\.cz\/[^"]*\/)"/', $html, $matches)) {
			foreach ($matches[1] as $url) {
				if (preg_match('/\/p\d+\/$|\/recenze\/$/', $url) || !str_contains($url, '/porovnani/')) {
					$urls[] = $url;
				}
			}
		}
		return array_values(array_unique($urls));
	}


	/**
	 * Fetch URL content via cURL.
	 *
	 * @param list<string> $headers
	 */
	public function fetch(string $url, array $headers = []): ?string
	{
		$defaultHeaders = [
			'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
			'Accept: text/html,application/xhtml+xml,application/xml;q=0.9',
			'Accept-Language: cs-CZ,cs;q=0.9,en;q=0.8',
		];

		$ch = curl_init($url);
		curl_setopt_array($ch, [
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_MAXREDIRS => 3,
			CURLOPT_TIMEOUT => 10,
			CURLOPT_HTTPHEADER => $headers ?: $defaultHeaders,
			CURLOPT_SSL_VERIFYPEER => false,
		]);

		$result = curl_exec($ch);
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		return ($httpCode === 200 && is_string($result)) ? $result : null;
	}
}
