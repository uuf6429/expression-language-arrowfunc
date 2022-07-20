<?php

namespace uuf6429\ExpressionLanguage;

use PHPUnit\Framework\TestCase;
use Symfony\Component\ExpressionLanguage\Token;

class TokenStreamTest extends TestCase
{
    /** @var Token[] */
    protected $tokens;

    protected function setUp(): void
    {
        $this->tokens = [
            new Token(Token::PUNCTUATION_TYPE, '(', 1),
            new Token(Token::NAME_TYPE, 'foo', 2),
            new Token(Token::OPERATOR_TYPE, '*', 6),
            new Token(Token::NAME_TYPE, 'bar', 8),
            new Token(Token::PUNCTUATION_TYPE, ')', 11),
            new Token(Token::EOF_TYPE, null, 12),
        ];
    }

    public function testMovingForward(): void
    {
        $tokens = $this->tokens;
        $stream = $this->getStream();

        $this->assertEquals($tokens[0], $stream->current);
        $this->assertSame(0, $stream->position());

        $stream->next();
        $this->assertEquals($tokens[1], $stream->current);
        $this->assertSame(1, $stream->position());

        $stream->next();
        $stream->expect(Token::OPERATOR_TYPE, '*');
        $this->assertEquals($tokens[3], $stream->current);
        $this->assertSame(3, $stream->position());
    }

    public function testMovingBackward(): void
    {
        $tokens = $this->tokens;
        $stream = $this->getStream();

        $stream->next();
        $stream->next();

        $this->assertEquals($tokens[2], $stream->current);
        $this->assertSame(2, $stream->position());

        $stream->prev();
        $this->assertEquals($tokens[1], $stream->current);
        $this->assertSame(1, $stream->position());

        $stream->expectPrev(Token::NAME_TYPE, 'foo');
        $this->assertEquals($tokens[0], $stream->current);
        $this->assertSame(0, $stream->position());
    }

    public function testSeeking(): void
    {
        $tokens = $this->tokens;
        $stream = $this->getStream();

        $stream->seek(3, SEEK_SET);
        $this->assertEquals($tokens[3], $stream->current);
        $this->assertSame(3, $stream->position());

        $stream->seek(-1, SEEK_CUR);
        $this->assertEquals($tokens[2], $stream->current);
        $this->assertSame(2, $stream->position());

        $stream->seek(2, SEEK_CUR);
        $this->assertEquals($tokens[4], $stream->current);
        $this->assertSame(4, $stream->position());

        $stream->seek(0, SEEK_END);
        $this->assertEquals($tokens[5], $stream->current);
        $this->assertSame(5, $stream->position());
        $this->assertTrue($stream->isEOF());

        $stream->seek(-2, SEEK_END);
        $this->assertEquals($tokens[3], $stream->current);
        $this->assertSame(3, $stream->position());
    }

    public function testSplicing(): void
    {
        $tokens = $this->tokens;
        $replacement1 = new Token(Token::NUMBER_TYPE, 42, 0);
        $replacement2 = new Token(Token::NUMBER_TYPE, 64, 0);
        $original = $this->getStream();
        $spliced = $original->splice(2, 3, [$replacement1, $replacement2]);

        $spliced->expect($tokens[0]->type, $tokens[0]->value);
        $spliced->expect($tokens[1]->type, $tokens[1]->value);

        $spliced->expect($replacement1->type, $replacement1->value);
        $spliced->expect($replacement2->type, $replacement2->value);

        $this->assertTrue($spliced->isEOF());

        $original->expect($tokens[0]->type, $tokens[0]->value);
        $original->expect($tokens[1]->type, $tokens[1]->value);
        $original->expect($tokens[2]->type, $tokens[2]->value);
        $original->expect($tokens[3]->type, $tokens[3]->value);

        $this->assertTrue($spliced->isEOF());
    }

    private function getStream(): TokenStream
    {
        return new TokenStream($this->tokens);
    }
}
