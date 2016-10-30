<?php

namespace uuf6429\ExpressionLanguage;

use Symfony\Component\ExpressionLanguage\Token;
use Symfony\Component\ExpressionLanguage\Node\NameNode;
use Symfony\Component\ExpressionLanguage\Node\Node;
use uuf6429\ExpressionLanguage\Node\ArrowFuncNode;

class Parser extends Symfony\Component\ExpressionLanguage\Parser
{
    const TOKEN_REPLACEMENT_TYPE = 'replacement';

    /** @var Node[] */
    private $replacementNodes;

    /**
     * {@inheritdoc}
     */
    public function __construct(array $functions)
    {
        parent::__construct($functions);

        $this->binaryOperators = array(
            '->' => array('precedence' => 5, 'associativity' => self::OPERATOR_LEFT),
        ) + $this->binaryOperators;

        $this->replacementNodes = array();
    }

    /**
     * {@inheritdoc}
     */
    public function parse(TokenStream $stream, $names = array())
    {
        $this->names = $names;
        $stream = $this->preParseArrowFuncs($stream);

        return parent::parse($stream, $names);
    }

    /**
     * Replaces all anonymous functions with placeholder tokens.
     *
     * @param TokenStream $stream
     *
     * @return TokenStream
     */
    protected function preParseArrowFuncs(TokenStream $stream)
    {
        while (!$stream->isEOF()) {
            if ($stream->current->test(Token::OPERATOR_TYPE, '->')) {
                $operatorPos = $stream->position();
                $operatorCursor = $stream->current->cursor;
                $replacementNodeIndex = count($this->replacementNodes);
                $this->replacementNodes[$replacementNodeIndex] = null;

                // parse parameters
                $parameterNames = array();
                $parameterNodes = array();
                $expectParam = true;
                $stream->prev();
                $stream->expectPrev(Token::PUNCTUATION_TYPE, ')', 'Parameter list must end with parenthesis');
                while (!$stream->current->test(Token::PUNCTUATION_TYPE, '(')) {
                    if ($expectParam) {
                        $stream->current->test(Token::NAME_TYPE);
                        array_unshift($parameterNames, $stream->current->value);
                        array_unshift($parameterNodes, new NameNode($stream->current->value));
                        $stream->prev();
                    } else {
                        $stream->expectPrev(Token::PUNCTUATION_TYPE, ',', 'Parameters must be separated by a comma');
                    }
                    $expectParam = !$expectParam;
                }
                $startPos = $stream->position();

                // parse body
                $stream->seek($operatorPos, SEEK_SET);
                $stream->next();
                $stream->expect(Token::PUNCTUATION_TYPE, '{', 'Anonymous function body must start with a curly bracket');
                $bodyTokens = array();
                $openingBracketCount = 1;
                while ($openingBracketCount != 0) {
                    if ($stream->current->test(Token::PUNCTUATION_TYPE, '{')) {
                        ++$openingBracketCount;
                    }

                    if ($stream->current->test(Token::PUNCTUATION_TYPE, '}')) {
                        --$openingBracketCount;
                    }

                    if (!$openingBracketCount) {
                        $currentNames = $this->names;
                        $currentStream = $this->stream;

                        $bodyTokens[] = new Token(Token::EOF_TYPE, null, 0);
                        $bodyNode = $this->parse(
                            new TokenStream($bodyTokens),
                            array_merge($currentNames, $parameterNames)
                        );

                        $this->names = $currentNames;
                        $this->stream = $currentStream;
                        break;
                    }

                    $bodyTokens[] = $stream->current;
                    $stream->next();
                }
                $stream->expect(Token::PUNCTUATION_TYPE, '}', 'Anonymous function body must end with a curly bracket');
                $endPos = $stream->position();

                // update token stream
                $this->replacementNodes[$replacementNodeIndex] = new ArrowFuncNode($parameterNodes, $bodyNode);
                $replacement = new Token(static::TOKEN_REPLACEMENT_TYPE, $replacementNodeIndex, $operatorCursor);
                $stream = $stream->splice($startPos, $endPos - $startPos, array($replacement));

                // keep parsing anonymous functions
                $stream->seek($startPos, SEEK_SET);
            }

            $stream->next();
        }
        $stream->rewind();

        return $stream;
    }

    public function parsePrimaryExpression()
    {
        $token = $this->stream->current;

        if ($token->type === static::TOKEN_REPLACEMENT_TYPE) {
            $this->stream->next();

            return $this->replacementNodes[$token->value];
        }

        return parent::parsePrimaryExpression();
    }
}
