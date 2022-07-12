<?php

namespace uuf6429\ExpressionLanguage;

use PHPUnit\Framework\TestCase;
use uuf6429\ExpressionLanguage\Lexer;
use Symfony\Component\ExpressionLanguage\Token;
use uuf6429\ExpressionLanguage\TokenStream;

class LexerTest extends TestCase
{
    /**
     * @param Token[] $tokens
     * @param string $expression
     *
     * @dataProvider getTokenizeData
     */
    public function testTokenize(array $tokens, string $expression): void
    {
        $tokens[] = new Token('end of expression', null, strlen($expression) + 1);
        $lexer = new Lexer();
        $this->assertEquals(new TokenStream($tokens), $lexer->tokenize($expression));
    }

    public function getTokenizeData(): array
    {
        return array(
            array(
                array(new Token('name', 'a', 3)),
                '  a  ',
            ),
            array(
                array(new Token('name', 'a', 1)),
                'a',
            ),
            array(
                array(new Token('string', 'foo', 1)),
                '"foo"',
            ),
            array(
                array(new Token('number', '3', 1)),
                '3',
            ),
            array(
                array(new Token('operator', '+', 1)),
                '+',
            ),
            array(
                array(new Token('punctuation', '.', 1)),
                '.',
            ),
            array(
                array(
                    new Token('punctuation', '(', 1),
                    new Token('number', '3', 2),
                    new Token('operator', '+', 4),
                    new Token('number', '5', 6),
                    new Token('punctuation', ')', 7),
                    new Token('operator', '~', 9),
                    new Token('name', 'foo', 11),
                    new Token('punctuation', '(', 14),
                    new Token('string', 'bar', 15),
                    new Token('punctuation', ')', 20),
                    new Token('punctuation', '.', 21),
                    new Token('name', 'baz', 22),
                    new Token('punctuation', '[', 25),
                    new Token('number', '4', 26),
                    new Token('punctuation', ']', 27),
                ),
                '(3 + 5) ~ foo("bar").baz[4]',
            ),
            array(
                array(new Token('operator', '..', 1)),
                '..',
            ),
            array(
                array(new Token('string', '#foo', 1)),
                "'#foo'",
            ),
            array(
                array(new Token('string', '#foo', 1)),
                '"#foo"',
            ),
            array(
                array(
                    new Token('name', 'foo', 1),
                    new Token('punctuation', '(', 4),
                    new Token('punctuation', '(', 5),
                    new Token('name', 'bar', 6),
                    new Token('punctuation', ',', 9),
                    new Token('name', 'baz', 11),
                    new Token('punctuation', ')', 14),
                    new Token('operator', '->', 16),
                    new Token('punctuation', '{', 19),
                    new Token('name', 'baz', 21),
                    new Token('punctuation', '}', 25),
                    new Token('punctuation', ')', 26),
                ),
                'foo((bar, baz) -> { baz })',
            ),
        );
    }
}
