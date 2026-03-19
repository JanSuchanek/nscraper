<?php
declare(strict_types=1);
namespace NScraper\Tests;
use NScraper\WebScraper;
use Tester\Assert;
use Tester\TestCase;
require __DIR__ . '/../vendor/autoload.php';
\Tester\Environment::setup();

class WebScraperTest extends TestCase
{
	public function testExtractDescriptionJsonLd(): void
	{
		$s = new WebScraper();
		$html = '<html><script type="application/ld+json">{"@type":"Product","description":"Test product desc"}</script></html>';
		Assert::same('Test product desc', $s->extractDescription($html));
	}

	public function testExtractDescriptionMeta(): void
	{
		$s = new WebScraper();
		$html = '<html><meta name="description" content="This is a meta description that is long enough to be useful for our extraction test"></html>';
		Assert::contains('meta description', $s->extractDescription($html));
	}

	public function testExtractDescriptionReturnsNullOnEmpty(): void
	{
		$s = new WebScraper();
		Assert::null($s->extractDescription('<html><body>short</body></html>'));
	}

	public function testFetchReturnsNullOnBadUrl(): void
	{
		$s = new WebScraper();
		Assert::null($s->fetch('http://nonexistent.invalid.test'));
	}

	public function testBuildCompetitorContextReturnString(): void
	{
		$s = new WebScraper();
		$ctx = $s->buildCompetitorContext('nonexistent-product-xyz-12345');
		Assert::type('string', $ctx);
	}
}
(new WebScraperTest())->run();
