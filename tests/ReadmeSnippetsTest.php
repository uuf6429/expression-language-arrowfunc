<?php declare(strict_types=1);

namespace uuf6429\ExpressionLanguageTests;

use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @internal
 */
final class ReadmeSnippetsTest extends TestCase
{
	/**
	 * @var string
	 */
	private const README_FILE = __DIR__ . '/../README.md';

	/**
	 * @dataProvider provideSnippets
	 */
	public function testSnippet(string $snippet): void
	{
		$this->expectNotToPerformAssertions();

		eval($snippet);
	}

	/**
	 * @return iterable<array{snippet: string}>
	 */
	public static function provideSnippets(): iterable
	{
		$content = file_get_contents(self::README_FILE);
		if ($content === false) {
			throw new RuntimeException('File could not be read: ' . self::README_FILE);
		}

		preg_match_all('/\n```php\n(.+?)\n```\n/s', $content, $matches);

		foreach ($matches[1] as $i => $match) {
			yield "Snippet #{$i}" => ['snippet' => $match];
		}
	}
}
